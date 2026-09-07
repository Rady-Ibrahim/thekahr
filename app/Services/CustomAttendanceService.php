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
    public const AUTO_CLOSED_NOTE = 'auto_closed';

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
                'source' => $source,
            ]);

            // Keep the main record's times readable by the dashboard immediately,
            // not only after the first check-out.
            $this->syncDashboardTimes($attendance->id);

            return [
                'success' => true,
                'message' => 'تم تسجيل الحضور بنجاح (جلسة رقم ' . ($attendance->logs()->count()) . ')',
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

            // Anchor the duration to the session's OWN log_date so overnight sessions
            // (and simulated check-outs) never inflate it by leaping across dates: a
            // 17:07→18:15 session must stay 68 minutes regardless of the wall clock.
            $durationMinutes = $log->isOpen()
                ? 0
                : max(0, (int) $log->checkInAt()->diffInMinutes($log->checkOutAt()));

            $log->update(['duration_minutes' => $durationMinutes]);

            $attendance = $this->recalculateDay($log->attendance_id);

            return [
                'success' => true,
                'message' => 'تم تسجيل الانصراف بنجاح',
                'session_duration_minutes' => $durationMinutes,
                'summary' => $this->buildSummary($attendance),
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
    public function autoCloseStaleSessions(?int $employeeId = null): int
    {
        $hours = (float) config('hr.working_hours.auto_close_after_hours', 20);
        $cutoff = now()->subHours($hours);

        $query = AttendanceLog::whereNull('check_out_time')
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

            $durationMinutes = max(0, (int) $checkInAt->diffInMinutes($autoCheckOut));

            $log->update([
                'check_out_time' => $autoCheckOut->toTimeString(),
                'duration_minutes' => $durationMinutes,
                'notes' => self::AUTO_CLOSED_NOTE,
            ]);

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
            $date = $data['date'] ?? today()->toDateString();

            if ($date > today()->toDateString()) {
                return ['success' => false, 'message' => 'لا يمكن تسجيل جلسة بتاريخ مستقبلي'];
            }

            if ($data['check_out_time'] === $data['check_in_time']) {
                return ['success' => false, 'message' => 'وقت الحضور والانصراف متطابقان'];
            }

            [$in, $out] = $this->resolveSessionRange($data['check_in_time'], $data['check_out_time']);
            $durationMinutes = max(0, (int) $in->diffInMinutes($out));

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
     * start time is treated as crossing midnight.
     */
    public function resolveSessionRange(string $checkIn, string $checkOut): array
    {
        $in = Carbon::createFromFormat('H:i', $checkIn);
        $out = Carbon::createFromFormat('H:i', $checkOut);

        if ($out->lessThan($in)) {
            $out->addDay();
        }

        return [$in, $out];
    }

    /**
     * Aggregate all sessions of the day: totals, hours status flag and shortfall deduction.
     */
    public function recalculateDay(?int $attendanceId): ?Attendance
    {
        $attendance = Attendance::with('logs')->find($attendanceId);
        if (!$attendance) {
            return null;
        }

        $employee = $attendance->employee;

        $totalMinutes = (int) $attendance->logs->sum('duration_minutes');
        $totalHours = round($totalMinutes / 60, 2);

        // Per-day override (set via manual entry) takes precedence over the employee default.
        $requiredHours = $employee?->isCustomAttendance()
            ? (float) ($attendance->required_hours ?: $employee->requiredDailyHours())
            : (float) config('hr.working_hours.daily_hours', 8);

        [$hoursStatus, $shortfallDeduction] = $this->resolveHoursStatus(
            $totalMinutes,
            $requiredHours,
            $employee
        );

        // Keep legacy columns in sync so existing reports/salary flows stay correct.
        $attendance->update([
            'total_worked_minutes' => $totalMinutes,
            'total_worked_hours' => $totalHours,
            'required_hours' => $requiredHours,
            'hours_status' => $hoursStatus,
            'actual_worked_hours' => $totalHours,
            'working_hours' => (int) floor($totalMinutes / 60),
            'deduction_amount' => $hoursStatus === Attendance::HOURS_SHORTFALL ? $shortfallDeduction : 0.0,
        ]);

        // Mirror the day's first check-in and latest closed check-out onto the main
        // attendance record so dashboard queries read them directly (no more "--").
        $this->syncDashboardTimes($attendanceId);

        return $attendance->fresh('logs');
    }

    /**
     * Persist the first session's check-in and the latest CLOSED session's
     * check-out onto the attendances row for the dashboard to read directly.
     */
    private function syncDashboardTimes(int $attendanceId): void
    {
        Attendance::whereKey($attendanceId)->update([
            'check_in_time'  => AttendanceLog::where('attendance_id', $attendanceId)->min('check_in_time'),
            'check_out_time' => AttendanceLog::where('attendance_id', $attendanceId)
                ->whereNotNull('check_out_time')
                ->max('check_out_time'),
        ]);
    }

    /**
     * @return array{0:string,1:float} [hours_status, shortfall_deduction_amount]
     */
    private function resolveHoursStatus(int $totalMinutes, float $requiredHours, ?Employee $employee): array
    {
        $requiredMinutes = (int) round($requiredHours * 60);

        if ($requiredMinutes > 0 && $totalMinutes < $requiredMinutes) {
            $hourlyRate = $employee ? $employee->hourlyRate() : 0.0;
            $shortfallMinutes = $requiredMinutes - $totalMinutes;

            // Proportional deduction based on base salary & hourly rate.
            return [Attendance::HOURS_SHORTFALL, round(($shortfallMinutes / 60) * $hourlyRate, 2)];
        }

        if ($requiredMinutes > 0 && $totalMinutes > $requiredMinutes + 30) {
            return [Attendance::HOURS_OVERTIME, 0.0];
        }

        return [Attendance::HOURS_FULFILLED, 0.0];
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

        return [
            'is_custom_attendance' => true,
            'employee_name'        => $employee->name,
            'daily_required_hours' => (float) $employee->requiredDailyHours(),
            'attendance_id' => $attendance?->id,
            'sessions' => $attendance?->logs->map(fn (AttendanceLog $log) => $this->formatSession($log))->values() ?? [],
            'open_session' => $openSession ? $this->formatSession($openSession) : null,
            'elapsed_open_session_minutes' => $openSession
                ? max(0, (int) $openSession->checkInAt()->diffInMinutes(now()))
                : 0,
            ...($attendance ? $this->buildSummary($attendance) : [
                'total_worked_minutes' => 0,
                'total_worked_hours' => 0.0,
                'remaining_minutes' => (int) round($employee->requiredDailyHours() * 60),
                'hours_status' => null,
                'sessions_count' => 0,
            ]),
        ];
    }

    private function buildSummary(Attendance $attendance): array
    {
        $totalMinutes = (int) ($attendance->total_worked_minutes ?? $attendance->logs->sum('duration_minutes'));
        $requiredMinutes = (int) round((float) ($attendance->required_hours ?? 0) * 60);

        return [
            'total_worked_minutes' => $totalMinutes,
            'total_worked_hours' => round($totalMinutes / 60, 2),
            'remaining_minutes' => $requiredMinutes > 0 ? max(0, $requiredMinutes - $totalMinutes) : 0,
            'overtime_minutes' => ($requiredMinutes > 0 && $totalMinutes > $requiredMinutes)
                ? $totalMinutes - $requiredMinutes
                : 0,
            'hours_status' => $attendance->hours_status,
            'sessions_count' => $attendance->logs->count(),
            'shortfall_deduction_amount' => (float) ($attendance->deduction_amount ?? 0),
        ];
    }

    private function formatSession(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'check_in_time' => $log->check_in_time ? substr($log->check_in_time, 0, 5) : null,
            'check_out_time' => $log->check_out_time ? substr($log->check_out_time, 0, 5) : null,
            'duration_minutes' => $log->duration_minutes,
            'is_open' => $log->isOpen(),
            'source' => $log->source,
            'notes' => $log->notes,
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}
