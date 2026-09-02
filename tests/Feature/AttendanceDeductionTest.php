<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftEarlyExitRule;
use App\Models\ShiftLateRule;
use App\Models\User;
use App\Services\AttendancePenaltyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceDeductionTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(float $salary = 5000): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'T' . self::$seq . '_' . uniqid(),
            'name'          => 'Test Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => $salary,
            'status'        => 'active',
        ]);
    }

    private function makeShift(): Shift
    {
        return $this->makeShiftAt('08:00:00', 15);
    }

    private function makeShiftAt(string $startTime, int $graceMinutes): Shift
    {
        $shift = Shift::create([
            'name'                => 'Test Shift',
            'start_time'          => $startTime,
            'end_time'            => '17:00:00',
            'grace_period_minutes' => $graceMinutes,
            'is_active'           => true,
        ]);

        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 1, 'max_delay_minutes' => 119, 'deduction_type' => 'minutes', 'deduction_value' => 5]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 120, 'max_delay_minutes' => null, 'deduction_type' => 'half_day', 'deduction_value' => 100]);

        ShiftEarlyExitRule::create(['shift_id' => $shift->id, 'min_early_minutes' => 1, 'max_early_minutes' => 59, 'deduction_type' => 'minutes', 'deduction_value' => 5]);
        ShiftEarlyExitRule::create(['shift_id' => $shift->id, 'min_early_minutes' => 60, 'max_early_minutes' => null, 'deduction_type' => 'half_day', 'deduction_value' => 100]);

        return $shift;
    }

    private function processEmpCheckIn(Employee $emp, string $checkIn): Attendance
    {
        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => $checkIn,
            'status'          => 'present',
        ]);

        return app(AttendancePenaltyService::class)->processAttendance($att);
    }

    private function assignShift(Employee $employee, Shift $shift): void
    {
        EmployeeShift::create([
            'employee_id'    => $employee->id,
            'shift_id'       => $shift->id,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to'   => null,
        ]);
    }

    public function test_process_attendance_computes_late_and_early_exit_from_shift_rules(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:30:00',  // 30 min late → minutes
            'check_out_time'  => '15:00:00',  // 120 min before end (17:00) → half_day
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        $this->assertSame(30, $processed->late_minutes);
        $this->assertSame('minutes', $processed->applied_late_deduction_type);
        $this->assertSame(120, $processed->early_exit_minutes);
        $this->assertSame('half_day', $processed->applied_early_deduction_type);
        $this->assertEquals($shift->id, $processed->shift_id);
    }

    public function test_record_deduction_amount_combines_late_minutes_and_early_half_day(): void
    {
        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:30:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);
        $result    = app(AttendancePenaltyService::class)->calculateRecordDeduction($processed);

        // 30 min late, 15 beyond grace → 15 × 5 EGP/minute + half-day 100 EGP
        $expected = round(15 * 5 + 100, 2);

        $this->assertEqualsWithDelta($expected, $result['amount'], 0.01);
        $this->assertStringContainsString('تأخير', $result['label']);
        $this->assertStringContainsString('انصراف مبكر', $result['label']);
    }

    private function workingDays(int $month, int $year): int
    {
        $count = 0;
        $start = \Carbon\Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (!$day->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }

    public function test_process_attendance_falls_back_to_config_hours_when_no_shift(): void
    {
        // ensure no active shift exists so resolveShift() returns null
        \App\Models\Shift::query()->update(['is_active' => false]);

        $emp = $this->makeEmployee(); // no shift assignment

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:30:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        $this->assertSame(30, $processed->late_minutes);
        $this->assertSame('minutes', $processed->applied_late_deduction_type);
        $this->assertSame(120, $processed->early_exit_minutes);
        $this->assertSame('minutes', $processed->applied_early_deduction_type);
    }

    public function test_checkout_endpoint_persists_checkout_time_and_returns_penalty(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $shift->end_time = '23:59:00';
        $shift->save();

        Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:00:00',
            'status'          => 'present',
        ]);

        $request = Request::create('/api/attendance/check-out', 'POST', [
            'employee_id' => $emp->id,
            'latitude'    => 30.0,
            'longitude'   => 31.0,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $response   = $controller->checkOut($request);
        $payload    = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);

        $saved = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', now()->toDateString())
            ->first();

        $this->assertNotNull($saved->check_out_time);
        $this->assertSame($saved->check_out_time, $payload['data']['check_out_time']);
        $this->assertNotNull($saved->early_exit_minutes);
        $this->assertNotNull($saved->applied_early_deduction_type);
        $this->assertSame($saved->early_exit_minutes, $payload['penalty']['early_exit_minutes']);
    }

    public function test_checkin_auto_closes_forgotten_open_shift_after_threshold(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-01 08:00:00'));
        $openDay = now()->toDateString();

        // Employee checked in but forgot to check out (open record)
        $open = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => $openDay,
            'check_in_time'   => '08:00:00',
            'status'          => 'present',
        ]);

        // Next day, more than 20h after check-in, employee tries to check in again
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-02 09:00:00'));

        $request = Request::create('/api/attendance/check-in', 'POST', [
            'employee_id' => $emp->id,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $response   = $controller->checkIn($request);
        $payload    = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success'], 'Check-in was rejected: ' . ($payload['message'] ?? ''));

        // Old open record must be auto-closed 20h after its check-in (08:00 + 20h = 04:00 next day)
        $old = $open->fresh();
        $this->assertNotNull($old->check_out_time);
        $this->assertSame('04:00:00', $old->check_out_time);

        \Carbon\Carbon::setTestNow(null);
    }

    public function test_checkout_after_midnight_on_night_shift_finds_open_record(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift(); // end_time 17:00, but we'll convert it to a night shift below
        $this->assignShift($emp, $shift);

        $shift->start_time = '17:00:00';
        $shift->end_time   = '05:00:00'; // crosses midnight
        $shift->save();

        // Day X, 17:00 — employee checks in
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-01 17:00:00'));
        $dayOne = now()->toDateString();

        Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => $dayOne,
            'check_in_time'   => now()->toTimeString(),
            'status'          => 'present',
        ]);

        // Day X+1, 06:00 — after the shift ended (05:00) the employee checks out
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-02 06:00:00'));

        $request = Request::create('/api/attendance/check-out', 'POST', [
            'employee_id' => $emp->id,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $response   = $controller->checkOut($request);
        $payload    = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success'], 'Checkout was rejected: ' . ($payload['message'] ?? ''));
        $this->assertSame('06:00:00', $payload['data']['check_out_time']);

        // The open day-X record must be the one that received the check-out
        $saved = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', $dayOne)
            ->first();
        $this->assertNotNull($saved->check_out_time);
        $this->assertSame('06:00:00', $saved->check_out_time);

        \Carbon\Carbon::setTestNow(null);
    }

    public function test_late_within_grace_period_is_not_penalized(): void
    {
        $emp = $this->makeEmployee(5000);
        $shift = $this->makeShiftAt('09:00:00', 10);
        $this->assignShift($emp, $shift);

        $att = $this->processEmpCheckIn($emp, '09:02:29'); // 2 minutes late, within 10-min grace

        $this->assertSame(0, $att->late_minutes);
        $this->assertNull($att->applied_late_deduction_type);
        $this->assertEquals(0.0, (float) $att->deduction_amount);

        $result = app(AttendancePenaltyService::class)->calculateRecordDeduction($att);
        $this->assertEquals(0.0, $result['amount']);
        $this->assertSame('-', $result['label']);
    }

    public function test_late_exactly_at_grace_boundary_is_not_penalized(): void
    {
        $emp = $this->makeEmployee(5000);
        $shift = $this->makeShiftAt('09:00:00', 10);
        $this->assignShift($emp, $shift);

        $att = $this->processEmpCheckIn($emp, '09:10:00'); // exactly 10 minutes late = grace

        $this->assertSame(0, $att->late_minutes);
        $this->assertNull($att->applied_late_deduction_type);
        $this->assertEquals(0.0, app(AttendancePenaltyService::class)->calculateRecordDeduction($att)['amount']);
    }

    public function test_late_beyond_grace_period_is_penalized(): void
    {
        $emp = $this->makeEmployee(5000);
        $shift = $this->makeShiftAt('09:00:00', 10);
        $this->assignShift($emp, $shift);

        $att = $this->processEmpCheckIn($emp, '09:20:00'); // 20 minutes late, 10 beyond grace

        $this->assertSame(20, $att->late_minutes);
        $this->assertSame('minutes', $att->applied_late_deduction_type);

        // 20 min late, 10 beyond grace → 10 × 5 EGP/minute
        $this->assertEqualsWithDelta(10 * 5, app(AttendancePenaltyService::class)->calculateRecordDeduction($att)['amount'], 0.01);
    }

    public function test_salary_deduction_includes_early_exit(): void
    {
        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:00:00',
            'check_out_time'  => '15:00:00', // 150 min early → half_day
            'status'          => 'present',
        ]);

        $svc = app(AttendancePenaltyService::class);
        $svc->processAttendance(Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', now()->toDateString())
            ->first());

        $summary = $svc->calculateAttendanceDeductionForSalary($emp, (int) now()->month, (int) now()->year, (float) $emp->base_salary);

        $this->assertEqualsWithDelta(100, $summary['amount'], 0.01);
        $this->assertStringContainsString('انصراف مبكر', $summary['label']);
    }

    public function test_my_daily_log_returns_check_in_check_out_per_day(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-09-10 12:00:00'));

        $user = User::create([
            'name'     => 'Mobile Emp',
            'email'    => 'mobile_' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        $emp   = $this->makeEmployee();
        $emp->update(['user_id' => $user->id]);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        Attendance::create([
            'employee_id'       => $emp->id,
            'attendance_date'   => '2026-09-03',
            'check_in_time'     => '08:05:00',
            'check_out_time'    => '17:00:00',
            'status'            => 'late',
            'late_minutes'      => 5,
            'early_exit_minutes'=> 0,
            'actual_worked_hours'=> 8.92,
            'shift_id'          => $shift->id,
            'deduction_amount'  => 25.0,
        ]);

        $response = $this->actingAs($user)->get('/api/attendance/my-daily-log?month=9&year=2026');
        $payload  = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertCount(30, $payload['data']); // September 2026 has 30 days

        $day3 = collect($payload['data'])->firstWhere('date', '2026-09-03');
        $this->assertSame('late', $day3['status']);
        $this->assertSame('08:05:00', $day3['check_in_time']);
        $this->assertSame('17:00:00', $day3['check_out_time']);
        $this->assertSame('Test Shift', $day3['shift_name']);

        // A day without a record should be marked absent
        $day5 = collect($payload['data'])->firstWhere('date', '2026-09-05');
        $this->assertSame('absent', $day5['status']);
        $this->assertNull($day5['check_in_time']);

        $this->assertSame(1, $payload['statistics']['late']);

        \Carbon\Carbon::setTestNow(null);
    }
}
