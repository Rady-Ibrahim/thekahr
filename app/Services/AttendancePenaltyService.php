<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class AttendancePenaltyService
{
    public const AUTO_CLOSE_GRACE_HOURS = 4;

    /**
     * Dynamically resolve the shift that matches the employee's check-in time.
     *
     * Priority order:
     *  1. An active assignment bound to the employee whose time window contains
     *     the given check-in time (supports shift rotations per employee).
     *  2. An active assignment bound to the employee (any shift, fallback).
     *  3. Any active shift in the system whose time window contains the check-in
     *     time (fully dynamic, supports cross-employee handovers/rotations).
     *  4. The first active shift as a default.
     *
     * @param Carbon|null $checkInTime When provided, the shift window is matched
     *                                 against this moment instead of the date bounds.
     */
    public function resolveShift(Employee $employee, Carbon $date, ?Carbon $checkInTime = null): ?Shift
    {
        $checkMoment = $checkInTime ?? Carbon::parse('23:59:59')->setDateFrom($date);

        $assignments = EmployeeShift::with('shift')
            ->where('employee_id', $employee->id)
            ->active($date)
            ->get();

        // 1) Employee's assigned shift matching the check-in moment.
        foreach ($assignments as $assignment) {
            if ($this->timeInShiftWindow($assignment->shift, $checkMoment)) {
                return $assignment->shift;
            }
        }

        // 2) First assigned shift regardless of window.
        if ($assignment = $assignments->first()) {
            return $assignment->shift;
        }

        // 3) Any active shift whose window contains the check-in moment.
        $activeShifts = Shift::with(['lateRules', 'earlyExitRules'])
            ->where('is_active', true)
            ->get();

        foreach ($activeShifts as $shift) {
            if ($this->timeInShiftWindow($shift, $checkMoment)) {
                return $shift;
            }
        }

        // 4) Default active shift.
        if ($defaultShift = $activeShifts->first()) {
            return $defaultShift;
        }

        return null;
    }

    /**
     * Whether a moment falls within a shift's time window, handling overnight
     * (crossing-midnight) shifts such as 17:00 -> 05:00.
     */
    public function timeInShiftWindow(Shift $shift, Carbon $moment): bool
    {
        if ($shift->start_time === null || $shift->end_time === null) {
            return false;
        }

        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);
        $time = $moment->copy()->startOfDay()->addMinutes($moment->hour * 60 + $moment->minute);

        if ($end->greaterThan($start)) {
            // Same-day shift (e.g. 08:00 -> 17:00).
            return $time->between($start, $end, true);
        }

        // Overnight shift (e.g. 17:00 -> 05:00): window spans midnight.
        return $time->gte($start) || $time->lte($end);
    }

    /**
     * The scheduled end of a shift for a given attendance date, expressed as a
     * Carbon datetime. For overnight shifts the end lands on the next calendar day.
     */
    public function shiftEndAt(Shift $shift, Carbon $date): ?Carbon
    {
        if ($shift->end_time === null) {
            return null;
        }

        $start = Carbon::parse($shift->start_time);
        $end = Carbon::parse($shift->end_time);

        $scheduledEnd = Carbon::parse($date->toDateString() . ' ' . $shift->end_time);

        // Overnight shift that ends before it starts => ends next day.
        if ($end->lessThan($start)) {
            $scheduledEnd->addDay();
        }

        return $scheduledEnd;
    }

    /**
     * The grace cutoff time beyond which an open session should be auto-closed:
     * shift end + the configured forgotten-checkout grace period (default 4 hours).
     */
    public function autoCloseCutoff(Shift $shift, Carbon $date): ?Carbon
    {
        $scheduledEnd = $this->shiftEndAt($shift, $date);

        if ($scheduledEnd === null) {
            return null;
        }

        $graceHours = (float) Config::get('hr.working_hours.auto_close_grace_hours', self::AUTO_CLOSE_GRACE_HOURS);

        return $scheduledEnd->copy()->addHours($graceHours);
    }

    /**
     * Whether an open attendance record is stale and its session should be
     * auto-closed. A record is stale when "now" is past the shift's scheduled
     * end time by more than the forgotten-checkout grace period (4h by default).
     * Falls back to the legacy check-in + N hours threshold for open-ended shifts.
     */
    public function isOpenRecordStale(Attendance $attendance, ?Carbon $now = null): bool
    {
        $now = $now ?? now();
        $date = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date->copy()
            : Carbon::parse($attendance->attendance_date);

        $shift = $attendance->shift ?? $attendance->employee?->currentShift();

        $cutoff = $shift ? $this->autoCloseCutoff($shift, $date) : null;

        // Preferred: shift-end + grace.
        if ($cutoff !== null) {
            return $now->greaterThan($cutoff);
        }

        // Open-ended shift (no end_time): fall back to check-in + legacy hours.
        $checkIn = $attendance->check_in_time instanceof Carbon
            ? $attendance->check_in_time
            : Carbon::parse($date->toDateString() . ' ' . $attendance->check_in_time);

        $hours = (float) Config::get('hr.working_hours.auto_close_after_hours', 20);

        return $now->greaterThan($checkIn->copy()->addHours($hours));
    }

    /**
     * The check-out time to stamp on an auto-closed forgotten session:
     * the shift's official scheduled end time (or check-in + N hours fallback).
     */
    public function autoCloseCheckOutTime(Attendance $attendance): string
    {
        $date = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date->copy()
            : Carbon::parse($attendance->attendance_date);

        $shift = $attendance->shift ?? $attendance->employee?->currentShift();

        if ($shift && ($scheduledEnd = $this->shiftEndAt($shift, $date)) !== null) {
            return $scheduledEnd->toTimeString();
        }

        $hours = (float) Config::get('hr.working_hours.auto_close_after_hours', 20);

        $checkIn = $attendance->check_in_time instanceof Carbon
            ? $attendance->check_in_time
            : Carbon::parse($date->toDateString() . ' ' . $attendance->check_in_time);

        return $checkIn->copy()->addHours($hours)->toTimeString();
    }

    /**
     * Auto-close all stale open attendance records. Optionally restricted to a
     * single employee. Returns the ids of records that were closed.
     *
     * @return array<int> closed attendance ids
     */
    public function autoCloseForgotten(?int $employeeId = null, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        $query = Attendance::whereNotNull('check_in_time')->whereNull('check_out_time');

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        $closed = [];

        foreach ($query->get() as $attendance) {
            if (!$this->isOpenRecordStale($attendance, $now)) {
                continue;
            }

            $attendance->update([
                'check_out_time' => $this->autoCloseCheckOutTime($attendance),
            ]);

            $this->processAttendance($attendance->fresh());

            $closed[] = (int) $attendance->id;
        }

        return $closed;
    }

    public function calculateLatePenalty(Shift $shift, Carbon $checkInTime, Carbon $date, ?Employee $employee = null): array
    {
        $scheduledStart = Carbon::parse($date->toDateString() . ' ' . $shift->start_time);
        $actualDelayMinutes = (int) $scheduledStart->diffInMinutes($checkInTime, false);
        $effectiveDelay = max(0, $actualDelayMinutes - $shift->grace_period_minutes);

        if ($effectiveDelay <= 0) {
            return [
                'late_minutes' => 0,
                'effective_delay' => 0,
                'deduction_type' => null,
                'deduction_amount' => 0.0,
            ];
        }

        $rules = $shift->lateRules()->orderBy('min_delay_minutes')->get();
        $matchedRule = null;

        foreach ($rules as $rule) {
            if ($effectiveDelay >= $rule->min_delay_minutes) {
                if ($rule->max_delay_minutes === null || $effectiveDelay <= $rule->max_delay_minutes) {
                    $matchedRule = $rule;
                    break;
                }
            }
        }

        if (!$matchedRule) {
            return [
                'late_minutes' => $actualDelayMinutes,
                'effective_delay' => $effectiveDelay,
                'deduction_type' => 'minutes',
                'deduction_amount' => 0.0,
            ];
        }

        $amount = $this->resolveAmount($matchedRule->deduction_type, $matchedRule->deduction_value, (float) ($employee?->base_salary ?? 0), $effectiveDelay);

        return [
            'late_minutes' => $actualDelayMinutes,
            'effective_delay' => $effectiveDelay,
            'deduction_type' => $matchedRule->deduction_type,
            'deduction_amount' => $amount,
        ];
    }

    public function calculateEarlyExitPenalty(Shift $shift, Carbon $checkInTime, Carbon $checkOutTime, Carbon $date, ?Employee $employee = null): array
    {
        if ($shift->end_time === null) {
            return [
                'early_exit_minutes' => 0,
                'actual_worked_hours' => round((int) $checkInTime->diffInMinutes($checkOutTime) / 60, 2),
                'deduction_type' => null,
                'deduction_amount' => 0.0,
            ];
        }

        $expectedEnd = Carbon::parse($date->toDateString() . ' ' . $shift->end_time);
        $workedMinutes = (int) $checkInTime->diffInMinutes($checkOutTime);
        $earlyMinutes = max(0, (int) $checkOutTime->diffInMinutes($expectedEnd, false));

        $actualWorkedHours = round($workedMinutes / 60, 2);

        if ($earlyMinutes <= 0) {
            return [
                'early_exit_minutes' => 0,
                'actual_worked_hours' => $actualWorkedHours,
                'deduction_type' => null,
                'deduction_amount' => 0.0,
            ];
        }

        $rules = $shift->earlyExitRules()->orderBy('min_early_minutes')->get();
        $matchedRule = null;

        foreach ($rules as $rule) {
            if ($earlyMinutes >= $rule->min_early_minutes) {
                if ($rule->max_early_minutes === null || $earlyMinutes <= $rule->max_early_minutes) {
                    $matchedRule = $rule;
                    break;
                }
            }
        }

        if (!$matchedRule) {
            return [
                'early_exit_minutes' => $earlyMinutes,
                'actual_worked_hours' => $actualWorkedHours,
                'deduction_type' => 'minutes',
                'deduction_amount' => 0.0,
            ];
        }

        $amount = $this->resolveAmount($matchedRule->deduction_type, $matchedRule->deduction_value, (float) ($employee?->base_salary ?? 0), $earlyMinutes);

        return [
            'early_exit_minutes' => $earlyMinutes,
            'actual_worked_hours' => $actualWorkedHours,
            'deduction_type' => $matchedRule->deduction_type,
            'deduction_amount' => $amount,
        ];
    }

    public function calculateLateFromConfig(Carbon $checkInTime, Carbon $date): array
    {
        $start = Carbon::parse($date->toDateString() . ' ' . Config::get('hr.working_hours.check_in_time', '08:00'));
        $lateMinutes = max(0, (int) $start->diffInMinutes($checkInTime, false));

        return [
            'late_minutes' => $lateMinutes,
            'deduction_type' => $lateMinutes > 0 ? 'minutes' : null,
            'deduction_amount' => 0.0,
        ];
    }

    public function calculateEarlyExitFromConfig(Carbon $checkInTime, Carbon $checkOutTime, Carbon $date): array
    {
        $end = Carbon::parse($date->toDateString() . ' ' . Config::get('hr.working_hours.check_out_time', '17:00'));

        $workedMinutes = (int) $checkInTime->diffInMinutes($checkOutTime);
        $earlyMinutes = max(0, (int) $checkOutTime->diffInMinutes($end, false));

        return [
            'early_exit_minutes' => $earlyMinutes,
            'actual_worked_hours' => round($workedMinutes / 60, 2),
            'deduction_type' => $earlyMinutes > 0 ? 'minutes' : null,
            'deduction_amount' => 0.0,
        ];
    }

    public function processAttendance(Attendance $attendance): Attendance
    {
        $date = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date
            : Carbon::parse($attendance->attendance_date);

        $employee = $attendance->employee;

        if (!$employee) {
            return $attendance;
        }

        $shift = $attendance->shift ?? $this->resolveShift($employee, $date);

        if ($shift) {
            $attendance->shift_id = $shift->id;
        }

        $lateResult = ['late_minutes' => 0, 'deduction_type' => null, 'deduction_amount' => 0.0];
        $earlyResult = ['early_exit_minutes' => 0, 'actual_worked_hours' => 0.0, 'deduction_type' => null, 'deduction_amount' => 0.0];

        if ($attendance->check_in_time) {
            $checkIn = $attendance->check_in_time instanceof Carbon
                ? $attendance->check_in_time
                : Carbon::parse($date->toDateString() . ' ' . $attendance->check_in_time);

            $lateResult = $shift
                ? $this->calculateLatePenalty($shift, $checkIn, $date, $employee)
                : $this->calculateLateFromConfig($checkIn, $date);

            if ($attendance->check_out_time) {
                $checkOut = $attendance->check_out_time instanceof Carbon
                    ? $attendance->check_out_time
                    : Carbon::parse($date->toDateString() . ' ' . $attendance->check_out_time);

                // A night shift may cross midnight: if the clock time of check-out is
                // earlier than check-in, the check-out took place on the next day.
                if ($attendance->check_in_time instanceof Carbon === false && $checkOut->lessThan($checkIn)) {
                    $checkOut->addDay();
                }

                $earlyResult = $shift
                    ? $this->calculateEarlyExitPenalty($shift, $checkIn, $checkOut, $date, $employee)
                    : $this->calculateEarlyExitFromConfig($checkIn, $checkOut, $date);
            }
        }

        $attendance->late_minutes = $lateResult['late_minutes'];
        $attendance->applied_late_deduction_type = $lateResult['deduction_type'];

        $attendance->early_exit_minutes = $earlyResult['early_exit_minutes'];
        $attendance->actual_worked_hours = $earlyResult['actual_worked_hours'];
        $attendance->applied_early_deduction_type = $earlyResult['deduction_type'];

        $totalDeduction = ($lateResult['deduction_amount'] ?? 0) + ($earlyResult['deduction_amount'] ?? 0);
        $attendance->deduction_amount = $totalDeduction;

        $attendance->save();

        return $attendance->fresh();
    }

    public function calculateAttendanceDeductionForSalary(Employee $employee, int $month, int $year, float $baseSalary): array
    {
        $workingDays = $this->getWorkingDaysInMonth($month, $year);

        if ($workingDays === 0) {
            return ['amount' => 0, 'label' => 'خصم تأخير/غياب', 'absent' => 0, 'half_days' => 0, 'late_minutes' => 0];
        }

        $dailyRate = $baseSalary / $workingDays;

        $records = Attendance::where('employee_id', $employee->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->get();

        $absentDays = $records->where('status', 'absent')->count();
        $absentDeduction = $absentDays * $dailyRate;

        $penaltyRecords = $records->where('status', '!=', 'absent');
        $penaltyDeduction = (float) $penaltyRecords->sum('deduction_amount');
        $lateMinutes = (int) $penaltyRecords->sum('late_minutes') + (int) $penaltyRecords->sum('early_exit_minutes');

        // Custom flexible attendance: shortfall vs daily required hours is already
        // stored per-day in deduction_amount by CustomAttendanceService::recalculateDay().
        $shortfallDays = (int) $records->where('hours_status', 'shortfall')->count();
        $shortfallAmount = round((float) $records->where('hours_status', 'shortfall')->sum('deduction_amount'), 2);

        $totalAmount = round($absentDeduction + $penaltyDeduction, 2);

        return [
            'amount' => $totalAmount,
            'label' => sprintf(
                'خصم حضور: %d غياب، %d دقيقة تأخير/انصراف مبكر%s',
                $absentDays,
                $lateMinutes,
                $shortfallDays > 0 ? sprintf('، نقص ساعات في %d يوم (%.2f)', $shortfallDays, $shortfallAmount) : ''
            ),
            'absent' => $absentDays,
            'half_days' => 0,
            'late_minutes' => $lateMinutes,
            'custom_attendance_shortfall_days' => $shortfallDays,
            'custom_attendance_shortfall_amount' => $shortfallAmount,
        ];
    }

    private function resolveAmount(string $deductionType, ?float $deductionValue, float $baseSalary, int $minutes = 0): float
    {
        return match ($deductionType) {
            'minutes' => (float) ($deductionValue ?? 0) * $minutes,
            'quarter_day', 'half_day', 'full_day' => (float) ($deductionValue ?? 0),
            'percentage' => $baseSalary * ($deductionValue ?? 0) / 100,
            'fixed_amount' => $deductionValue ?? 0,
            default => 0.0,
        };
    }

    public function calculateRecordDeduction(Attendance $attendance): array
    {
        $amount = (float) ($attendance->deduction_amount ?? 0);
        $lateMinutes = (int) ($attendance->late_minutes ?? 0);
        $earlyMinutes = (int) ($attendance->early_exit_minutes ?? 0);

        $label = [];
        if ($lateMinutes > 0) {
            $label[] = "{$lateMinutes} دقيقة تأخير";
        }
        if ($earlyMinutes > 0) {
            $label[] = "{$earlyMinutes} دقيقة انصراف مبكر";
        }
        if (empty($label)) {
            $label[] = $amount > 0 ? 'خصم تأخير/انصراف مبكر' : '-';
        }

        return [
            'amount' => round($amount, 2),
            'label' => implode('، ', $label),
        ];
    }


    private function getWorkingDaysInMonth(int $month, int $year): int
    {
        $start = Carbon::createFromDate($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $count = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (!$day->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }
}
