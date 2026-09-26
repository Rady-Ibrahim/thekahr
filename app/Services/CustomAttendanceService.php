<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CustomAttendanceService
{
    /** Note stamped on auto-closed (forgotten) sessions. */
    public const AUTO_CLOSED_NOTE = 'إغلاق تلقائي بعد 20 ساعة';

    public function __construct(private AttendanceHoursService $hours)
    {
    }

    /**
     * Start a new work session (check-in) for a custom-attendance employee.
     * Multiple completed sessions per day are allowed; only one open session at a time.
     */
    public function startSession(Employee $employee, array $data = [], string $source = 'mobile', ?Carbon $now = null): array
    {
        return DB::transaction(function () use ($employee, $data, $source, $now) {
            // Auto-close any stale open sessions before checking.
            $this->autoCloseStaleSessions($employee->id);

            if ($this->openSession($employee)) {
                return [
                    'success' => false,
                    'message' => 'لديك جلسة عمل مفتوحة حالياً، يجب تسجيل الانصراف أولاً',
                ];
            }

            $now = $now ?? now();
            $today = $now->toDateString();
            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $today],
                [
                    'status' => 'present',
                    'required_hours' => $employee->requiredDailyHours(),
                ]
            );

            // Absent days flip back to present once the employee shows up.
            if ($attendance->status === 'absent') {
                $attendance->update(['status' => 'present', 'late_minutes' => 0]);
            }

            $checkInPhoto = isset($data['photo']) && $data['photo'] instanceof UploadedFile
                ? $data['photo']->store('attendance/checkin', 'public')
                : null;

            $log = AttendanceLog::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'log_date' => $today,
                'check_in_time' => $now->toTimeString(),
                'check_in_latitude' => $data['latitude'] ?? null,
                'check_in_longitude' => $data['longitude'] ?? null,
                'check_in_photo' => $checkInPhoto,
                'duration_minutes' => 0,
                'source' => $source,
            ]);

            // Keep the main record's times readable by the dashboard immediately,
            // not only after the first check-out.
            $this->syncDashboardTimes($attendance->id);

            return [
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح (جلسة رقم ' . $attendance->logs()->count() . ')',
                'session' => $log,
                'attendance' => $attendance->fresh('logs'),
            ];
        });
    }

    /**
     * End the open session (check-out), compute its duration and re-aggregate the day.
     */
    public function endSession(AttendanceLog $log, array $data = [], ?Carbon $now = null): array
    {
        return DB::transaction(function () use ($log, $data, $now) {
            if (!$log->isOpen()) {
                return ['success' => false, 'message' => 'تم تسجيل الانصراف لهذه الجلسة مسبقاً'];
            }

            $checkOutPhoto = isset($data['photo']) && $data['photo'] instanceof UploadedFile
                ? $data['photo']->store('attendance/checkout', 'public')
                : null;

            $now = $now ?? now();
            $checkInAt = $log->checkInAt();

            // Handle sessions spanning midnight: a clock-time-only override (or the
            // real now()) may fall before the check-in clock time but actually belongs
            // to the next day, so bump it forward to keep the stored clock sane.
            if ($now->lessThan($checkInAt)) {
                $now->addDay();
            }

            $log->update([
                'check_out_time' => $now->toTimeString(),
                'check_out_latitude' => $data['latitude'] ?? null,
                'check_out_longitude' => $data['longitude'] ?? null,
                'check_out_photo' => $checkOutPhoto,
            ]);

            // Per-session duration, anchored to the session's OWN log_date and to
            // its own check-in/check-out pair via diffInMinutes. A 17:07→18:15
            // session stays 68 minutes regardless of the wall clock, an overnight
            // session keeps its true length, and a session with no real check-out
            // stays at 0 instead of falling back to a default.
            $durationMinutes = $log->isOpen()
                ? 0
                : $this->hours->sessionMinutes($log->fresh());

            $log->update(['duration_minutes' => $durationMinutes]);

            $attendance = $this->recalculateDay($log->attendance_id);

            return [
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح',
                'session_duration_minutes' => $durationMinutes,
                'summary' => $attendance ? $this->buildSummary($attendance) : null,
            ];
        });
    }

    public function openSession(Employee $employee): ?AttendanceLog
    {
        return AttendanceLog::where('employee_id', $employee->id)
            ->whereNull('check_out_time')
            ->latest('check_in_time')
            ->first();
    }

    /**
     * Auto-close all stale open sessions for custom-attendance employees.
     * A session is stale when its check-in time is older than the configured
     * auto_close_after_hours (default 20 hours).
     *
     * @return int number of sessions closed
     */
    public function autoCloseStaleSessions(?int $employeeId = null, ?Carbon $now = null): int
    {
        $hours = (float) config('hr.working_hours.auto_close_after_hours', 20);
        $now ??= now();
        $cutoff = $now->copy()->subHours($hours);

        $query = AttendanceLog::whereNull('check_out_time')
            ->whereNotNull('check_in_time')
            ->where(function ($q) use ($cutoff) {
                $q->where('log_date', '<', $cutoff->toDateString())
                    ->orWhere(function ($q) use ($cutoff) {
                        $q->where('log_date', $cutoff->toDateString())
                            ->where('check_in_time', '<=', $cutoff->toTimeString());
                    });
            });

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        $closed = 0;

        foreach ($query->get() as $log) {
            $checkInAt = $log->checkInAt();
            $autoCheckOut = $checkInAt->copy()->addHours($hours);

            $log->update([
                'check_out_time' => $autoCheckOut->toTimeString(),
                'notes' => self::AUTO_CLOSED_NOTE,
            ]);

            // Duration of THIS session only, then re-aggregate its own day.
            $log->update(['duration_minutes' => $this->hours->sessionMinutes($log->fresh())]);

            $this->recalculateDay($log->attendance_id);
            $closed++;
        }

        return $closed;
    }

    /**
     * Admin manual entry: create a fully-specified session (check-in/out times)
     * for any date, optionally overriding the day's required hours.
     */
    public function manualSession(Employee $employee, array $data): array
    {
        return DB::transaction(function () use ($employee, $data) {
            $date = Carbon::parse($data['date'] ?? today()->toDateString())->toDateString();

            if ($date > today()->toDateString()) {
                return ['success' => false, 'message' => 'لا يمكن تسجيل جلسة بتاريخ مستقبلي'];
            }

            if ($data['check_out_time'] === $data['check_in_time']) {
                return ['success' => false, 'message' => 'وقت الحضور والانصراف متطابقان'];
            }

            // Duration of this session only, from its own check-in/check-out pair.
            $durationMinutes = $this->hours->minutesBetween($date, $data['check_in_time'], $data['check_out_time']);

            if ($durationMinutes <= 0) {
                return ['success' => false, 'message' => 'أوقات الحضور والانصراف غير صحيحة'];
            }

            $attendance = Attendance::firstOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $date],
                [
                    'status' => 'present',
                    'required_hours' => $employee->requiredDailyHours(),
                ]
            );

            if ($attendance->status === 'absent') {
                $attendance->update(['status' => 'present', 'late_minutes' => 0]);
            }

            AttendanceLog::create([
                'employee_id'      => $employee->id,
                'attendance_id'    => $attendance->id,
                'log_date'         => $date,
                'check_in_time'    => $data['check_in_time'],
                'check_out_time'   => $data['check_out_time'],
                'duration_minutes' => $durationMinutes,
                'source'           => 'admin',
                'notes'            => $data['notes'] ?? null,
            ]);

            if (!empty($data['required_hours'])) {
                $this->applyRequiredHours($attendance->id, (float) $data['required_hours']);
            }

            $updated = $this->recalculateDay($attendance->id);

            return [
                'success' => true,
                'message' => sprintf(
                    'تم تسجيل الجلسة يدوياً (%s دقيقة عمل)',
                    number_format($durationMinutes)
                ),
                'session_duration_minutes' => $durationMinutes,
                'summary' => $updated ? $this->buildSummary($updated) : null,
            ];
        });
    }

    /**
     * Set a per-day required-hours override (null = reset to employee default).
     */
    public function applyRequiredHours(int $attendanceId, ?float $hours): ?Attendance
    {
        $attendance = Attendance::findOrFail($attendanceId);

        $attendance->update([
            'required_hours' => $hours ?? $attendance->employee->requiredDailyHours(),
        ]);

        return $this->recalculateDay($attendanceId);
    }

    /**
     * Update the employee's daily required hours and re-sync today's record,
     * so totals/deductions reflect the new target immediately.
     */
    public function setDailyRequiredHours(Employee $employee, float $hours): array
    {
        return DB::transaction(function () use ($employee, $hours) {
            $employee->update([
                'is_custom_attendance' => true,
                'daily_required_hours' => $hours,
            ]);

            $attendance = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', today())
                ->first();

            if ($attendance) {
                $this->applyRequiredHours($attendance->id, $hours);
            }

            return [
                'success' => true,
                'message' => sprintf('تم تحديد %s ساعة مطلوبة يومياً للموظف %s', rtrim(rtrim(number_format($hours, 2), '0'), '.'), $employee->name),
            ];
        });
    }

    /**
     * Compute duration for admin-entered times; an end time earlier than the
     * start time is treated as crossing midnight. Delegates the minute maths to
     * AttendanceHoursService so there is a single implementation.
     */
    public function resolveSessionRange(string $checkIn, string $checkOut, ?string $date = null): array
    {
        $date = $date ?: today()->toDateString();

        $in = Carbon::createFromFormat('H:i', substr($checkIn, 0, 5))->setDateFrom(Carbon::parse($date));
        $out = Carbon::createFromFormat('H:i', substr($checkOut, 0, 5))->setDateFrom(Carbon::parse($date));

        if ($out->lessThan($in)) {
            $out->addDay();
        }

        return [$in, $out];
    }

    /**
     * Duration in minutes of a single session, from its own punch pair.
     */
    public function sessionMinutes(AttendanceLog $log): int
    {
        return $this->hours->sessionMinutes($log);
    }

    /**
     * Aggregate ONE day of an attendance record: per-session totals, the unified
     * hours status, the shortfall deduction and the overtime figures.
     *
     * Everything is delegated to AttendanceHoursService, which guarantees:
     *  - sessions are timed individually (never re-timed as a block),
     *  - only sessions of THIS calendar day are summed (whereDate('log_date')),
     *  - a day with no real check-in/check-out yields 0.00 hours, never a
     *    hardcoded fallback such as the 20-hour auto-close window.
     */
    public function recalculateDay(?int $attendanceId): ?Attendance
    {
        $attendance = Attendance::with('logs')->find($attendanceId);
        if (!$attendance) {
            return null;
        }

        $employee = $attendance->employee;

        $date = $this->hours->resolveDate($attendance);

        // Unified status + deduction + overtime, computed from a per-session
        // sum that is strictly scoped to the record's own date.
        // The 4th element is the AUTHORITATIVE total for the day: it is already
        // zeroed for absent / on-leave days and for days with no completed
        // session, so a leftover session can never manufacture hours.
        [$hoursStatus, $shortfallDeduction, $overtimeMinutes, $effective] = $this->hours->resolveDayStatus(
            $attendance,
            $employee,
            $date
        );

        $totals = [
            'total_minutes' => $effective['total_minutes'],
            'total_hours'   => $effective['total_hours'],
        ];

        $requiredHours = $this->hours->requiredHoursFor($attendance, $employee);

        // Keep legacy columns in sync so existing reports/salary flows stay correct.
        $attendance->update([
            'total_worked_minutes' => $totals['total_minutes'],
            'total_worked_hours'   => $totals['total_hours'],
            'required_hours'       => $requiredHours,
            'hours_status'         => $hoursStatus,
            'overtime_minutes'     => $overtimeMinutes,
            'overtime_hours'       => round($overtimeMinutes / 60, 2),
            'actual_worked_hours'  => $totals['total_hours'],
            'working_hours'        => (int) floor($totals['total_minutes'] / 60),
            'deduction_amount'     => $hoursStatus === Attendance::HOURS_SHORTFALL ? $shortfallDeduction : 0.0,
        ]);

        // Mirror the day's first check-in and latest closed check-out onto the main
        // attendance record so dashboard queries read them directly (no more "--").
        $this->syncDashboardTimes($attendanceId);

        return $attendance->fresh('logs');
    }

    /**
     * Persist the first session's check-in and the latest CLOSED session's
     * check-out of THIS DAY onto the attendances row so the dashboard can read
     * them directly. Date-scoped so a neighbouring day's session can never
     * stretch the displayed range.
     */
    private function syncDashboardTimes(int $attendanceId): void
    {
        $attendance = Attendance::find($attendanceId);

        if (!$attendance) {
            return;
        }

        $logs = $this->hours->dayLogs($attendance);

        Attendance::whereKey($attendanceId)->update([
            'check_in_time'  => $logs->whereNotNull('check_in_time')->min('check_in_time'),
            'check_out_time' => $logs->whereNotNull('check_out_time')->max('check_out_time'),
        ]);
    }

    /**
     * Live summary for the punch interface: sessions list, cumulative totals and remaining time.
     */
    public function todaySummary(Employee $employee): array
    {
        $today = today()->toDateString();

        $attendance = Attendance::with('logs')
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        $openSession = $this->openSession($employee);

        $sessions = $attendance
            ? $this->hours->dayLogs($attendance, $today)
                ->map(fn (AttendanceLog $log) => $this->formatSession($log))
                ->values()
                ->all()
            : [];

        return [
            'is_custom_attendance' => true,
            'employee_name'        => $employee->name,
            'daily_required_hours' => (float) $employee->requiredDailyHours(),
            'overtime_enabled'     => $this->hours->overtimeEnabled($employee),
            'attendance_id'        => $attendance?->id,
            'sessions'             => $sessions,
            'open_session'         => $openSession ? $this->formatSession($openSession) : null,
            'elapsed_open_session_minutes' => $openSession
                ? max(0, (int) $openSession->checkInAt()->diffInMinutes(now()))
                : 0,
            ...($attendance ? $this->buildSummary($attendance) : [
                'total_worked_minutes'        => 0,
                'total_worked_hours'          => 0.0,
                'remaining_minutes'           => (int) round($employee->requiredDailyHours() * 60),
                'required_minutes'            => (int) round($employee->requiredDailyHours() * 60),
                'required_hours'              => (float) $employee->requiredDailyHours(),
                'overtime_minutes'            => 0,
                'overtime_hours'              => 0.0,
                'hours_status'                => null,
                'sessions_count'              => 0,
                'completed_sessions_count'    => 0,
                'open_sessions_count'         => $openSession ? 1 : 0,
                'shortfall_deduction_amount'  => 0.0,
            ]),
        ];
    }

    private function buildSummary(Attendance $attendance): array
    {
        return $this->hours->summary($attendance, $attendance->employee);
    }

    private function formatSession(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'log_date' => $log->log_date?->toDateString(),
            'check_in_time' => $log->check_in_time ? substr($log->check_in_time, 0, 5) : null,
            'check_out_time' => $log->check_out_time ? substr($log->check_out_time, 0, 5) : null,
            // Always derived from this session's own punch pair; 0 for open sessions.
            'duration_minutes' => $this->hours->sessionMinutes($log),
            'duration_hours' => round($this->hours->sessionMinutes($log) / 60, 2),
            'is_open' => $log->isOpen(),
            'source' => $log->source,
            'notes' => $log->notes,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}
