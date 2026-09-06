<?php

namespace Tests\Feature;

use App\Models\Allowance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use App\Services\SalaryCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EmployeeShiftAllowanceTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(float $salary = 5000): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'SV' . self::$seq . '_' . uniqid(),
            'name'          => 'Shift Value Emp ' . self::$seq,
            'phone'         => '011' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => '2026-01-01',
            'base_salary'   => $salary,
            'status'        => 'active',
        ]);
    }

    private function makeShift(): Shift
    {
        return Shift::create([
            'name'                 => 'Temporary Night Shift',
            'start_time'           => '20:00:00',
            'end_time'             => '04:00:00',
            'grace_period_minutes' => 15,
            'is_active'            => true,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'HR Admin',
            'email'    => 'hr_' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    public function test_store_with_shift_value_creates_linked_active_allowance_that_counts_in_salary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'    => $emp->id,
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-01',
            'effective_to'   => '2026-09-30',
            'shift_value'    => 300,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $assignment = EmployeeShift::where('employee_id', $emp->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame('2026-09-01', $assignment->effective_from->toDateString());
        $this->assertSame('2026-09-30', $assignment->effective_to->toDateString());

        $allowance = Allowance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($allowance);
        $this->assertSame('بدل وردية', $allowance->allowance_type);
        $this->assertSame(300.0, (float) $allowance->amount);
        $this->assertSame('active', $allowance->status);
        $this->assertSame('2026-09-01', $allowance->start_date->toDateString());
        $this->assertSame('2026-09-30', $allowance->end_date->toDateString());

        $salary = app(SalaryCalculationService::class)->calculate($emp, 9, 2026);
        $this->assertSame(5300.0, (float) $salary->gross_salary);
        $this->assertContains('بدل وردية', $salary->components->pluck('component_name')->all());

        Carbon::setTestNow(null);
    }

    public function test_store_without_shift_value_does_not_create_allowance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'    => $emp->id,
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-01',
            'effective_to'   => '2026-09-30',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('allowances', [
            'employee_id' => $emp->id,
            'allowance_type' => 'بدل وردية',
        ]);

        Carbon::setTestNow(null);
    }

    public function test_destroy_assignment_removes_linked_shift_allowance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        $assignment = EmployeeShift::create([
            'employee_id'    => $emp->id,
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-01',
            'effective_to'   => '2026-09-30',
        ]);

        $allowance = Allowance::create([
            'employee_id'    => $emp->id,
            'allowance_type' => 'بدل وردية',
            'amount'         => 300.0,
            'start_date'     => '2026-09-01',
            'end_date'       => '2026-09-30',
            'recurring'      => false,
            'status'         => 'active',
            'notes'          => 'مرتبطة بتعيين وردية #' . $assignment->id,
        ]);

        $this->actingAs($this->makeUser())->deleteJson('/api/employee-shifts/' . $assignment->id)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('allowances', ['id' => $allowance->id]);

        Carbon::setTestNow(null);
    }

    public function test_store_with_extra_hours_and_hourly_rate_single_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'    => $emp->id,
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-10',
            'effective_to'   => '2026-09-10',
            'extra_hours'    => 2,
            'hourly_rate'    => 50,
        ])->assertStatus(201)->assertJsonPath('success', true);

        $allowance = Allowance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($allowance);
        $this->assertSame('بدل وردية', $allowance->allowance_type);
        $this->assertSame(100.0, (float) $allowance->amount);
        $this->assertSame('2026-09-10', $allowance->start_date->toDateString());
        $this->assertSame('2026-09-10', $allowance->end_date->toDateString());
        $this->assertStringContainsString('2 ساعة/يوم', $allowance->notes);
        $this->assertStringContainsString('مرتبطة بتعيين وردية #', $allowance->notes);

        $salary = app(SalaryCalculationService::class)->calculate($emp, 9, 2026);
        $this->assertSame(5100.0, (float) $salary->gross_salary);
        $this->assertContains('بدل وردية', $salary->components->pluck('component_name')->all());

        Carbon::setTestNow(null);
    }

    public function test_store_with_extra_hours_and_hourly_rate_multiple_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'    => $emp->id,
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-10',
            'effective_to'   => '2026-09-14',
            'extra_hours'    => 2,
            'hourly_rate'    => 50,
        ])->assertStatus(201)->assertJsonPath('success', true);

        $allowance = Allowance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($allowance);
        $this->assertSame(500.0, (float) $allowance->amount);
        $this->assertSame('2026-09-10', $allowance->start_date->toDateString());
        $this->assertSame('2026-09-14', $allowance->end_date->toDateString());
        $this->assertStringContainsString('2 ساعة/يوم × 5 يوم', $allowance->notes);

        $salary = app(SalaryCalculationService::class)->calculate($emp, 9, 2026);
        $this->assertSame(5500.0, (float) $salary->gross_salary);

        Carbon::setTestNow(null);
    }

    public function test_bulk_store_with_extra_hours_calculates_correctly_for_all_employees(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $shift = $this->makeShift();

        $employees = [$this->makeEmployee(), $this->makeEmployee(), $this->makeEmployee()];

        $this->actingAs($user)->postJson('/api/employee-shifts/bulk', [
            'employee_ids'   => collect($employees)->pluck('id')->all(),
            'shift_id'       => $shift->id,
            'effective_from' => '2026-09-10',
            'effective_to'   => '2026-09-11',
            'extra_hours'    => 3,
            'hourly_rate'    => 40,
        ])->assertStatus(201)->assertJsonPath('success', true);

        foreach ($employees as $emp) {
            $allowance = Allowance::where('employee_id', $emp->id)->first();
            $this->assertNotNull($allowance);
            $this->assertSame(240.0, (float) $allowance->amount);
            $this->assertStringContainsString('3 ساعة/يوم × 2 يوم', $allowance->notes);

            $salary = app(SalaryCalculationService::class)->calculate($emp, 9, 2026);
            $this->assertSame(5240.0, (float) $salary->gross_salary);
        }

        Carbon::setTestNow(null);
    }

    public function test_open_ended_shift_with_extra_start_only_is_single_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        // Shift assignment is open-ended (effective_to is null), but the extra hours
        // are for the start day only (6/9) → 2h × 50 × 1 day = 100.
        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'       => $emp->id,
            'shift_id'          => $shift->id,
            'effective_from'    => '2026-09-01',
            'effective_to'      => null,
            'extra_hours'       => 2,
            'hourly_rate'       => 50,
            'extra_start_date'  => '2026-09-06',
        ])->assertStatus(201)->assertJsonPath('success', true);

        $assignment = EmployeeShift::where('employee_id', $emp->id)->first();
        $this->assertNull($assignment->effective_to);

        $allowance = Allowance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($allowance);
        $this->assertSame(100.0, (float) $allowance->amount);
        $this->assertSame('2026-09-06', $allowance->start_date->toDateString());
        $this->assertSame('2026-09-06', $allowance->end_date->toDateString());
        $this->assertStringContainsString('2 ساعة/يوم', $allowance->notes);

        $salary = app(SalaryCalculationService::class)->calculate($emp, 9, 2026);
        $this->assertSame(5100.0, (float) $salary->gross_salary);

        Carbon::setTestNow(null);
    }

    public function test_dedicated_extra_date_range_overrides_open_shift_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $user  = $this->makeUser();
        $emp   = $this->makeEmployee();
        $shift = $this->makeShift();

        // Open shift, but the extra-hours window is 2026-09-10 → 2026-09-11 (2 days)
        // → 2h × 50 × 2 = 200.
        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id'       => $emp->id,
            'shift_id'          => $shift->id,
            'effective_from'    => '2026-09-01',
            'extra_hours'       => 2,
            'hourly_rate'       => 50,
            'extra_start_date'  => '2026-09-10',
            'extra_end_date'    => '2026-09-11',
        ])->assertStatus(201)->assertJsonPath('success', true);

        $allowance = Allowance::where('employee_id', $emp->id)->first();
        $this->assertNotNull($allowance);
        $this->assertSame(200.0, (float) $allowance->amount);
        $this->assertSame('2026-09-10', $allowance->start_date->toDateString());
        $this->assertSame('2026-09-11', $allowance->end_date->toDateString());
        $this->assertStringContainsString('2 ساعة/يوم × 2 يوم', $allowance->notes);

        Carbon::setTestNow(null);
    }
}