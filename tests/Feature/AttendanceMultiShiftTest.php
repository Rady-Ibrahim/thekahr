<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\ShiftLateRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendanceMultiShiftTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(float $salary = 5000): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'M' . self::$seq . '_' . uniqid(),
            'name'          => 'Multi Emp ' . self::$seq,
            'phone'         => '012' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => $salary,
            'status'        => 'active',
        ]);
    }

    private function makeShift(string $name, string $start, string $end, ?string $effectiveFrom = '2026-01-01'): Shift
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

    private function assignShift(Employee $employee, Shift $shift, string $effectiveFrom = '2026-01-01'): EmployeeShift
    {
        return EmployeeShift::create([
            'employee_id'    => $employee->id,
            'shift_id'       => $shift->id,
            'effective_from' => $effectiveFrom,
            'effective_to'   => null,
        ]);
    }

    private function checkIn(Employee $employee): array
    {
        $request = Request::create('/api/attendance/check-in', 'POST', [
            'employee_id' => $employee->id,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(\App\Services\AttendancePenaltyService::class));
        $response = $controller->checkIn($request);

        return ['status' => $response->getStatusCode(), 'payload' => json_decode($response->getContent(), true)];
    }

    private function checkOut(Employee $employee): array
    {
        $request = Request::create('/api/attendance/check-out', 'POST', [
            'employee_id' => $employee->id,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(\App\Services\AttendancePenaltyService::class));
        $response = $controller->checkOut($request);

        return ['status' => $response->getStatusCode(), 'payload' => json_decode($response->getContent(), true)];
    }

    public function testScenario1_12hour_day_shift_standard_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 07:55:00'));

        $emp = $this->makeEmployee();
        $shift = $this->makeShift('12H Day', '08:00:00', '20:00:00');
        $this->assignShift($emp, $shift);

        // Check in at 07:55 (within grace for 08:00 start).
        $in = $this->checkIn($emp);
        $this->assertSame(200, $in['status']);
        $this->assertTrue($in['payload']['success']);
        $this->assertSame($shift->id, $in['payload']['shift']['id'] ?? null);
        // Start matches the 12h day shift binding.
        $record = Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-14')->first();
        $this->assertSame($shift->id, (int) $record->shift_id);
        $this->assertSame('07:55:00', $record->check_in_time);

        // Check out at 20:05 same day — no "already checked in/out" error.
        Carbon::setTestNow(Carbon::parse('2026-09-14 20:05:00'));
        $out = $this->checkOut($emp);
        $this->assertSame(200, $out['status']);
        $this->assertTrue($out['payload']['success']);
        $this->assertSame('20:05:00', $out['payload']['data']['check_out_time']);

        // Duration: roughly 12h10m.
        $record = $record->fresh();
        $this->assertSame('20:05:00', $record->check_out_time);
        $this->assertGreaterThanOrEqual(12.0, (float) $record->actual_worked_hours);

        Carbon::setTestNow(null);
    }

    public function testScenario2_12hour_night_shift_crosses_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 16:50:00'));

        $emp = $this->makeEmployee();
        $shift = $this->makeShift('12H Night', '17:00:00', '05:00:00');
        $this->assignShift($emp, $shift);

        // Check in on Day 1 (16:50, before 17:00 night start).
        $in = $this->checkIn($emp);
        $this->assertSame(200, $in['status']);
        $this->assertTrue($in['payload']['success']);
        $this->assertSame($shift->id, $in['payload']['shift']['id'] ?? null);

        $recordDay1 = Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-14')->first();
        $this->assertNotNull($recordDay1);
        $this->assertSame($shift->id, (int) $recordDay1->shift_id);
        $this->assertSame('16:50:00', $recordDay1->check_in_time);

        // Check out on Day 2 at 05:10 — must locate the Day 1 open record across midnight.
        Carbon::setTestNow(Carbon::parse('2026-09-15 05:10:00'));
        $out = $this->checkOut($emp);
        $this->assertSame(200, $out['status']);
        $this->assertTrue($out['payload']['success']);

        // The Day 1 record is the one that got closed (not a new Day 2 record).
        $closed = $recordDay1->fresh();
        $this->assertSame('05:10:00', $closed->check_out_time);
        $this->assertNull(Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-15')->first());

        // Duration between 16:50 and 05:10 next day ≈ 12h20m.
        $this->assertGreaterThanOrEqual(12.0, (float) $closed->actual_worked_hours);

        Carbon::setTestNow(null);
    }

    public function testScenario3_sequential_back_to_back_shifts_same_employee_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 08:00:00'));

        $emp = $this->makeEmployee();
        $shift1 = $this->makeShift('Shift1', '08:00:00', '16:00:00', '2026-01-01');
        $shift2 = $this->makeShift('Shift2 Night', '17:00:00', '05:00:00', '2026-02-01');
        $this->assignShift($emp, $shift1, '2026-01-01');
        $this->assignShift($emp, $shift2, '2026-02-01');

        // Shift 1: check in at 08:00, check out at 16:00.
        $in1 = $this->checkIn($emp);
        $this->assertSame(200, $in1['status']);
        Carbon::setTestNow(Carbon::parse('2026-09-14 16:00:00'));
        $out1 = $this->checkOut($emp);
        $this->assertSame(200, $out1['status']);
        $this->assertTrue($out1['payload']['success']);

        // Immediate check-in for Shift 2 on the SAME calendar day.
        Carbon::setTestNow(Carbon::parse('2026-09-14 17:00:00'));
        $in2 = $this->checkIn($emp);
        $this->assertSame(200, $in2['status']);
        $this->assertTrue($in2['payload']['success']);

        // The same day record's shift was rebound to Shift 2 (night).
        $record = Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-14')->first();
        $this->assertNotNull($record);
        $this->assertSame((int) $shift2->id, (int) $record->shift_id);
        $this->assertNull($record->check_out_time); // new session is open

        Carbon::setTestNow(null);
    }

    public function testScenario4_multi_employee_shift_handover_rotation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-18 05:00:00'));

        $empD = $this->makeEmployee(6000);
        $empE = $this->makeEmployee(6000);
        $shiftDay = $this->makeShift('Day', '05:00:00', '17:00:00', '2026-01-01');
        $shiftNight = $this->makeShift('Night', '17:00:00', '05:00:00', '2026-01-01');
        $this->assignShift($empD, $shiftDay, '2026-01-01');
        $this->assignShift($empE, $shiftNight, '2026-01-01');

        // Employee D: day shift 05:00 -> 17:00.
        $dIn = $this->checkIn($empD);
        $this->assertSame(200, $dIn['status']);
        $this->assertTrue($dIn['payload']['success']);
        Carbon::setTestNow(Carbon::parse('2026-09-18 17:00:00'));
        $dOut = $this->checkOut($empD);
        $this->assertSame(200, $dOut['status']);
        $this->assertTrue($dOut['payload']['success']);

        // Employee E: night shift 17:00 -> 05:00 — starts at the handover moment.
        Carbon::setTestNow(Carbon::parse('2026-09-18 17:00:00'));
        $eIn = $this->checkIn($empE);
        $this->assertSame(200, $eIn['status']);
        $this->assertTrue($eIn['payload']['success']);

        // Independent records and shift bindings.
        $recD = Attendance::where('employee_id', $empD->id)->where('attendance_date', '2026-09-18')->first();
        $recE = Attendance::where('employee_id', $empE->id)->where('attendance_date', '2026-09-18')->first();

        $this->assertNotNull($recD);
        $this->assertNotNull($recE);
        $this->assertNotSame((int) $recD->id, (int) $recE->id);
        $this->assertSame((int) $shiftDay->id, (int) $recD->shift_id);
        $this->assertSame((int) $shiftNight->id, (int) $recE->shift_id);
        $this->assertNotNull($recD->check_out_time);  // D done
        $this->assertNull($recE->check_out_time);     // E still open

        // Employee E checks out next morning (handover back to day).
        Carbon::setTestNow(Carbon::parse('2026-09-19 05:10:00'));
        $eOut = $this->checkOut($empE);
        $this->assertSame(200, $eOut['status']);
        $this->assertTrue($eOut['payload']['success']);

        $recE2 = $recE->fresh();
        $this->assertNotNull($recE2->check_out_time);

        Carbon::setTestNow(null);
    }

    public function testScenario5_forgotten_checkout_auto_close_4h_grace_12h_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 08:00:00'));

        $emp = $this->makeEmployee();
        $shift = $this->makeShift('12H Day', '08:00:00', '20:00:00');
        $this->assignShift($emp, $shift);

        // Employee F checks in for the 12h shift and forgets to check out.
        $in = $this->checkIn($emp);
        $this->assertSame(200, $in['status']);
        $this->assertTrue($in['payload']['success']);

        $record = Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-20')->first();
        $this->assertNotNull($record);
        $this->assertNull($record->check_out_time);

        // On next check-in before the 4h grace expires, the old session must NOT yet be closed.
        Carbon::setTestNow(Carbon::parse('2026-09-20 23:00:00')); // 20:00 end + 3h, within 4h grace
        $inEarly = $this->checkIn($emp);
        $this->assertSame(422, $inEarly['status']); // open session still exists (within grace)
        $this->assertNull($record->fresh()->check_out_time);

        // Just after the 4h grace (20:00 + 4h = 00:00 next day), a new check-in fires
        // auto-close on the stale session and proceeds cleanly.
        Carbon::setTestNow(Carbon::parse('2026-09-21 00:30:00'));
        $inLate = $this->checkIn($emp);
        $this->assertSame(200, $inLate['status']);
        $this->assertTrue($inLate['payload']['success']);

        // Old session closed at the scheduled shift end (20:00).
        $closed = $record->fresh();
        $this->assertNotNull($closed->check_out_time);
        $this->assertSame('20:00:00', $closed->check_out_time);

        // New check-in recorded successfully on the next day.
        $newRecord = Attendance::where('employee_id', $emp->id)->where('attendance_date', '2026-09-21')->first();
        $this->assertNotNull($newRecord);
        $this->assertNull($newRecord->check_out_time);

        Carbon::setTestNow(null);
    }
}
