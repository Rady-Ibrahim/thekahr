<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\HRSetting;
use App\Models\Shift;
use App\Models\ShiftEarlyExitRule;
use App\Models\ShiftLateRule;
use App\Services\AttendancePenaltyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EarlyExitOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(array $overrides = []): Employee
    {
        self::$seq++;

        return Employee::create(array_merge([
            'employee_code' => 'EO' . self::$seq . '_' . uniqid(),
            'name'          => 'EO Emp ' . self::$seq,
            'phone'         => '011' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => 5000,
            'status'        => 'active',
        ], $overrides));
    }

    private function makeShift(): Shift
    {
        $shift = Shift::create([
            'name'                => 'Test Shift',
            'start_time'          => '08:00:00',
            'end_time'            => '17:00:00',
            'grace_period_minutes' => 15,
            'is_active'           => true,
        ]);

        ShiftLateRule::create(['shift_id' => $shift->id, 'min_delay_minutes' => 1, 'max_delay_minutes' => 119, 'deduction_type' => 'minutes', 'deduction_value' => 5]);

        ShiftEarlyExitRule::create(['shift_id' => $shift->id, 'min_early_minutes' => 1, 'max_early_minutes' => 59, 'deduction_type' => 'minutes', 'deduction_value' => 5]);
        ShiftEarlyExitRule::create(['shift_id' => $shift->id, 'min_early_minutes' => 60, 'max_early_minutes' => null, 'deduction_type' => 'half_day', 'deduction_value' => 100]);

        return $shift;
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

    private function processEarlyExit(Employee $emp, int $earlyMinutes): Attendance
    {
        $att = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:00:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);

        $processed = app(AttendancePenaltyService::class)->processAttendance($att);

        // Sanity: fixture should produce the requested early-exit minutes.
        $this->assertSame($earlyMinutes, $processed->early_exit_minutes);

        return $processed;
    }

    public function setUp(): void
    {
        parent::setUp();
        // Ensure defaults are clean between tests (cache is not rolled back with the DB).
        Cache::forget('hr_setting.' . HRSetting::EARLY_EXIT_DEDUCTION_ENABLED);
    }

    public function test_early_exit_is_enabled_by_default_and_applies_shift_rules(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = $this->processEarlyExit($emp, 120); // 120 min early → half_day 100

        $this->assertTrue(HRSetting::get(HRSetting::EARLY_EXIT_DEDUCTION_ENABLED, true));
        $this->assertSame('half_day', $att->applied_early_deduction_type);
        $this->assertEqualsWithDelta(100.0, (float) $att->deduction_amount, 0.01);
        $this->assertFalse($att->penalty_overridden);
    }

    public function test_global_disable_stops_early_exit_deduction_for_everyone(): void
    {
        HRSetting::set(HRSetting::EARLY_EXIT_DEDUCTION_ENABLED, false);

        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = $this->processEarlyExit($emp, 120);

        // Minutes are still tracked for reporting but no discount is applied.
        $this->assertSame(120, $att->early_exit_minutes);
        $this->assertNull($att->applied_early_deduction_type);
        $this->assertEquals(0.0, (float) $att->deduction_amount);
    }

    public function test_employee_switch_off_tracks_minutes_without_deduction(): void
    {
        $emp   = $this->makeEmployee(['early_exit_penalty_enabled' => false]);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = $this->processEarlyExit($emp, 120);

        $this->assertFalse($emp->early_exit_penalty_enabled);
        $this->assertSame(120, $att->early_exit_minutes);
        $this->assertNull($att->applied_early_deduction_type);
        $this->assertEquals(0.0, (float) $att->deduction_amount);
    }

    public function test_employee_override_fixed_amount_replaces_shift_rules(): void
    {
        $emp   = $this->makeEmployee([
            'early_exit_deduction_type'  => 'fixed_amount',
            'early_exit_deduction_value' => 75,
        ]);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = $this->processEarlyExit($emp, 120); // shift would give half_day 100 → override 75

        $this->assertSame('fixed_amount', $att->applied_early_deduction_type);
        $this->assertEquals(75.0, (float) $att->deduction_amount);
    }

    public function test_employee_override_percentage_of_salary(): void
    {
        $emp   = $this->makeEmployee([
            'base_salary'                 => 4000,
            'early_exit_deduction_type'  => 'percentage',
            'early_exit_deduction_value' => 2, // 2% of 4000 = 80
        ]);
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $att = $this->processEarlyExit($emp, 120);

        $this->assertSame('percentage', $att->applied_early_deduction_type);
        $this->assertEqualsWithDelta(80.0, (float) $att->deduction_amount, 0.01);
    }

    public function test_update_endpoint_manual_override_skips_recompute(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        // Auto path first: 08:30 → 30 min late, 17:00 → leaves 2h early.
        $record = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:30:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);
        app(AttendancePenaltyService::class)->processAttendance($record);

        $this->assertGreaterThan(0, (float) $record->deduction_amount);
        $this->assertFalse($record->penalty_overridden);

        // Admin forces a small fixed deduction: must be kept verbatim even though new
        // check times would recompute to a much larger deduction.
        $request = Request::create('/api/attendance/' . $record->id, 'PUT', [
            'check_in_time'              => '10:00',
            'check_out_time'             => '11:00',
            'late_minutes'               => 5,
            'early_exit_minutes'         => 5,
            'applied_late_deduction_type' => 'minutes',
            'applied_early_deduction_type' => 'fixed_amount',
            'deduction_amount'           => 20,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $response   = $controller->update($request, $record->id);
        $payload    = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success'], ($payload['message'] ?? 'update failed'));

        $saved = Attendance::find($record->id);

        $this->assertEquals(20.0, (float) $saved->deduction_amount);
        $this->assertTrue($saved->penalty_overridden);
        $this->assertSame(5, $saved->late_minutes);
        $this->assertSame(5, $saved->early_exit_minutes);
        $this->assertSame('minutes', $saved->applied_late_deduction_type);
        $this->assertSame('fixed_amount', $saved->applied_early_deduction_type);
    }

    public function test_update_recompute_resets_override_flag(): void
    {
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();
        $this->assignShift($emp, $shift);

        $record = Attendance::create([
            'employee_id'     => $emp->id,
            'attendance_date' => now()->toDateString(),
            'check_in_time'   => '08:30:00',
            'check_out_time'  => '15:00:00',
            'status'          => 'present',
        ]);
        $record->update([
            'deduction_amount' => 99,
            'penalty_overridden' => true,
        ]);

        // Saving without a deduction_amount → normal recompute, flag cleared.
        $request = Request::create('/api/attendance/' . $record->id, 'PUT', [
            'notes' => 'recompute me',
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $controller = new AttendanceController(app(AttendancePenaltyService::class));
        $controller->update($request, $record->id);

        $saved = Attendance::find($record->id);

        $this->assertFalse($saved->penalty_overridden);
        $this->assertSame(120, $saved->early_exit_minutes);
        $this->assertGreaterThan(0.0, (float) $saved->deduction_amount);
    }
}