<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftLateRule;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttendanceDynamicShiftTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(float $salary = 5000): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'D' . self::$seq . '_' . uniqid(),
            'name'          => 'Dynamic Emp ' . self::$seq,
            'phone'         => '011' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => $salary,
            'status'        => 'active',
        ]);
    }

    private function makeShift(string $name, string $start, string $end): Shift
    {
        $shift = Shift::create([
            'name'                => $name,
            'start_time'          => $start,
            'end_time'            => $end,
            'grace_period_minutes' => 15,
            'is_active'           => true,
        ]);

        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 1, 'max_delay_minutes' => null, 'deduction_type' => 'minutes', 'deduction_value' => 5]);

        return $shift;
    }

    private function assignShift(Employee $employee, Shift $shift, ?string $effectiveFrom = null): EmployeeShift
    {
        return EmployeeShift::create([
            'employee_id'    => $employee->id,
            'shift_id'       => $shift->id,
            'effective_from' => $effectiveFrom ?? now()->startOfMonth()->toDateString(),
            'effective_to'   => null,
        ]);
    }

    public function test_resolve_shift_matches_overnight_shift_by_checkin_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));

        $emp = $this->makeEmployee();
        $dayShift = $this->makeShift('Day', '05:00:00', '17:00:00');
        $nightShift = $this->makeShift('Night', '17:00:00', '05:00:00');
        $this->assignShift($emp, $dayShift, '2026-01-01');
        $this->assignShift($emp, $nightShift, '2026-02-01');

        // Employee has two assigned active shifts. At 18:00 the night shift window
        // (17:00 -> 05:00) must be selected dynamically.
        $svc = app(AttendancePenaltyService::class);
        $resolved = $svc->resolveShift($emp, Carbon::parse('2026-09-10'), now());

        $this->assertNotNull($resolved);
        $this->assertSame($nightShift->id, $resolved->id);

        Carbon::setTestNow(null);
    }

    public function test_resolve_shift_matches_day_shift_by_checkin_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00'));

        $emp = $this->makeEmployee();
        $dayShift = $this->makeShift('Day', '05:00:00', '17:00:00');
        $nightShift = $this->makeShift('Night', '17:00:00', '05:00:00');
        $this->assignShift($emp, $dayShift, '2026-01-01');
        $this->assignShift($emp, $nightShift, '2026-02-01');

        $svc = app(AttendancePenaltyService::class);
        $resolved = $svc->resolveShift($emp, Carbon::parse('2026-09-10'), now());

        $this->assertNotNull($resolved);
        $this->assertSame($dayShift->id, $resolved->id);

        Carbon::setTestNow(null);
    }

    public function test_resolve_shift_falls_back_to_matching_global_shift_when_unassigned(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));

        $emp = $this->makeEmployee();
        $dayShift = $this->makeShift('Day', '05:00:00', '17:00:00');
        $nightShift = $this->makeShift('Night', '17:00:00', '05:00:00');
        // No employee assignment at all — dynamic global matching must kick in.

        $svc = app(AttendancePenaltyService::class);
        $resolved = $svc->resolveShift($emp, Carbon::parse('2026-09-10'), now());

        $this->assertNotNull($resolved);
        $this->assertSame($nightShift->id, $resolved->id);

        Carbon::setTestNow(null);
    }

    public function test_employee_handover_is_clean_for_different_employees(): void
    {
        // Employee A checks in at 17:00 for the night shift (17:00-05:00).
        Carbon::setTestNow(Carbon::parse('2026-09-10 17:00:00'));
        $today = now()->toDateString();

        $empA = $this->makeEmployee();
        $empB = $this->makeEmployee();
        $nightShift = $this->makeShift('Night', '17:00:00', '05:00:00');
        $this->assignShift($empA, $nightShift);
        $this->assignShift($empB, $nightShift);

        $svc = app(AttendancePenaltyService::class);

        $attA = Attendance::create([
            'employee_id'     => $empA->id,
            'attendance_date' => $today,
            'check_in_time'   => now()->toTimeString(),
            'status'          => 'present',
            'shift_id'        => $nightShift->id,
        ]);

        // Later, when both have forgotten to check out...
        Carbon::setTestNow(Carbon::parse('2026-09-11 09:30:00')); // 05:00 end + 4h30m grace

        // Auto-close ALL stale records: both employees' sessions must close cleanly.
        $closed = $svc->autoCloseForgotten();

        $this->assertContains((int) $attA->id, $closed);
        $this->assertSame(1, count($closed)); // only empA has an open record

        // And the same employee can check in the next day without an "open session" error.
        $attA2 = Attendance::create([
            'employee_id'     => $empA->id,
            'attendance_date' => '2026-09-11',
            'check_in_time'   => '09:30:00',
            'status'          => 'present',
        ]);

        $this->assertNotNull($attA2);

        Carbon::setTestNow(null);
    }

    public function test_forgotten_session_auto_closes_after_4_hours_past_shift_end(): void
    {
        $emp = $this->makeEmployee();
        $shift = $this->makeShift('Night', '17:00:00', '05:00:00');
        $this->assignShift($emp, $shift);

        $svc = app(AttendancePenaltyService::class);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '17:00:00',
            'status'          => 'present',
            'shift_id'        => $shift->id,
        ]);

        // Within the 4h grace (09:00 = 05:00 end + 4h) -> NOT stale.
        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00:00'));
        $this->assertFalse($svc->isOpenRecordStale($att, now()));
        $this->assertSame([], $svc->autoCloseForgotten($emp->id, now()));

        // Just past the 4h grace (09:01) -> stale.
        Carbon::setTestNow(Carbon::parse('2026-09-11 09:01:00'));
        $this->assertTrue($svc->isOpenRecordStale($att->fresh(), now()));

        $closed = $svc->autoCloseForgotten($emp->id, now());
        $this->assertContains((int) $att->id, $closed);

        // Check-out stamped at the official shift end (05:00), not "now".
        $closedAttendance = $att->fresh();
        $this->assertSame('05:00:00', $closedAttendance->check_out_time);

        Carbon::setTestNow(null);
    }

    public function test_forgotten_session_not_closed_within_grace_for_day_shift(): void
    {
        $emp = $this->makeEmployee();
        $shift = $this->makeShift('Day', '05:00:00', '17:00:00');
        $this->assignShift($emp, $shift);

        $svc = app(AttendancePenaltyService::class);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '05:00:00',
            'status'          => 'present',
            'shift_id'        => $shift->id,
        ]);

        // 20:00 = 17:00 end + 3h, still within 4h grace -> no close.
        Carbon::setTestNow(Carbon::parse('2026-09-10 20:00:00'));
        $this->assertFalse($svc->isOpenRecordStale($att, now()));
        $this->assertSame([], $svc->autoCloseForgotten($emp->id, now()));

        Carbon::setTestNow(null);
    }

    public function test_auto_close_artisan_command_closes_stale_sessions(): void
    {
        $emp = $this->makeEmployee();
        $shift = $this->makeShift('Day', '05:00:00', '17:00:00');
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '05:00:00',
            'status'          => 'present',
            'shift_id'        => $shift->id,
        ]);

        // 21:30 = 17:00 end + 4.5h -> stale; command should close it.
        $this->artisan('attendance:auto-close-forgotten', ['--employee' => $emp->id, '--now' => '2026-09-10 21:30:00'])
            ->expectsOutputToContain('Auto-closed 1')
            ->assertExitCode(0);

        $this->assertNotNull($att->fresh()->check_out_time);
        $this->assertSame('17:00:00', $att->fresh()->check_out_time);
    }
}
