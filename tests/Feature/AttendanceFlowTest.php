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
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
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
        // Beyond the grace the free minutes are forfeited → 60 × 5 EGP/minute = 300
        $this->assertEqualsWithDelta(300.0, $processed->deduction_amount, 0.01);
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
        $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $oldLog->notes);

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

    public function test_early_departure_on_overnight_shift_crossing_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 18:00:00'));

        $emp   = $this->makeEmployee(5000);
        $shift = $this->makeShift('18:00:00', '02:00:00', 15);
        $this->assignShift($emp, $shift);

        // Checked in 18:00 on Sep 10, checked out 01:00 on Sep 11.
        // The shift ends at 02:00 on Sep 11, so the employee left 60 minutes early.
        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '18:00:00',
            'check_out_time'  => '01:00:00',
            'status'          => 'present',
            'shift_id'        => $shift->id,
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // Early by 60 minutes (02:00 next day - 01:00 next day).
        $this->assertSame(60, $processed->early_exit_minutes);
        $this->assertSame('half_day', $processed->applied_early_deduction_type);
        $this->assertEqualsWithDelta(100.0, $processed->deduction_amount, 0.01);

        // Worked 18:00 -> 01:00 (next day) = 7 hours.
        $this->assertEqualsWithDelta(7.0, $processed->actual_worked_hours, 0.01);

        // No late.
        $this->assertSame(0, $processed->late_minutes);

        Carbon::setTestNow(null);
    }

    // ─── Self-cleaning via Middleware (no cron dependency) ──────────────────

    private function makeUserWithEmployee(): User
    {
        $user = User::create([
            'name'     => 'Flow User ' . self::$seq,
            'email'    => 'flow' . self::$seq . '_' . uniqid() . '@example.com',
            'password' => 'password',
        ]);

        $emp = $this->makeEmployee(5000, custom: true);
        $emp->update(['user_id' => $user->id]);

        return $user;
    }

    public function test_middleware_auto_closes_stale_session_on_authenticated_request(): void
    {
        // Day 1, 17:00 — employee checks in (custom attendance).
        Carbon::setTestNow(Carbon::parse('2026-09-02 17:00:00'));

        $user = $this->makeUserWithEmployee();
        $emp  = Employee::where('user_id', $user->id)->firstOrFail();

        $service = app(CustomAttendanceService::class);
        $service->startSession($emp, [], 'mobile');

        $openLog = $service->openSession($emp);
        $this->assertNotNull($openLog);
        $this->assertTrue($openLog->isOpen());

        // Day 2, 15:00 — 22 hours later the employee opens the app: a request to
        // any protected endpoint triggers the self-cleaning middleware.
        Carbon::setTestNow(Carbon::parse('2026-09-03 15:00:00'));

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/attendance/my-records');

        $response->assertOk();

        // Old session closed at check-in + 20h = 13:00 next day with a marker note.
        $closed = $openLog->fresh();
        $this->assertNotNull($closed->check_out_time);
        $this->assertSame('13:00:00', $closed->check_out_time);
        $this->assertSame(1200, $closed->duration_minutes);
        $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $closed->notes);

        Carbon::setTestNow(null);
    }

    public function test_middleware_does_not_close_recent_session(): void
    {
        // Day 1, 09:00 — employee checks in.
        Carbon::setTestNow(Carbon::parse('2026-09-02 09:00:00'));

        $user = $this->makeUserWithEmployee();
        $emp  = Employee::where('user_id', $user->id)->firstOrFail();

        $service = app(CustomAttendanceService::class);
        $service->startSession($emp, [], 'mobile');

        // Same day, 19:00 — only 10 hours elapsed, session must stay open.
        Carbon::setTestNow(Carbon::parse('2026-09-02 19:00:00'));

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/attendance/my-records');

        $response->assertOk();

        $stillOpen = $service->openSession($emp);
        $this->assertNotNull($stillOpen);
        $this->assertTrue($stillOpen->isOpen());

        Carbon::setTestNow(null);
    }

    public function test_stale_session_cleanup_on_checkin_without_cron(): void
    {
        // Day 1, 18:00 — employee checks in and forgets to check out.
        Carbon::setTestNow(Carbon::parse('2026-09-01 18:00:00'));

        $emp   = $this->makeEmployee(5000, custom: true);
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $service = app(CustomAttendanceService::class);
        $service->startSession($emp, [], 'mobile');
        $oldLog = $service->openSession($emp);
        $this->assertNotNull($oldLog);

        // Day 2, 16:00 — 22 hours later (NO cron has run in between) the employee
        // checks in again: the app cleans the stale session itself and opens a new one.
        Carbon::setTestNow(Carbon::parse('2026-09-02 16:00:00'));

        $result = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($result['success'], 'New check-in should succeed: ' . ($result['message'] ?? ''));

        // Old session auto-closed at check-in + 20h = 14:00 next day, marked.
        $closed = $oldLog->fresh();
        $this->assertNotNull($closed->check_out_time);
        $this->assertSame('14:00:00', $closed->check_out_time);
        $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $closed->notes);

        // A brand-new open session exists for the new day.
        $newLog = $service->openSession($emp);
        $this->assertNotNull($newLog);
        $this->assertNotSame($closed->id, $newLog->id);
        $this->assertSame('2026-09-02', $newLog->log_date->toDateString());
        $this->assertTrue($newLog->isOpen());

        Carbon::setTestNow(null);
    }

    public function test_middleware_admin_cleans_all_employees_stale_sessions(): void
    {
        // Day 1, 17:00 — two custom employees check in and forget to check out.
        Carbon::setTestNow(Carbon::parse('2026-09-01 17:00:00'));

        $empA = $this->makeEmployee(5000, custom: true);
        $empB = $this->makeEmployee(5000, custom: true);

        $service = app(CustomAttendanceService::class);
        $service->startSession($empA, [], 'mobile');
        $service->startSession($empB, [], 'mobile');

        $logA = $service->openSession($empA);
        $logB = $service->openSession($empB);
        $this->assertNotNull($logA);
        $this->assertNotNull($logB);

        // A super-admin with NO employee record signs in.
        // Create role in test DB (no seed data).
        Role::create(['name' => 'super_admin', 'description' => 'System admin']);

        $admin = User::create([
            'name'     => 'Super Admin ' . self::$seq,
            'email'    => 'superadmin_' . uniqid() . '@example.com',
            'password' => 'password',
        ]);
        $admin->giveRole('super_admin');
        $this->assertTrue($admin->hasRole('super_admin'), 'Role must be attached');

        // ...22 hours later: their plain request must sweep BOTH employees'
        // stale sessions even though the admin owns neither of them.
        Carbon::setTestNow(Carbon::parse('2026-09-02 15:00:00'));

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/attendance/my-records');
        $response->assertOk();

        foreach ([$logA, $logB] as $log) {
            $closed = $log->fresh();
            $this->assertNotNull($closed->check_out_time);
            $this->assertSame('13:00:00', $closed->check_out_time);
            $this->assertSame(1200, $closed->duration_minutes);
            $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $closed->notes);
        }

        Carbon::setTestNow(null);
    }

    public function test_checkin_status_aligns_with_applied_deduction(): void
    {
        // Two independent standard employees on the same 09:00-17:00 shift
        // (grace 15 min). 21 raw minutes late exceeds the grace, so the free
        // 15 minutes are forfeited and the minutes rule (1-119 = EGP 5/min)
        // charges the full 21 minutes = 105 at check-in/out.
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $graceEmp = $this->makeEmployee();
        $lateEmp  = $this->makeEmployee();
        $shift    = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($graceEmp, $shift);
        $this->assignShift($lateEmp, $shift);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));

        // 09:14 = inside the 15-minute grace -> present, no late/penalty.
        $graceReq = Request::create('/api/attendance/check-in', 'POST', [
            'employee_id'         => $graceEmp->id,
            'custom_check_in_time' => '09:14:00',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $gracePayload = json_decode($controller->checkIn($graceReq)->getContent(), true);

        $this->assertTrue($gracePayload['success'], 'Grace check-in failed: ' . ($gracePayload['message'] ?? ''));
        $this->assertSame('present', $gracePayload['status']);
        $this->assertSame(0, $gracePayload['late_minutes']);
        $this->assertNull($gracePayload['applied_deduction_type']);

        // 09:21 = beyond grace -> late, 21 raw, minutes@5 = 105.
        $lateReq = Request::create('/api/attendance/check-in', 'POST', [
            'employee_id'         => $lateEmp->id,
            'custom_check_in_time' => '09:21:00',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $latePayload = json_decode($controller->checkIn($lateReq)->getContent(), true);

        $this->assertTrue($latePayload['success'], 'Late check-in failed: ' . ($latePayload['message'] ?? ''));
        $this->assertSame('late', $latePayload['status']);
        $this->assertSame(21, $latePayload['late_minutes']);
        $this->assertSame('minutes', $latePayload['applied_deduction_type']);

        // The deduction the rule ladder attaches to that status.
        $lateRecord = Attendance::where('employee_id', $lateEmp->id)->firstOrFail();
        $processed  = app(AttendancePenaltyService::class)->processAttendance($lateRecord->fresh());
        $this->assertSame('minutes', $processed->applied_late_deduction_type);
        $this->assertEqualsWithDelta(105.0, $processed->deduction_amount, 0.01);

        Carbon::setTestNow(null);
    }

    public function test_live_checkin_saves_shift_tier_amount_immediately(): void
    {
        // Acceptance: a 09:21 check-in on a shift with a 15-min grace and a
        // 16-60 min "quarter_day = 50" tier must persist 50.00 to the attendances
        // row at live check-in (no recalculate needed) and echo it in the response.
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $emp = $this->makeEmployee(5000);

        $shift = Shift::create([
            'name'                 => 'Quarter Tier Shift',
            'start_time'           => '09:00:00',
            'end_time'             => '17:00:00',
            'grace_period_minutes' => 15,
            'is_active'            => true,
        ]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 1,   'max_delay_minutes' => 15,  'deduction_type' => 'minutes',     'deduction_value' => 5]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 16,  'max_delay_minutes' => 60,  'deduction_type' => 'quarter_day', 'deduction_value' => 50]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 61,  'max_delay_minutes' => 240, 'deduction_type' => 'half_day',    'deduction_value' => 100]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 241, 'max_delay_minutes' => null, 'deduction_type' => 'full_day',   'deduction_value' => 200]);

        $this->assignShift($emp, $shift);

        $request = Request::create('/api/attendance/check-in', 'POST', [
            'employee_id'          => $emp->id,
            'custom_check_in_time' => '09:21:00',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $payload = json_decode($controller->checkIn($request)->getContent(), true);

        // Live response carries the monetary value right away (no recalc).
        $this->assertTrue($payload['success']);
        $this->assertSame('late', $payload['status']);
        $this->assertSame(21, $payload['late_minutes']);
        $this->assertSame('quarter_day', $payload['applied_deduction_type']);
        $this->assertEqualsWithDelta(50.0, $payload['deduction_amount'], 0.01);

        // The attendances row persisted it immediately on check-in.
        $saved = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', '2026-09-10')
            ->firstOrFail();
        $this->assertSame('quarter_day', $saved->applied_late_deduction_type);
        $this->assertEqualsWithDelta(50.0, $saved->deduction_amount, 0.01);

        Carbon::setTestNow(null);
    }

    public function test_standard_auto_close_stamps_auto_closed_note(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));

        $emp   = $this->makeEmployee();
        $shift = $this->makeShift('09:00:00', '17:00:00', 15);
        $this->assignShift($emp, $shift);

        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-01',
            'check_in_time'   => '10:00:00',
            'shift_id'        => $shift->id,
            'status'          => 'present',
        ]);

        // Check-in + 20h = 06:00 next day; by 2026-09-03 the record is stale.
        Carbon::setTestNow(Carbon::parse('2026-09-03 09:00:00'));

        $svc = app(AttendancePenaltyService::class);
        $this->assertTrue($svc->isOpenRecordStale($att->fresh(), now()));
        $this->assertContains((int) $att->id, $svc->autoCloseForgotten());

        $closed = $att->fresh();
        $this->assertNotNull($closed->check_out_time);
        $this->assertSame('06:00:00', $closed->check_out_time);
        $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $closed->notes);

        Carbon::setTestNow(null);
    }

    public function test_late_beyond_grace_applies_shift_tier_on_total_delay(): void
    {
        // Acceptance #1: Eman/Salma arrive 09:21 / 09:22 (grace 15 min). Once the
        // grace is exceeded the free minutes are voided and the shift's own tier
        // (16-60 min = quarter_day) applies immediately instead of tiny per-minute.
        Carbon::setTestNow(Carbon::parse('2026-09-10 09:00:00'));

        $graceEmp = $this->makeEmployee(5000);
        $lateEmp  = $this->makeEmployee(5000);

        $shift = Shift::create([
            'name'                 => 'Pharmacy Shift',
            'start_time'           => '09:00:00',
            'end_time'             => '17:00:00',
            'grace_period_minutes' => 15,
            'is_active'            => true,
        ]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 1,  'max_delay_minutes' => 15,  'deduction_type' => 'minutes',     'deduction_value' => 5]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 16, 'max_delay_minutes' => 60,  'deduction_type' => 'quarter_day', 'deduction_value' => 50]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 61, 'max_delay_minutes' => 240, 'deduction_type' => 'half_day',    'deduction_value' => 100]);
        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 241, 'max_delay_minutes' => null, 'deduction_type' => 'full_day',  'deduction_value' => 200]);

        $this->assignShift($graceEmp, $shift);
        $this->assignShift($lateEmp, $shift);

        // 09:14 → inside grace: nothing charged.
        $graceAtt = Attendance::create([
            'employee_id'     => $graceEmp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '09:14:00',
            'status'          => 'present',
        ]);
        $graceResult = app(AttendancePenaltyService::class)->processAttendance($graceAtt->fresh());

        $this->assertSame(0, $graceResult->late_minutes);
        $this->assertNull($graceResult->applied_late_deduction_type);
        $this->assertEquals(0.0, (float) $graceResult->deduction_amount);

        // 09:21 → past the 15-min grace: quarter_day applies on the total delay.
        $lateAtt = Attendance::create([
            'employee_id'     => $lateEmp->id,
            'attendance_date' => '2026-09-10',
            'check_in_time'   => '09:21:00',
            'status'          => 'present',
        ]);
        $lateResult = app(AttendancePenaltyService::class)->processAttendance($lateAtt->fresh());

        $this->assertSame(21, $lateResult->late_minutes);
        $this->assertSame('quarter_day', $lateResult->applied_late_deduction_type);
        $this->assertEqualsWithDelta(50.0, $lateResult->deduction_amount, 0.01);

        Carbon::setTestNow(null);
    }

    public function test_custom_session_duration_uses_session_date(): void
    {
        // Acceptance #3: a 17:07 -> 18:15 session must be exactly 68 minutes, never
        // inflated (previously 4252) by anchoring against a far-away wall clock.
        Carbon::setTestNow(Carbon::parse('2026-09-10 17:07:00'));

        $emp     = $this->makeEmployee(5000, custom: true);
        $service = app(CustomAttendanceService::class);

        $checkIn = $service->startSession($emp, [], 'mobile');
        $this->assertTrue($checkIn['success']);

        $open = $service->openSession($emp);
        $this->assertNotNull($open);

        $checkOut = $service->endSession($open, [], Carbon::parse('2026-09-10 18:15:00'));
        $this->assertTrue($checkOut['success']);
        $this->assertSame(68, $checkOut['session_duration_minutes']);

        // The main attendance record mirrors first check-in / last closed check-out.
        $att = Attendance::where('employee_id', $emp->id)
            ->where('attendance_date', '2026-09-10')
            ->firstOrFail();
        $this->assertSame('17:07:00', $att->check_in_time);
        $this->assertSame('18:15:00', $att->check_out_time);
        $this->assertSame(68, (int) $att->total_worked_minutes);

        Carbon::setTestNow(null);
    }

    public function test_today_summary_exposes_custom_times_and_deduction(): void
    {
        // Acceptance #2: a custom-attendance employee's session times and shortfall
        // deduction must surface in the dashboard list instead of "--".
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user = User::create([
            'name'     => 'Dashboard Emp',
            'email'    => 'dashboard_' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        $emp = $this->makeEmployee(5000, custom: true);
        $emp->update(['user_id' => $user->id]);

        $att = Attendance::create([
            'employee_id'      => $emp->id,
            'attendance_date'  => '2026-09-10',
            'status'           => 'present',
            'required_hours'   => 8,
        ]);

        AttendanceLog::create([
            'employee_id'     => $emp->id,
            'attendance_id'   => $att->id,
            'log_date'        => '2026-09-10',
            'check_in_time'   => '09:10:28',
            'check_out_time'  => '15:54:21',
            'duration_minutes'=> 404,
            'source'          => 'mobile',
        ]);

        app(CustomAttendanceService::class)->recalculateDay($att->id);

        $response = $this->actingAs($user)->get('/api/attendance/today-summary');
        $response->assertOk();
        $payload = json_decode($response->getContent(), true);

        $row = collect($payload['data']['lists']['present'])->firstWhere('id', $att->id);
        $this->assertNotNull($row, 'Custom employee must appear in today dashboard');
        $this->assertSame('09:10:28', $row['check_in_time']);
        $this->assertSame('15:54:21', $row['check_out_time']);
        $this->assertGreaterThan(0, (float) $row['deduction_amount']);

        Carbon::setTestNow(null);
    }

    public function test_auto_close_20h_old_session_updates_dashboard(): void
    {
        // A custom-attendance session opened 21 hours ago (log_date 2026-09-09,
        // check-in 10:00) must be auto-closed when the request/sweep runs on
        // 2026-09-10 07:00 (21h later). After closing, the note, the dashboard
        // check-out time and the day's worked total must all be updated.
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));

        $emp     = $this->makeEmployee(5000, custom: true);
        $service = app(CustomAttendanceService::class);

        // Day 1, 10:00 — open a session.
        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => '2026-09-09',
            'status'          => 'present',
            'required_hours'  => 8,
        ]);

        $log = AttendanceLog::create([
            'employee_id'   => $emp->id,
            'attendance_id' => $att->id,
            'log_date'      => '2026-09-09',
            'check_in_time' => '10:00:00',
            'source'        => 'mobile',
            'duration_minutes' => 0,
        ]);

        $this->assertTrue($log->isOpen());
        $this->assertNull($att->check_in_time, 'Dashboard main record not synced until first close');

        // 21 hours later the sweep must close it exactly at check-in + 20h = 06:00.
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00'));
        $closedCount = $service->autoCloseStaleSessions($emp->id);

        $this->assertSame(1, $closedCount);

        $closed = $log->fresh();
        $this->assertFalse($closed->isOpen());
        $this->assertSame('06:00:00', $closed->check_out_time);
        $this->assertSame(1200, $closed->duration_minutes);
        $this->assertSame(CustomAttendanceService::AUTO_CLOSED_NOTE, $closed->notes);

        // Dashboard main record reflects the synced times (first in / last out).
        $dag = $att->fresh();
        $this->assertSame('10:00:00', $dag->check_in_time);
        $this->assertSame('06:00:00', $dag->check_out_time);
        // 20h close window → 1200 minutes of worked time, via recalculateDay.
        $this->assertSame(1200, (int) $dag->total_worked_minutes);

        Carbon::setTestNow(null);
    }
}
