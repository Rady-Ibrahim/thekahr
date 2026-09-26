<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\HRSetting;
use App\Services\AttendanceHoursService;
use App\Services\CustomAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Guards the three attendance-hours defects:
 *
 *  1. A "present" record with no punch times / no sessions must always be
 *     0.00 hours - never a hardcoded fallback such as 20.00.
 *  2. Multi-session days must be timed per session and strictly scoped to the
 *     day, so totals can neither double-count nor absorb other days' sessions.
 *  3. Overtime must be stable: only after the required shift hours are
 *     fulfilled, only when overtime is enabled, and 0.00 for absent days or
 *     days with an unfinished session.
 */
class AttendanceHoursCalculationTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(bool $custom = true, float $requiredHours = 8.0): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code'        => 'AH' . self::$seq . '_' . uniqid(),
            'name'                 => 'Hours QA ' . self::$seq,
            'phone'                => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -3),
            'position'             => 'Tester',
            'department'           => 'QA',
            'joining_date'         => now()->subMonth()->toDateString(),
            'base_salary'          => 6000,
            'status'               => 'active',
            'is_custom_attendance' => $custom,
            'daily_required_hours' => $custom ? $requiredHours : null,
            'overtime_enabled'     => true,
        ]);
    }

    private function makeRecord(Employee $employee, string $date, string $status = 'present', ?float $requiredHours = 8.0): Attendance
    {
        return Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => $date,
            'status'          => $status,
            'required_hours'  => $requiredHours,
        ]);
    }

    private function addSession(
        Attendance $attendance,
        ?string $checkIn,
        ?string $checkOut,
        ?string $logDate = null,
        array $extra = []
    ): AttendanceLog {
        $logDate = $logDate ?: $attendance->attendance_date->toDateString();

        $log = AttendanceLog::create(array_merge([
            'employee_id'   => $attendance->employee_id,
            'attendance_id' => $attendance->id,
            'log_date'      => $logDate,
            'check_in_time' => $checkIn,
            'check_out_time'=> $checkOut,
            'source'        => 'mobile',
            'duration_minutes' => 0,
        ], $extra));

        return $log;
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 1: no hardcoded fallback hours
    // ──────────────────────────────────────────────────────────────────

    public function test_present_record_without_any_punch_times_is_zero_hours(): void
    {
        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        $this->assertNull($record->check_in_time);
        $this->assertNull($record->check_out_time);

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(0, (int) $updated->total_worked_minutes, 'No punch times must mean zero minutes');
        $this->assertEqualsWithDelta(0.0, (float) $updated->total_worked_hours, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $updated->actual_worked_hours, 0.001);
        $this->assertSame(0, (int) $updated->working_hours);
        $this->assertSame(0, (int) $updated->overtime_minutes);
        $this->assertNotEquals(20.0, (float) $updated->total_worked_hours, 'Must never fall back to 20.00 hours');
    }

    public function test_present_record_with_an_open_session_is_zero_hours(): void
    {
        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        // Checked in, never checked out: nothing was completed yet.
        $this->addSession($record, '09:00:00', null);

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(0, (int) $updated->total_worked_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->total_worked_hours, 0.001);
        $this->assertSame(0, (int) $updated->overtime_minutes);
    }

    public function test_shift_based_present_record_without_punch_times_is_zero_hours(): void
    {
        $employee = $this->makeEmployee(custom: false);
        $record = Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2026-09-10',
            'status'          => 'present',
        ]);

        $processed = app(\App\Services\AttendancePenaltyService::class)->processAttendance($record);

        $this->assertNull($processed->check_in_time);
        $this->assertNull($processed->check_out_time);
        $this->assertEqualsWithDelta(0.0, (float) $processed->actual_worked_hours, 0.001);
        $this->assertSame(0, (int) $processed->total_worked_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $processed->total_worked_hours, 0.001);
        $this->assertNotEquals(20.0, (float) $processed->actual_worked_hours);
    }

    public function test_today_summary_for_present_day_without_sessions_reports_zero(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $employee = $this->makeEmployee();
        $this->makeRecord($employee, '2026-09-10');

        $summary = app(CustomAttendanceService::class)->todaySummary($employee);

        $this->assertSame(0, $summary['total_worked_minutes']);
        $this->assertEqualsWithDelta(0.0, $summary['total_worked_hours'], 0.001);
        $this->assertSame(0, $summary['overtime_minutes']);

        Carbon::setTestNow(null);
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 2: per-session accuracy + strict date scoping
    // ──────────────────────────────────────────────────────────────────

    public function test_multi_session_day_sums_each_session_independently(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        // 3 sessions: 120 + 105 + 90 = 315 minutes = 5.25 hours.
        $this->addSession($record, '08:00:00', '10:00:00');
        $this->addSession($record, '11:00:00', '12:45:00');
        $this->addSession($record, '14:00:00', '15:30:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(315, (int) $updated->total_worked_minutes);
        $this->assertEqualsWithDelta(5.25, (float) $updated->total_worked_hours, 0.001);
        $this->assertSame(3, $updated->logs()->count());
    }

    public function test_sessions_from_other_days_never_leak_into_the_day_total(): void
    {
        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        // Same attendance row, but the sessions carry a different log_date.
        $this->addSession($record, '08:00:00', '12:00:00', '2026-09-10'); // 240 min - ours
        $this->addSession($record, '08:00:00', '20:00:00', '2026-09-11'); // 720 min - other day
        $this->addSession($record, '08:00:00', '22:00:00', '2026-09-09'); // 840 min - other day

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(240, (int) $updated->total_worked_minutes, 'Only the 2026-09-10 session counts');
        $this->assertEqualsWithDelta(4.0, (float) $updated->total_worked_hours, 0.001);
        $this->assertLessThan(24, (float) $updated->total_worked_hours, 'No impossible 100+ hour totals');
    }

    public function test_day_total_is_clamped_and_never_exceeds_24_hours(): void
    {
        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        // Six "impossible" 12h sessions on the same day.
        for ($i = 0; $i < 6; $i++) {
            $this->addSession($record, '00:00:00', '12:00:00');
        }

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertLessThanOrEqual(24 * 60, (int) $updated->total_worked_minutes);
    }

    public function test_overnight_session_is_measured_on_its_own_log_date(): void
    {
        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        // 22:00 -> 04:00 next day = 360 minutes, not 1080 and not negative.
        $this->addSession($record, '22:00:00', '04:00:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(360, (int) $updated->total_worked_minutes);
        $this->assertEqualsWithDelta(6.0, (float) $updated->total_worked_hours, 0.001);
    }

    public function test_session_duration_is_recomputed_per_session_not_blockwise(): void
    {
        $hours = app(AttendanceHoursService::class);

        $employee = $this->makeEmployee();
        $record = $this->makeRecord($employee, '2026-09-10');

        $a = $this->addSession($record, '08:00:00', '09:00:00');
        $b = $this->addSession($record, '12:00:00', '13:30:00');

        $this->assertSame(60, $hours->sessionMinutes($a->fresh()));
        $this->assertSame(90, $hours->sessionMinutes($b->fresh()));
    }

    public function test_day_sessions_endpoint_is_date_scoped_and_reports_overtime(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        $this->addSession($record, '08:00:00', '13:00:00'); // 300 min
        $this->addSession($record, '14:00:00', '20:00:00'); // 360 min => 660 total
        $this->addSession($record, '08:00:00', '20:00:00', '2026-09-12'); // other day

        app(CustomAttendanceService::class)->recalculateDay($record->id);

        $service = app(AttendanceHoursService::class);
        $totals = $service->aggregate($record->fresh());
        $overtime = $service->resolveOvertime($record->fresh(), $employee);

        $this->assertSame(660, $totals['total_minutes']);
        $this->assertEqualsWithDelta(11.0, $totals['total_hours'], 0.001);
        $this->assertSame(2, $totals['sessions_count']);
        $this->assertSame(180, $overtime['minutes'], '660 - 480 required = 180 overtime minutes');
    }

    // ──────────────────────────────────────────────────────────────────
    // Requirement 3: stable overtime
    // ──────────────────────────────────────────────────────────────────

    public function test_overtime_is_zero_when_shift_hours_are_not_fulfilled(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        // 7h30m < 8h required -> shortfall, never overtime.
        $this->addSession($record, '09:00:00', '16:30:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(450, (int) $updated->total_worked_minutes);
        $this->assertSame(Attendance::HOURS_SHORTFALL, $updated->hours_status);
        $this->assertSame(0, (int) $updated->overtime_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->overtime_hours, 0.001);
    }

    public function test_overtime_is_granted_only_after_required_shift_hours(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        // 9h worked vs 8h required -> 60 minutes of overtime.
        $this->addSession($record, '08:00:00', '17:00:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(540, (int) $updated->total_worked_minutes);
        $this->assertSame(Attendance::HOURS_OVERTIME, $updated->hours_status);
        $this->assertSame(60, (int) $updated->overtime_minutes);
        $this->assertEqualsWithDelta(1.0, (float) $updated->overtime_hours, 0.001);
    }

    public function test_overtime_is_stable_across_repeated_recalculation(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        $this->addSession($record, '08:00:00', '12:00:00');
        $this->addSession($record, '13:00:00', '19:00:00'); // 600 min total

        $service = app(CustomAttendanceService::class);

        $first = $service->recalculateDay($record->id);
        $second = $service->recalculateDay($record->id);
        $third = $service->recalculateDay($record->id);

        $this->assertSame(600, (int) $first->total_worked_minutes);
        $this->assertSame(600, (int) $second->total_worked_minutes);
        $this->assertSame(600, (int) $third->total_worked_minutes);
        $this->assertSame(120, (int) $first->overtime_minutes);
        $this->assertSame(120, (int) $second->overtime_minutes);
        $this->assertSame(120, (int) $third->overtime_minutes);
        $this->assertSame(Attendance::HOURS_OVERTIME, $third->hours_status);
    }

    public function test_overtime_is_zero_when_disabled_for_the_employee(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $employee->update(['overtime_enabled' => false]);

        $record = $this->makeRecord($employee, '2026-09-10');
        $this->addSession($record, '08:00:00', '18:00:00'); // 10h

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(600, (int) $updated->total_worked_minutes, 'Hours are still counted');
        $this->assertSame(0, (int) $updated->overtime_minutes, 'But overtime is suppressed');
        $this->assertEqualsWithDelta(0.0, (float) $updated->overtime_hours, 0.001);
        $this->assertNotSame(Attendance::HOURS_OVERTIME, $updated->hours_status);
    }

    public function test_overtime_is_zero_when_disabled_system_wide(): void
    {
        HRSetting::set(HRSetting::OVERTIME_ENABLED, false);

        try {
            $employee = $this->makeEmployee(requiredHours: 8.0);
            $record = $this->makeRecord($employee, '2026-09-10');
            $this->addSession($record, '08:00:00', '18:00:00'); // 10h

            $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

            $this->assertSame(600, (int) $updated->total_worked_minutes);
            $this->assertSame(0, (int) $updated->overtime_minutes);
            $this->assertEqualsWithDelta(0.0, (float) $updated->overtime_hours, 0.001);
        } finally {
            HRSetting::set(HRSetting::OVERTIME_ENABLED, true);
        }
    }

    public function test_overtime_is_zero_on_an_absent_day(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10', status: 'absent');

        // Leftover sessions from a previous state of the record must not grant
        // hours or overtime on a day the employee was marked absent.
        $this->addSession($record, '08:00:00', '18:00:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(0, (int) $updated->total_worked_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->total_worked_hours, 0.001);
        $this->assertSame(0, (int) $updated->overtime_minutes);
        $this->assertEqualsWithDelta(0.0, (float) $updated->overtime_hours, 0.001);
    }

    public function test_overtime_is_zero_while_a_session_is_still_open(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10');

        // 9 completed hours plus an unfinished session: the day is not closed,
        // so no overtime may be granted yet.
        $this->addSession($record, '08:00:00', '17:00:00');
        $this->addSession($record, '18:00:00', null);

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(540, (int) $updated->total_worked_minutes);
        $this->assertSame(0, (int) $updated->overtime_minutes, 'Incomplete day => no overtime');

        // Once the session is closed the same input deterministically yields overtime.
        $open = $updated->logs()->whereNull('check_out_time')->first();
        $open->update(['check_out_time' => '20:00:00', 'duration_minutes' => 120]);

        $closed = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(660, (int) $closed->total_worked_minutes);
        $this->assertSame(180, (int) $closed->overtime_minutes);
    }

    public function test_overtime_is_zero_on_a_leave_day(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10', status: 'on_leave');
        $this->addSession($record, '08:00:00', '18:00:00');

        $updated = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(0, (int) $updated->total_worked_minutes);
        $this->assertSame(0, (int) $updated->overtime_minutes);
    }

    public function test_shift_based_overtime_follows_the_same_rules(): void
    {
        $employee = $this->makeEmployee(custom: false);

        $record = Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '08:00:00',
            'check_out_time'  => '18:00:00',
            'status'          => 'present',
        ]);

        $processed = app(\App\Services\AttendancePenaltyService::class)->processAttendance($record);

        $this->assertSame(600, (int) $processed->total_worked_minutes);
        $this->assertEqualsWithDelta(10.0, (float) $processed->actual_worked_hours, 0.001);
        $this->assertSame(Attendance::HOURS_OVERTIME, $processed->hours_status);
        $this->assertSame(120, (int) $processed->overtime_minutes);
        $this->assertEqualsWithDelta(2.0, (float) $processed->overtime_hours, 0.001);
    }

    public function test_shift_based_overtime_is_zero_when_employee_switch_is_off(): void
    {
        $employee = $this->makeEmployee(custom: false);
        $employee->update(['overtime_enabled' => false]);

        $record = Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '08:00:00',
            'check_out_time'  => '18:00:00',
            'status'          => 'present',
        ]);

        $processed = app(\App\Services\AttendancePenaltyService::class)->processAttendance($record);

        $this->assertSame(600, (int) $processed->total_worked_minutes);
        $this->assertSame(0, (int) $processed->overtime_minutes);
    }

    public function test_shift_based_day_without_log_rows_still_reports_its_punch_pair(): void
    {
        $employee = $this->makeEmployee(custom: false, requiredHours: 8.0);

        $record = Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '08:00:00',
            'check_out_time'  => '18:00:00',
            'status'          => 'present',
        ]);

        $this->assertSame(0, $record->logs()->count(), 'No attendance_logs rows at all');

        $service = app(AttendanceHoursService::class);
        $totals  = $service->dayTotals($record, $employee);
        $overtime = $service->resolveOvertime($record, $employee);

        $this->assertSame(600, $totals['total_minutes'], 'A real punch pair must not collapse to zero');
        $this->assertEqualsWithDelta(10.0, $totals['total_hours'], 0.001);
        $this->assertSame(1, $totals['completed_sessions_count']);
        $this->assertSame(120, $overtime['minutes']);
    }

    public function test_shift_based_open_pair_reports_zero_and_blocks_overtime(): void
    {
        $employee = $this->makeEmployee(custom: false, requiredHours: 8.0);

        $record = Attendance::create([
            'employee_id'     => $employee->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '08:00:00',
            'check_out_time'  => null,
            'status'          => 'present',
        ]);

        $service = app(AttendanceHoursService::class);
        $totals  = $service->dayTotals($record, $employee);
        $overtime = $service->resolveOvertime($record, $employee);

        $this->assertSame(0, $totals['total_minutes']);
        $this->assertSame(1, $totals['open_sessions_count']);
        $this->assertSame(0, $overtime['minutes']);
    }

    public function test_absent_day_never_reports_session_hours_in_any_read_path(): void
    {
        $employee = $this->makeEmployee(requiredHours: 8.0);
        $record = $this->makeRecord($employee, '2026-09-10', status: 'absent');

        // A stale 12h session is still attached to the record.
        $this->addSession($record, '08:00:00', '20:00:00');

        $service = app(AttendanceHoursService::class);
        $persisted = app(CustomAttendanceService::class)->recalculateDay($record->id);

        $this->assertSame(0, (int) $persisted->total_worked_minutes, 'Persisted value must be zero');

        // Every READ path must agree with the persisted value, otherwise the API
        // would contradict the database row.
        $aggregate = $service->aggregate($record->fresh());
        $dayTotals = $service->dayTotals($record->fresh(), $employee);
        $effective = $service->effectiveTotals($record->fresh(), $employee);
        $summary   = $service->summary($record->fresh(), $employee);

        $this->assertSame(720, $aggregate['total_minutes'], 'raw aggregate still sees the row');
        $this->assertSame(720, $dayTotals['total_minutes'], 'dayTotals sees the row');
        $this->assertSame(0, $effective['total_minutes'], 'effectiveTotals is the reportable value');
        $this->assertEqualsWithDelta(0.0, $effective['total_hours'], 0.001);
        $this->assertSame(0, $summary['total_worked_minutes'], 'summary must use effective totals');
        $this->assertEqualsWithDelta(0.0, $summary['total_worked_hours'], 0.001);
        $this->assertSame(0, $summary['overtime_minutes']);
    }

    // ──────────────────────────────────────────────────────────────────
    // Unit-level guards on the service itself
    // ──────────────────────────────────────────────────────────────────

    public function test_minutes_between_returns_zero_for_missing_or_invalid_times(): void
    {
        $hours = app(AttendanceHoursService::class);

        $this->assertSame(0, $hours->minutesBetween('2026-09-10', null, '17:00:00'));
        $this->assertSame(0, $hours->minutesBetween('2026-09-10', '08:00:00', null));
        $this->assertSame(0, $hours->minutesBetween('2026-09-10', null, null));
        $this->assertSame(0, $hours->minutesBetween('2026-09-10', '', ''));
        $this->assertSame(0, $hours->minutesBetween('2026-09-10', '08:00:00', 'not-a-time'));
    }

    public function test_hours_between_never_returns_a_fallback_value(): void
    {
        $hours = app(AttendanceHoursService::class);

        $this->assertEqualsWithDelta(0.0, $hours->hoursBetween('2026-09-10', null, null), 0.001);
        $this->assertEqualsWithDelta(0.0, $hours->hoursBetween('2026-09-10', '08:00:00', null), 0.001);
        $this->assertEqualsWithDelta(8.5, $hours->hoursBetween('2026-09-10', '08:00:00', '16:30:00'), 0.001);
    }
}
