<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Salary;
use App\Models\User;
use App\Services\AdvanceDeductionService;
use App\Services\SalaryCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression guard for the production incident where every advance ended up with
 * remaining_amount = 0 and status = "مسدد" despite being split over 10
 * installments.
 *
 * Root cause: SalaryCalculationService::calculate() decremented every active
 * advance by one installment on each run of the "حساب الرواتب" button, whether or
 * not the salary was approved or paid. Calculating N months consumed N
 * installments, so a 10-month advance was zeroed after 10 calculation runs.
 *
 * The rule now enforced:
 *   - calculating a salary NEVER touches an advance
 *   - the installment is consumed exactly once, when the salary is PAID
 *   - re-paying can never consume a second installment
 *   - status becomes 'paid' only when remaining_amount really reaches 0
 */
class AdvanceDeductionTest extends TestCase
{
    use DatabaseTransactions;

    private static int $seq = 0;

    private function makeEmployee(): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'ADV' . self::$seq . '_' . uniqid(),
            'name'          => 'Advance Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => '2026-01-01',
            'base_salary'   => 6000,
            'status'        => 'active',
        ]);
    }

    private function makeAdvance(Employee $employee, int $installments = 10, float $installment = 1000): Advance
    {
        return Advance::create([
            'employee_id'          => $employee->id,
            'amount'               => $installments * $installment,
            'advance_date'         => '2026-01-01',
            'installments_count'   => $installments,
            'installment_amount'   => $installment,
            'paid_installments'    => 0,
            'remaining_installments' => $installments,
            'remaining_amount'     => $installments * $installment,
            'status'               => 'active',
            'reason'               => 'سلفة',
        ]);
    }

    public function test_calculating_a_salary_never_consumes_an_installment(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);

        // the installment is still correctly shown as a deduction of this month
        $this->assertEqualsWithDelta(1000.0, (float) $salary->total_advances, 0.01);
        $this->assertSame('draft', $salary->status);

        // ... but the advance itself is untouched until money is actually paid out
        $advance->refresh();
        $this->assertEqualsWithDelta(0, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(10, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(10000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('active', $advance->status);
    }

    public function test_calculating_every_month_of_the_year_does_not_wipe_the_advance(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, 10, 1000);

        for ($month = 1; $month <= 12; $month++) {
            app(SalaryCalculationService::class)->calculate($employee, $month, 2026);
        }

        $advance->refresh();

        $this->assertEqualsWithDelta(0, $advance->paid_installments, 0.01, 'calculating is not collecting');
        $this->assertEqualsWithDelta(10, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(10000.0, $advance->remaining_amount, 0.01);
        $this->assertNotSame('paid', $advance->status);
    }

    public function test_paying_a_salary_consumes_exactly_one_installment(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);
        $salary->update(['status' => 'approved']);

        $result = app(AdvanceDeductionService::class)->deductForSalary($salary->fresh());

        $this->assertSame(1, $result['deducted']);

        $advance->refresh();
        $this->assertEqualsWithDelta(1, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(9, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(9000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('partially_paid', $advance->status);
    }

    public function test_paying_the_same_salary_twice_does_not_double_charge(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);
        $salary->update(['status' => 'approved']);

        $service = app(AdvanceDeductionService::class);
        $this->assertSame(1, $service->deductForSalary($salary->fresh())['deducted']);
        $this->assertSame(0, $service->deductForSalary($salary->fresh())['deducted'], 'idempotent');
        $this->assertSame(0, $service->deductForSalary($salary->fresh())['deducted'], 'still idempotent');

        $advance->refresh();
        $this->assertEqualsWithDelta(1, $advance->paid_installments, 0.01);
        $this->assertEqualsWithDelta(9000.0, $advance->remaining_amount, 0.01);
    }

    public function test_advance_is_only_marked_settled_when_remaining_really_reaches_zero(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, 2, 3500);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);
        $salary->update(['status' => 'approved']);
        app(AdvanceDeductionService::class)->deductForSalary($salary->fresh());

        $advance->refresh();
        $this->assertEqualsWithDelta(1, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(3500.0, $advance->remaining_amount, 0.01);
        $this->assertSame('partially_paid', $advance->status, 'not settled while money is still owed');

        $salary2 = app(SalaryCalculationService::class)->calculate($employee, 10, 2026);
        $salary2->update(['status' => 'approved']);
        app(AdvanceDeductionService::class)->deductForSalary($salary2->fresh());

        $advance->refresh();
        $this->assertEqualsWithDelta(0, $advance->remaining_installments, 0.01);
        $this->assertEqualsWithDelta(0, $advance->remaining_amount, 0.01);
        $this->assertSame('paid', $advance->status, 'settled only now');
    }

    public function test_pay_endpoint_consumes_the_installment_and_is_idempotent(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);
        $salary->update(['status' => 'approved']);

        $controller = app(\App\Http\Controllers\Api\SalaryController::class);

        $first  = $controller->pay(\Illuminate\Http\Request::create('/', 'POST'), $salary->id);
        $second = $controller->pay(\Illuminate\Http\Request::create('/', 'POST'), $salary->id);

        $this->assertSame(200, $first->getStatusCode(), 'the pay button must not 422 without a body');
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame(1, json_decode($first->getContent(), true)['advances_deducted']);
        $this->assertSame(0, json_decode($second->getContent(), true)['advances_deducted']);

        $advance->refresh();
        $this->assertEqualsWithDelta(9000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('paid', Salary::find($salary->id)->status);
    }

    public function test_unpaid_salary_keeps_the_advance_intact_even_after_many_calculations(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee, 10, 1000);

        for ($month = 1; $month <= 10; $month++) {
            app(SalaryCalculationService::class)->calculate($employee, $month, 2026);
        }

        // nothing was disbursed, so nothing may have been collected
        $this->assertSame(0, Salary::where('employee_id', $employee->id)->where('status', 'paid')->count());

        $advance->refresh();
        $this->assertEqualsWithDelta(10000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('active', $advance->status);
    }

    /**
     * Production's last saved salary per exhausted advance has total_advances = 0 and
     * NO 'advance' component row (the old calculate() cascade-deleted the log on every
     * re-run and stopped logging once remaining_installments hit 0). Paying such a
     * salary must still deduct, or the advance would silently keep its full balance.
     */
    public function test_legacy_salary_without_advance_log_rows_still_deducts(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);

        // strip the advance component rows, mimicking the legacy production shape
        \App\Models\SalaryComponentLog::where('salary_id', $salary->id)
            ->where('component_type', 'advance')
            ->delete();

        $this->assertSame(0, \App\Models\SalaryComponentLog::where('salary_id', $salary->id)
            ->where('component_type', 'advance')->count());

        $salary->update(['status' => 'approved']);
        $result = app(AdvanceDeductionService::class)->deductForSalary($salary->fresh());

        $this->assertSame(1, $result['deducted'], 'fallback must still charge the active advance');

        $advance->refresh();
        $this->assertEqualsWithDelta(9000.0, $advance->remaining_amount, 0.01);
        $this->assertSame('partially_paid', $advance->status);
    }

    public function test_advance_created_after_the_salary_is_not_charged_when_log_rows_exist(): void
    {
        $employee = $this->makeEmployee();
        $advance  = $this->makeAdvance($employee);

        $salary = app(SalaryCalculationService::class)->calculate($employee, 9, 2026);

        // a brand new advance the salary never saw
        $newer = $this->makeAdvance($employee, 4, 500);
        $newer->update(['amount' => 2000.0, 'installments_count' => 4, 'installment_amount' => 500.0]);

        $salary->update(['status' => 'approved']);
        app(AdvanceDeductionService::class)->deductForSalary($salary->fresh());

        // the logged advance is charged ...
        $advance->refresh();
        $this->assertEqualsWithDelta(9000.0, $advance->remaining_amount, 0.01);

        // ... and the unrelated newer one is not
        $newer->refresh();
        $this->assertEqualsWithDelta(2000.0, $newer->remaining_amount, 0.01);
        $this->assertEqualsWithDelta(0, $newer->paid_installments, 0.01);
    }

    /**
     * employeeSummary() used to report total_paid as SUM(remaining_amount) over settled
     * advances, which is always 0 by definition - so a fully collected advance looked
     * like nothing had ever been collected.
     */
    public function test_employee_summary_reports_the_amount_actually_collected(): void
    {
        $employee = $this->makeEmployee();

        $settled = $this->makeAdvance($employee, 10, 1000);
        $settled->update([
            'paid_installments'      => 10,
            'remaining_installments' => 0,
            'remaining_amount'       => 0,
            'status'                 => 'paid',
        ]);

        $halfway = $this->makeAdvance($employee, 10, 500);
        $halfway->update([
            'paid_installments'      => 4,
            'remaining_installments' => 6,
            'remaining_amount'       => 3000,
            'status'                 => 'partially_paid',
        ]);

        $response = $this->actingAs($this->makeAdmin())->getJson("/api/advances/employee/{$employee->id}/summary");
        $summary  = $response->json('summary');

        // 10,000 fully collected + (5,000 - 3,000) partially collected
        $this->assertEqualsWithDelta(12000.0, (float) $summary['total_paid'], 0.01);
        $this->assertEqualsWithDelta(3000.0, (float) $summary['total_remaining'], 0.01);
        $this->assertEqualsWithDelta(15000.0, (float) $summary['total_advances'], 0.01);
    }

    /** A rejected advance must not inflate total_advances or total_paid. */
    public function test_employee_summary_excludes_rejected_advances(): void
    {
        $employee = $this->makeEmployee();

        $good = $this->makeAdvance($employee, 10, 1000);
        $good->update([
            'paid_installments'      => 10,
            'remaining_installments' => 0,
            'remaining_amount'       => 0,
            'status'                 => 'paid',
        ]);

        $rejected = $this->makeAdvance($employee, 5, 800);
        $rejected->update(['status' => 'rejected']);

        $summary = $this->actingAs($this->makeAdmin())
            ->getJson("/api/advances/employee/{$employee->id}/summary")
            ->json('summary');

        $this->assertEqualsWithDelta(10000.0, (float) $summary['total_advances'], 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $summary['total_paid'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $summary['total_remaining'], 0.01);
        $this->assertEqualsWithDelta(4000.0, (float) $summary['total_rejected'], 0.01);
        $this->assertSame(0, $summary['active_count']);
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'name'     => 'Payroll Admin',
            'email'    => 'payroll_admin_' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['name' => 'admin']);

        $user->giveRole('admin');

        return $user->fresh();
    }
}
