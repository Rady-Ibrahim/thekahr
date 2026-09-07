<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftEarlyExitRule;
use App\Models\ShiftLateRule;
use App\Services\AttendancePenaltyService;
use App\Services\CustomAttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makeEmployee(float $salary = 5000, bool $custom = false): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code'         => 'AF' . self::$seq . '_' . uniqid(),
            'name'                  => 'Attendance Flow ' . self::$seq,
            'phone'                 => '011' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'              => 'Tester',
            'department'            => 'QA',
            'joining_date'          => now()->subMonth()->toDateString(),
            'base_salary'           => $salary,
            'status'                => 'active',
            'is_custom_attendance'  => $custom,
            'daily_required_hours'  => $custom ? 8.0 : null,
        ]);
    }

    private function makeShift(string $start = '09:00:00', string $end = '17:00:00', int $grace = 15): Shift
    {
        $shift = Shift::create([
            'name'                 => 'Flow Shift ' . self::$seq,
            'start_time'           => $start,
            'end_time'             => $end,
            'grace_period_minutes' => $grace,
            'is_active'            => true,
        ]);

        ShiftLateRule::create([
            'shift_id'           => $shift->id,
            'min_delay_minutes'  => 1,
            'max_delay_minutes'  => 119,
            'deduction_type'     => 'minutes',
            'deduction_value'    => 5,
        ]);
        ShiftLateRule::create([
            'shift_id'           => $shift->id,
            'min_delay_minutes'  => 120,
            'max_delay_minutes'  => null,
            'deduction_type'     => 'half_day',
            'deduction_value'    => 100,
        ]);

        ShiftEarlyExitRule::create([
            'shift_id'            => $shift->id,
            'min_early_minutes'   => 1,
            'max_early_minutes'   => 59,
            'deduction_type'      => 'minutes',
            'deduction_value'     => 5,
        ]);
        ShiftEarlyExitRule::create([
            'shift_id'            => $shift->id,
            'min_early_minutes'   => 60,
            'max_early_minutes'   => null,
            'deduction_type'      => 'half_day',
            'deduction_value'     => 100,
        ]);

        return $shift;
    }

    private function assignShift(Employee $employee, Shift $shift, ?string $from = null, ?string $to = null): void
    {
        EmployeeShift::create([
            'employee_id'    => $employee->id,
            'shift_id'       => $shift->id,
            'effective_from' => $from ?? now()->startOfMonth()->toDateString(),
            'effective_to'   => $to,
        ]);
    }

    private function makeNightShift(int $grace = 15): Shift
    {
        return $this->makeShift('17:00:00', '05:00:00', $grace);
    }

    // ─── Case 1: Late Check-in & Deduction Calculation ─────────────────────

    public function test_late_checkin_1hour_records_late_minutes_and_deduction(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '10:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // 60 minutes late, 15 grace → 45 effective delay
        $this->assertSame(60, $processed->late_minutes);
        $this->assertSame('minutes', $processed->applied_late_deduction_type);
        // 45 × 5 EGP/minute = 225
        $this->assertEqualsWithDelta(225.0, $processed->deduction_amount, 0.01);
        $this->assertEquals($shift->id, $processed->shift_id);

        Carbon::setTestNow(null);
    }

    // ─── Case 2: Leaving 1 Hour After Shift End (No Overtime) ─────────────

    public function test_checkout_1h_after_shift_end_records_full_day_no_early_exit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '09:00:00',
            'check_out_time'  => '18:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // No late, no early exit
        $this->assertSame(0, $processed->late_minutes);
        $this->assertSame(0, $processed->early_exit_minutes);
        $this->assertNull($processed->applied_late_deduction_type);
        $this->assertNull($processed->applied_early_deduction_type);
        $this->assertEquals(0.0, $processed->deduction_amount);

        // Actual worked: 09:00 → 18:00 = 9 hours
        $this->assertEqualsWithDelta(9.0, $processed->actual_worked_hours, 0.01);

        Carbon::setTestNow(null);
    }

    // ─── Case 3: Overtime (Custom Attendance) ─────────────────────────────

    public function test_custom_attendance_overtime_records_excess_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));

        $emp   = $this->makeEmployee(5000, custom: true);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $service = app(CustomAttendanceService::class);

        // Check-in at 09:00
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));
        $checkInResult = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($checkInResult['success']);

        // Check-out at 18:00 (9 hours worked, 1h overtime)
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));
        $log = $service->openSession($emp);
        $this->assertNotNull($log);

        $checkOutResult = $service->endSession($log, []);
        $this->assertTrue($checkOutResult['success']);

        // Verify overtime status
        $attendance = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', '2026-09-10')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame(540, $attendance->total_worked_minutes); // 9 hours
        $this->assertEqualsWithDelta(9.0, $attendance->total_worked_hours, 0.01);
        $this->assertSame(Attendance::HOURS_OVERTIME, $attendance->hours_status);

        Carbon::setTestNow(null);
    }

    // ─── Case 4: Forgotten Check-out & 20-Hour Auto-Close ─────────────────

    public function test_forgotten_session_auto_closes_after_20_hours_on_checkin(): void
    {
        // Day 1, 18:00 — employee checks in (custom attendance)
        Carbon::setTestNow(Carbon::parse('2026-09-01 18:00:00'));

        $emp   = $this->makeEmployee(5000, custom: true);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $service = app(CustomAttendanceService::class);
        $checkInResult = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($checkInResult['success']);

        $openLog = $service->openSession($emp);
        $this->assertNotNull($openLog);
        $this->assertTrue($openLog->isOpen());

        // Day 2, 15:00 — 21 hours later, employee tries to check in again
        Carbon::setTestNow(Carbon::parse('2026-09-02 15:00:00'));

        $checkInResult2 = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($checkInResult2['success'], 'New check-in should succeed: ' . ($checkInResult2['message'] ?? ''));

        // Old session must be auto-closed: check-in 18:00 + 20h = 14:00 next day
        $oldLog = $openLog->fresh();
        $this->assertNotNull($oldLog->check_out_time);
        $this->assertSame('14:00:00', $oldLog->check_out_time);
        $this->assertSame(1200, $oldLog->duration_minutes); // 20 hours

        // Attendance record should be recalculated
        $att = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', '2026-09-01')
            ->first();
        $this->assertNotNull($att);
        $this->assertSame(1200, $att->total_worked_minutes);

        Carbon::setTestNow(null);
    }

    public function test_forgotten_session_auto_closes_via_command(): void
    {
        // Day 1, 18:00 — employee checks in
        Carbon::setTestNow(Carbon::parse('2026-09-01 18:00:00'));

        $emp   = $this->makeEmployee(5000, custom: true);
        $service = app(CustomAttendanceService::class);

        $service->startSession($emp, [], 'mobile');
        $log = $service->openSession($emp);
        $this->assertNotNull($log);

        // Day 2, 15:00 — simulate what the auto-close command does
        Carbon::setTestNow(Carbon::parse('2026-09-02 15:00:00'));

        $closed = $service->autoCloseStaleSessions($emp->id);
        $this->assertGreaterThanOrEqual(1, $closed);

        $log->refresh();
        $this->assertNotNull($log->check_out_time);
        $this->assertSame('14:00:00', $log->check_out_time);

        Carbon::setTestNow(null);
    }

    // ─── Case 5: Cross-day / Overnight Shift Checkout ──────────────────────

    public function test_overnight_custom_session_checkout_next_morning(): void
    {
        // Day 1, 17:00 — employee checks in for night shift
        Carbon::setTestNow(Carbon::parse('2026-09-01 17:00:00'));

        $emp   = $this->makeEmployee(5000, custom: true);
        $shift = $this->makeNightShift();
        $this->assignShift($emp, $shift);

        $service = app(CustomAttendanceService::class);
        $checkInResult = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($checkInResult['success']);

        $log = $service->openSession($emp);
        $this->assertNotNull($log);
        $this->assertSame('2026-09-01', $log->log_date->toDateString());

        // Day 2, 04:58 — employee checks out (11h 58min later)
        Carbon::setTestNow(Carbon::parse('2026-09-02 04:58:00'));

        // openSession must still find the session despite different date
        $foundSession = $service->openSession($emp);
        $this->assertNotNull($foundSession, 'openSession must find session from previous day');
        $this->assertSame($log->id, $foundSession->id);

        $checkOutResult = $service->endSession($foundSession, []);
        $this->assertTrue($checkOutResult['success']);

        // Duration: 17:00 → 04:58 = 11h 58m = 718 minutes
        $this->assertSame(718, $checkOutResult['session_duration_minutes']);

        // Attendance record for Sep 1 should be updated
        $att = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', '2026-09-01')
            ->first();
        $this->assertNotNull($att);
        $this->assertSame(718, $att->total_worked_minutes);
        $this->assertEqualsWithDelta(11.97, $att->total_worked_hours, 0.01);

        Carbon::setTestNow(null);
    }

    public function test_overnight_standard_attendance_checkout_next_day(): void
    {
        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeNightShift(); // 17:00 → 05:00
        $this->assignShift($emp, $shift);

        // Day 1, 17:00 — check in
        Carbon::setTestNow(Carbon::parse('2026-09-01 17:00:00'));
        $dayOne = now()->toDateString();

        Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => $dayOne,
            'check_in_time'   => now()->toTimeString(),
            'status'          => 'present',
        ]);

        // Day 2, 04:58 — check out
        Carbon::setTestNow(Carbon::parse('2026-09-02 04:58:00'));

        $request = Request::create('/api/attendance/check-out', 'POST', [
            'employee_id' => $emp->id,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller  = new AttendanceController(app(AttendancePenaltyService::class));
        $response    = $controller->checkOut($request);
        $payload     = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success'], 'Checkout failed: ' . ($payload['message'] ?? ''));
        $this->assertSame('04:58:00', $payload['data']['check_out_time']);

        $saved = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', $dayOne)
            ->first();
        $this->assertNotNull($saved->check_out_time);
        $this->assertSame('04:58:00', $saved->check_out_time);

        Carbon::setTestNow(null);
    }

    // ─── Case 6: Early Departure ──────────────────────────────────────────

    public function test_early_departure_2h_before_shift_end_records_penalty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 15:00:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '09:00:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // 120 minutes early (17:00 - 15:00)
        $this->assertSame(120, $processed->early_exit_minutes);
        $this->assertSame('half_day', $processed->applied_early_deduction_type);
        $this->assertEqualsWithDelta(100.0, $processed->deduction_amount, 0.01);

        // Worked: 09:00 → 15:00 = 6 hours
        $this->assertEqualsWithDelta(6.0, $processed->actual_worked_hours, 0.01);

        // No late
        $this->assertSame(0, $processed->late_minutes);
        $this->assertNull($processed->applied_late_deduction_type);

        Carbon::setTestNow(null);
    }

    public function test_early_departure_minutes_rule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 16:00:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '09:00:00',
            'check_out_time'  => '16:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // 60 minutes early → minutes rule (1-59 range is minutes@5, 60+ is half_day@100)
        // Actually 60 >= 60 so half_day rule applies
        $this->assertSame(60, $processed->early_exit_minutes);
        $this->assertSame('half_day', $processed->applied_early_deduction_type);
        $this->assertEqualsWithDelta(100.0, $processed->deduction_amount, 0.01);

        // Worked: 09:00 → 16:00 = 7 hours
        $this->assertEqualsWithDelta(7.0, $processed->actual_worked_hours, 0.01);

        Carbon::setTestNow(null);
    }

    public function test_early_departure_within_1h_uses_minutes_rule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 16:30:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '09:00:00',
            'check_out_time'  => '16:30:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // 30 minutes early → minutes rule (1-59 = minutes@5)
        $this->assertSame(30, $processed->early_exit_minutes);
        $this->assertSame('minutes', $processed->applied_early_deduction_type);
        $this->assertEqualsWithDelta(30 * 5, $processed->deduction_amount, 0.01);

        $this->assertEqualsWithDelta(7.5, $processed->actual_worked_hours, 0.01);

        Carbon::setTestNow(null);
    }
}
