<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\HRSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for every attendance hours calculation.
 *
 * Rules enforced here (and nowhere else):
 *
 *  1. NO HARD-CODED FALLBACK HOURS. Hours are only ever derived from a real
 *     check-in/check-out pair. A record with no punch times and no sessions
 *     always yields 0 minutes / 0.00 hours - never a config default, never
 *     the auto-close window, never 20.00.
 *  2. PER-SESSION GRANULARITY. Every session is timed individually with
 *     Carbon::parse($checkOut)->diffInMinutes(Carbon::parse($checkIn)) and the
 *     day total is the plain sum of those per-session values. A session is
 *     never re-timed against another session's clock.
 *  3. STRICT DATE SCOPING. Aggregation is always restricted to
 *     whereDate('log_date', $date) so sessions belonging to other days can
 *     never leak into (and multiply) a day's total.
 *  4. STABLE OVERTIME. Overtime is a pure function of (a) the overtime
 *     switches, (b) the required shift hours, (c) the completed sessions of
 *     the day. It is 0 whenever the shift is not fulfilled, the day is
 *     absent/on-leave, or any session is still open.
 */
class AttendanceHoursService
{
    /**
     * Hard sanity ceiling for a single day. A human cannot work 104 hours, so
     * any total above this is the product of a data error and gets clamped.
     */
    public const MAX_DAILY_MINUTES = 1440; // 24h

    /**
     * The default required shift hours when nothing is configured per employee
     * and no shift is assigned. Used ONLY to decide how much overtime/shortfall
     * applies - it is never used as a worked-hours value.
     */
    public const DEFAULT_REQUIRED_HOURS = 8;

    // ──────────────────────────────────────────────────────────────────
    // Per-session duration
    // ──────────────────────────────────────────────────────────────────

    /**
     * Duration of a single session in minutes, derived strictly from that
     * session's own check-in / check-out pair.
     *
     * Returns 0 when:
     *  - there is no check-in time (nothing was actually punched),
     *  - the session is still open (no check-out yet),
     *  - the check-out precedes the check-in by more than one day,
     *  - the stored value exceeds MAX_DAILY_MINUTES (corrupt row).
     */
    public function sessionMinutes(AttendanceLog $log): int
    {
        return $this->minutesBetween(
            $log->log_date?->toDateString(),
            $log->check_in_time,
            $log->check_out_time
        );
    }

    /**
     * Canonical minute difference between two clock times of the same day.
     * A check-out clock earlier than the check-in clock is treated as an
     * overnight session and bumped to the next calendar day.
     */
    public function minutesBetween(?string $date, mixed $checkIn, mixed $checkOut): int
    {
        if ($checkIn === null || $checkIn === '' || $checkOut === null || $checkOut === '') {
            return 0;
        }

        $checkInTime = $this->normalizeTime($checkIn);
        $checkOutTime = $this->normalizeTime($checkOut);

        if ($checkInTime === null || $checkOutTime === null) {
            return 0;
        }

        $day = $date ?: today()->toDateString();

        $start = Carbon::parse($day . ' ' . $checkInTime);
        $end   = Carbon::parse($day . ' ' . $checkOutTime);

        // Overnight session: the check-out happened on the following day.
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $minutes = (int) $end->diffInMinutes($start);

        return ($minutes < 0 || $minutes > self::MAX_DAILY_MINUTES) ? 0 : $minutes;
    }

    /**
     * Worked hours (decimal, 2dp) for a check-in/check-out pair. Always 0.00
     * when either side is missing - this is the guard against phantom hours.
     */
    public function hoursBetween(?string $date, mixed $checkIn, mixed $checkOut): float
    {
        return round($this->minutesBetween($date, $checkIn, $checkOut) / 60, 2);
    }

    // ──────────────────────────────────────────────────────────────────
    // Strict day scoping
    // ──────────────────────────────────────────────────────────────────

    /**
     * Sessions of the given attendance record, restricted to ONE calendar day
     * via whereDate('log_date', $date). This is the only supported way to read
     * a day's sessions.
     */
    public function dayLogsQuery(Attendance $attendance, ?string $date = null): Builder
    {
        $date = $this->resolveDate($attendance, $date);

        return AttendanceLog::where('attendance_id', $attendance->id)
            ->whereDate('log_date', $date)
            ->orderBy('check_in_time');
    }

