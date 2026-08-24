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
    /**
     * Start a new work session (check-in) for a custom-attendance employee.
     * Multiple completed sessions per day are allowed; only one open session at a time.
     */
    public function startSession(Employee $employee, array $data = [], string $source = 'mobile'): array
    {
        return DB::transaction(function () use ($employee, $data, $source) {
            if ($this->openSession($employee)) {
                return [
                    'success' => false,
                    'message' => 'لديك جلسة عمل مفتوحة حالياً، يجب تسجيل الانصراف أولاً',
                ];
            }

            $today = today()->toDateString();
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
                'check_in_time' => now()->toTimeString(),
                'check_in_latitude' => $data['latitude'] ?? null,
                'check_in_longitude' => $data['longitude'] ?? null,
                'check_in_photo' => $checkInPhoto,
                'source' => $source,
            ]);

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
    public function endSession(AttendanceLog $log, array $data = []): array
    {
        return DB::transaction(function () use ($log, $data) {
            if (!$log->isOpen()) {
                return ['success' => false, 'message' => 'تم تسجيل الانصراف لهذه الجلسة مسبقاً'];
            }

            $checkOutPhoto = isset($data['photo']) && $data['photo'] instanceof UploadedFile
                ? $data['photo']->store('attendance/checkout', 'public')
                : null;

            $now = now();

            // Handle sessions spanning midnight.
            if ($now->lessThan($log->checkInAt())) {
                $now->addDay();
            }

            $durationMinutes = max(0, (int) $log->checkInAt()->diffInMinutes($now));

            $log->update([
                'check_out_time' => $now->toTimeString(),
                'check_out_latitude' => $data['latitude'] ?? null,
                'check_out_longitude' => $data['longitude'] ?? null,
                'check_out_photo' => $checkOutPhoto,
                'duration_minutes' => $durationMinutes,
            ]);

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
            ->whereDate('log_date', today())
            ->whereNull('check_out_time')
            ->first();
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

        return $attendance->fresh('logs');
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
