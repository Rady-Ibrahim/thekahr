<?php

namespace Tests\Feature;

use App\Console\Commands\RepairAdvancesCommand;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Salary;
use App\Models\SalaryComponentLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The repair command must rebuild the advance counters from REAL disbursement
 * evidence only (a paid salary that charged the advance), never from the
 * corrupted columns themselves.
 */
class RepairAdvancesCommandTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'RP' . self::$seq . '_' . uniqid(),
            'name'          => 'Repair Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => '2026-01-01',
            'base_salary'   => 6000,
            'status'        => 'active',
        ]);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function makeAdvance(Employee $employee, array $overrides = []): Advance
    {
        $base = [
            'employee_id'          => $employee->id,
            'amount'               => 10000.0,
            'advance_date'         => '2026-01-01',
            'installments_count'   => 10,
            'installment_amount'   => 1000.0,
            'paid_installments'    => 0,
            'remaining_installments' => 10,
            'remaining_amount'     => 10000.0,
            'status'               => 'active',
            'reason'               => 'سلفة',
        ];

        return Advance::create(array_merge($base, $overrides));
    }

    /**
     * @return array{0: Salary, 1: SalaryComponentLog}
     */
    private function paidSalaryCharging(Employee $employee, Advance $advance, string $status = 'paid', int $month = 9): array
    {
        $salary = Salary::create([
            'employee_id' => $employee->id,
            'month'       => $month,
            'year'        => 2026,
            'base_salary' => 6000,
            'gross_salary' => 6000,
            'total_deductions' => 0,
            'total_advances'   => (float) $advance->installment_amount,
            'net_salary'  => 6000 - (float) $advance->installment_amount,
            'status'      => $status,
        ]);

        $log = SalaryComponentLog::create([
            'salary_id'      => $salary->id,
            'component_type' => 'advance',
            'component_name' => 'قسط سلفة',
            'component_id'   => $advance->id,
            'amount'         => -1 * (float) $advance->installment_amount,
        ]);

        return [$salary, $log];
    }

    private function runRepair(bool $apply = true): int
    {
        // Artisan::call() runs non-interactively, so the "Write these corrections
        // now?" confirmation falls back to its default (yes) without needing an
        // expectation - which also works when a case has nothing to change.
        return Artisan::call('payroll:repair-advances', array_filter([
            '--apply'  => $apply,
            '--backup' => sys_get_temp_dir(),
        ], fn($v) => $v !== false));
    }

    public function test_resets_an_advance_wiped_by_calculations_without_any_payment(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'paid_installments'      => 10,
            'remaining_installments' => 0,
            'remaining_amount'       => 0,
            'status'                 => 'paid',
        ]);

        $this->runRepair();

        $advance->refresh();
        $this->assertEqualsWithDelta(0, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(10, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(10000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('active', $advance->status, 'reopened because nothing was ever collected');
    }

    public function test_keeps_exactly_the_installments_backed_by_paid_salaries(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'amount'                 => 10000.0,
            'installments_count'     => 10,
            'installment_amount'     => 1000.0,
            'paid_installments'      => 10,   // over-decremented
            'remaining_installments' => 0,
            'remaining_amount'       => 0,
            'status'                 => 'paid',
        ]);

        // three months were really disbursed
        for ($i = 1; $i <= 3; $i++) {
            $this->paidSalaryCharging($employee, $advance, 'paid', $i);
        }

        $this->runRepair();

        $advance->refresh();
        $this->assertEqualsWithDelta(3, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(7, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(7000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('partially_paid', $advance->status);
    }

    public function test_ignores_draft_and_approved_salaries_as_evidence(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'paid_installments'      => 4,
            'remaining_installments' => 6,
            'remaining_amount'       => 6000.0,
            'status'                 => 'partially_paid',
        ]);

        // never disbursed - these must not count
        $this->paidSalaryCharging($employee, $advance, 'draft', 1);
        $this->paidSalaryCharging($employee, $advance, 'approved', 2);

        $this->runRepair();

        $advance->refresh();
        $this->assertEqualsWithDelta(0, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(10000.0, $advance->remaining_amount, 0.01);
    }

    public function test_does_not_count_the_same_salary_twice(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'paid_installments'      => 2,
            'remaining_installments' => 8,
            'remaining_amount'       => 8000.0,
            'status'                 => 'partially_paid',
        ]);

        // duplicate component log rows on one salary = still one installment
        [$salary] = $this->paidSalaryCharging($employee, $advance);
        SalaryComponentLog::create([
            'salary_id' => $salary->id, 'component_type' => 'advance',
            'component_name' => 'قسط سلفة', 'component_id' => $advance->id, 'amount' => -1000,
        ]);

        $this->runRepair();

        $advance->refresh();
        $this->assertEqualsWithDelta(1, $advance->paid_installments, 0.01);
    }

    public function test_partially_collected_advance_keeps_pending_status_when_nothing_paid(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'paid_installments'      => 0,
            'remaining_installments' => 10,
            'remaining_amount'       => 10000.0,
            'status'                 => 'pending',
        ]);

        $this->runRepair();

        $advance->refresh();
        $this->assertSame('pending', $advance->status, 'a not-yet-approved advance stays pending');
    }

    public function test_genuinely_settled_advance_is_left_alone(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, [
            'installments_count'     => 10,
            'paid_installments'      => 10,
            'remaining_installments' => 0,
            'remaining_amount'       => 0,
            'status'                 => 'paid',
        ]);

        for ($i = 1; $i <= 10; $i++) {
            $this->paidSalaryCharging($employee, $advance, 'paid', $i);
        }

        $this->runRepair();

        $advance->refresh();
        $this->assertSame('paid', $advance->status);
        $this->assertEqualsWithDelta(0, $advance->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(10, $advance->paid_installments, 0.01);
    }
}