    /**
     * @return Collection<int, AttendanceLog>
     */
    public function dayLogs(Attendance $attendance, ?string $date = null): Collection
    {
        return $this->dayLogsQuery($attendance, $date)->get();
    }

    /**
     * The calendar day an attendance record represents.
     */
    public function resolveDate(Attendance $attendance, ?string $date = null): string
    {
        if ($date !== null && $date !== '') {
            return Carbon::parse($date)->toDateString();
        }

        $recordDate = $attendance->attendance_date;

        if ($recordDate instanceof Carbon) {
            return $recordDate->toDateString();
        }

        return Carbon::parse((string) $recordDate)->toDateString();
    }

    // ──────────────────────────────────────────────────────────────────
    // Day aggregation
    // ──────────────────────────────────────────────────────────────────

    /**
     * Aggregate ONE day of an attendance record.
     *
     * Every session is timed on its own and summed. Open sessions contribute 0
     * minutes (and are reported separately so overtime can be suppressed), and
     * sessions from other dates are excluded by the whereDate filter.
     *
     * @return array{
     *     date: string,
     *     total_minutes: int,
     *     total_hours: float,
     *     sessions_count: int,
     *     completed_sessions_count: int,
     *     open_sessions_count: int,
     *     has_sessions: bool
     * }
     */
    public function aggregate(Attendance $attendance, ?string $date = null): array
    {
        $date = $this->resolveDate($attendance, $date);
        $logs = $this->dayLogs($attendance, $date);

        $totalMinutes = 0;
        $completed = 0;
        $open = 0;

        foreach ($logs as $log) {
            if ($log->isOpen()) {
                $open++;
                continue;
            }

            $completed++;
            $totalMinutes += $this->sessionMinutes($log);
        }

        // Defensive clamp: a day can never exceed 24h of counted work.
        $totalMinutes = min($totalMinutes, self::MAX_DAILY_MINUTES);

        return [
            'date'                     => $date,
            'total_minutes'            => $totalMinutes,
            'total_hours'              => round($totalMinutes / 60, 2),
            'sessions_count'           => $logs->count(),
            'completed_sessions_count' => $completed,
            'open_sessions_count'      => $open,
            'has_sessions'             => $logs->isNotEmpty(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Mode-aware day totals
    // ──────────────────────────────────────────────────────────────────

    /**
     * Whether the record's hours are defined by attendance_logs rows
     * (flexible/custom attendance) instead of the single punch pair.
     */
    public function usesSessions(Attendance $attendance, ?Employee $employee = null): bool
    {
        $employee ??= $attendance->employee;

        if ($employee && $employee->isCustomAttendance()) {
            return true;
        }

        return $attendance->logs()->exists();
    }

    /**
     * Day totals for ANY attendance mode.
     *
     * Flexible attendance is always defined by its per-day sessions; a
     * shift-based record is defined by its own check-in/check-out pair. Both
     * end up as minutes/hours, so reporting code never has to branch - and in
     * particular a legitimate shift-based day is never reported as 0.00 hours
     * just because it has no log rows.
     *
     * @return array<string,int|float|bool|string>
     */
    public function dayTotals(Attendance $attendance, ?Employee $employee = null, ?string $date = null): array
    {
        $employee ??= $attendance->employee;

        if ($this->usesSessions($attendance, $employee)) {
            return $this->aggregate($attendance, $date);
        }

        $date    = $this->resolveDate($attendance, $date);
        $minutes = $this->minutesBetween($date, $attendance->check_in_time, $attendance->check_out_time);

        $hasIn  = $attendance->check_in_time !== null && $attendance->check_in_time !== '';
        $hasOut = $attendance->check_out_time !== null && $attendance->check_out_time !== '';

        return [
            'date'                     => $date,
            'total_minutes'            => $minutes,
            'total_hours'              => round($minutes / 60, 2),
            'sessions_count'           => 0,
            // A complete punch pair counts as one completed "block".
            'completed_sessions_count' => $hasIn && $hasOut ? 1 : 0,
            'open_sessions_count'      => $hasIn && !$hasOut ? 1 : 0,
            'has_sessions'             => false,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Required hours
    // ──────────────────────────────────────────────────────────────────

    /**
     * The shift hours the employee is required to fulfil on this day.
     * A per-day override (attendances.required_hours) beats the employee
     * default, which beats the system default.
     */
    public function requiredHoursFor(Attendance $attendance, ?Employee $employee = null): float
    {
        $employee ??= $attendance->employee;

        $override = $attendance->required_hours;
        if ($override !== null && (float) $override > 0) {
            return round((float) $override, 2);
        }

        if ($employee && $employee->isCustomAttendance()) {
            return round($employee->requiredDailyHours(), 2);
        }

        return round((float) config('hr.working_hours.daily_hours', self::DEFAULT_REQUIRED_HOURS), 2);
    }

    public function requiredMinutesFor(Attendance $attendance, ?Employee $employee = null): int
    {
        return (int) round($this->requiredHoursFor($attendance, $employee) * 60);
    }

    // ──────────────────────────────────────────────────────────────────
    // Overtime
    // ──────────────────────────────────────────────────────────────────

    /**
     * (a) Is overtime enabled for this employee (and the system)?
     * Falls back to enabled when the column is not present yet, so the switch
     * never silently turns overtime off.
     */
    public function overtimeEnabled(?Employee $employee = null): bool
    {
        $global = HRSetting::get(HRSetting::OVERTIME_ENABLED, true);

        if ($global === false) {
            return false;
        }

        if ($employee === null) {
            return true;
        }

        return $employee->overtime_enabled !== false;
    }

    /**
     * The totals that may actually be REPORTED for a day.
     *
     * Same as dayTotals() but with the day status applied first, so an
     * absent / on-leave / excused day reports 0.00 hours even if stale session
     * rows are still attached to the record, and a day with no completed
     * session reports 0.00 hours. Every read path (APIs, summaries, reports)
     * MUST use this instead of the raw aggregate so the response can never
     * contradict what was persisted on the attendances row.
     *
     * @return array<string,int|float|bool|string>
     */
    public function effectiveTotals(Attendance $attendance, ?Employee $employee = null, ?string $date = null): array
    {
        [, , , $effective] = $this->resolveDayStatus($attendance, $employee, $date);

        return $effective;
    }

    /**
     * Unified overtime resolution. Overtime is granted ONLY when every
     * condition holds:
     *
     *  a) overtime_enabled (system + employee) is on,
     *  b) the day is not absent / on-leave / excused,
     *  c) every session of the day is completed (no forgotten check-out),
     *  d) completed session minutes exceed the required shift minutes.
     *
     * @return array{
     *     enabled: bool,
     *     minutes: int,
     *     hours: float,
     *     required_minutes: int,
     *     completed_minutes: int
     * }
     */
    public function resolveOvertime(Attendance $attendance, ?Employee $employee = null, ?string $date = null): array
    {
        $employee ??= $attendance->employee;
        $totals    = $this->dayTotals($attendance, $employee, $date);
        $required  = $this->requiredMinutesFor($attendance, $employee);
        $enabled   = $this->overtimeEnabled($employee);

        $eligible = $enabled
            && !in_array($attendance->status, ['absent', 'on_leave', 'excused'], true)
            && $totals['completed_sessions_count'] > 0
            && $totals['open_sessions_count'] === 0
            && $required > 0
            && $totals['total_minutes'] > $required;

        $minutes = $eligible ? $totals['total_minutes'] - $required : 0;

        return [
            'enabled'          => $enabled,
            'minutes'          => $minutes,
            'hours'            => round($minutes / 60, 2),
            'required_minutes' => $required,
            'completed_minutes'=> $totals['total_minutes'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Unified hours status
    // ──────────────────────────────────────────────────────────────────

    /**
     * Single, deterministic classification of a day's hours.
     *
     * Precedence:
     *  1. absent / on_leave / excused  -> zero hours, zero overtime, no
     *     shortfall deduction (the absence is already penalised by the absence
     *     deduction, so we must not charge it twice);
     *  2. no real completed session    -> 0.00 hours, nothing to classify;
     *  3. shortfall                    -> required shift hours not fulfilled;
     *  4. overtime                    -> enabled AND shift fulfilled AND every
     *     session closed AND minutes above the required shift hours;
     *  5. fulfilled                   -> anything else.
     *
     * @return array{0:?string,1:float,2:int,3:array} [status, deduction, overtime_minutes, totals]
     */
    public function resolveDayStatus(Attendance $attendance, ?Employee $employee = null, ?string $date = null): array
    {
        $employee = $employee ?? $attendance->employee;
        $totals   = $this->dayTotals($attendance, $employee, $date);
        $overtime = $this->resolveOvertime($attendance, $employee, $date);

        // Leave / excuse / absence: no counted hours at all, so no leftover
        // session can manufacture hours or overtime for a day the employee
        // was not there.
        if (in_array($attendance->status, ['absent', 'on_leave', 'excused'], true)) {
            $totals['total_minutes'] = 0;
            $totals['total_hours']   = 0.0;

            return [null, 0.0, 0, $totals];
        }

        $requiredMinutes = $overtime['required_minutes'];

        // No real check-in/check-out at all => 0.00 hours, never a default and
        // never a deduction, because nothing was worked to measure against.
        if ($requiredMinutes <= 0 || $totals['completed_sessions_count'] === 0) {
            $totals['total_minutes'] = 0;
            $totals['total_hours']   = 0.0;

            return [Attendance::HOURS_FULFILLED, 0.0, 0, $totals];
        }

        if ($totals['total_minutes'] < $requiredMinutes) {
            return [
                Attendance::HOURS_SHORTFALL,
                $this->shortfallDeduction($requiredMinutes, $totals['total_minutes'], $employee),
                0,
                $totals,
            ];
        }

        if ($overtime['minutes'] > 0) {
            return [Attendance::HOURS_OVERTIME, 0.0, $overtime['minutes'], $totals];
        }

        return [Attendance::HOURS_FULFILLED, 0.0, 0, $totals];
    }

    /**
     * Proportional shortfall deduction based on the employee's hourly rate.
     */
    public function shortfallDeduction(int $requiredMinutes, int $workedMinutes, ?Employee $employee): float
    {
        if ($requiredMinutes <= 0) {
            return 0.0;
        }

        $shortfallMinutes = $requiredMinutes - $workedMinutes;

        if ($shortfallMinutes <= 0) {
            return 0.0;
        }

        $hourlyRate = $employee ? $employee->hourlyRate() : 0.0;

        return round(($shortfallMinutes / 60) * $hourlyRate, 2);
    }

    /**
     * Public read-only summary of a day, safe for API responses.
     */
    public function summary(Attendance $attendance, ?Employee $employee = null): array
    {
        $employee = $employee ?? $attendance->employee;
        $totals   = $this->effectiveTotals($attendance, $employee);
        $overtime = $this->resolveOvertime($attendance, $employee);

        $requiredMinutes = $overtime['required_minutes'];

        return [
            'total_worked_minutes'        => $totals['total_minutes'],
            'total_worked_hours'          => $totals['total_hours'],
            'remaining_minutes'           => $requiredMinutes > 0
                ? max(0, $requiredMinutes - $totals['total_minutes'])
                : 0,
            'required_minutes'            => $requiredMinutes,
            'required_hours'              => round($requiredMinutes / 60, 2),
            'overtime_enabled'            => $overtime['enabled'],
            'overtime_minutes'            => $overtime['minutes'],
            'overtime_hours'              => $overtime['hours'],
            'hours_status'                => $attendance->hours_status,
            'sessions_count'              => $totals['sessions_count'],
            'completed_sessions_count'    => $totals['completed_sessions_count'],
            'open_sessions_count'         => $totals['open_sessions_count'],
            'shortfall_deduction_amount'  => (float) ($attendance->deduction_amount ?? 0),
        ];
    }

    /**
     * Accepts "H:i", "H:i:s" or a full datetime string and returns "H:i:s".
     */
    private function normalizeTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Full datetime: keep only the time part.
        if (preg_match('/\d{4}-\d{2}-\d{2}[T ]/', $value)) {
            try {
                return Carbon::parse($value)->format('H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }

        return null;
    }
}
