<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\FinancialController;
use App\Models\Advance;
use App\Models\Attendance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Models\Incentive;
use App\Models\Salary;
use App\Services\SalaryCalculationService;
use App\Services\SalaryBreakdownService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The financial statement API is consumed by the mobile app, so the legacy keys
 * must keep their ORIGINAL meaning:
 *
 *   estimated_net = gross
 *                 - deductions_total            (direct only!)
 *                 - attendance_deduction_total
 *                 - points_debit_total
 *                 - advances_installment_total
 *
 * Folding attendance into `deductions_total` silently double counts attendance for
 * every existing client, so the combined figure is exposed under the new explicit
 * `total_deductions` key instead.
 */
class FinancialStatementApiTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = 9;
    private const YEAR  = 2026;

    private static int $seq = 0;

    private function makeEmployee(): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'FS' . self::$seq . '_' . uniqid(),
            'name'          => 'Fin Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => 6000,
            'status'        => 'active',
        ]);
    }

    private function seedMonth(Employee $employee): void
    {
        Incentive::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 100, 'incentive_type' => 'أداء', 'status' => 'approved']);
        EmployeePoint::create(['employee_id' => $employee->id, 'type' => 'credit', 'points' => 10, 'point_price' => 5, 'total_amount' => 50, 'reason' => 'نقاط', 'month' => self::MONTH, 'year' => self::YEAR]);
        EmployeePoint::create(['employee_id' => $employee->id, 'type' => 'debit', 'points' => 4, 'point_price' => 5, 'total_amount' => 20, 'reason' => 'نقاط', 'month' => self::MONTH, 'year' => self::YEAR]);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 300, 'deduction_type' => 'غياب', 'reason' => 'يدوي', 'status' => 'approved']);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 500, 'deduction_type' => 'معلق', 'reason' => 'معلق', 'status' => 'pending']);
        Advance::create([
            'employee_id' => $employee->id, 'amount' => 3000, 'advance_date' => '2026-09-01',
            'installments_count' => 3, 'installment_amount' => 1000, 'paid_installments' => 0,
            'remaining_installments' => 3, 'remaining_amount' => 3000, 'status' => 'active', 'reason' => 'سلفة',
        ]);
        foreach ([['2026-09-14', 50], ['2026-09-15', 70]] as [$date, $penalty]) {
            Attendance::create([
                'employee_id' => $employee->id, 'attendance_date' => $date,
                'check_in_time' => '08:00:00', 'check_out_time' => '17:00:00',
                'status' => 'present', 'late_minutes' => 10, 'deduction_amount' => $penalty,
                'hours_status' => Attendance::HOURS_FULFILLED,
            ]);
        }
    }

    private function summaryFor(Employee $employee): array
    {
        $response = app(FinancialController::class)->employeeFinancials(
            $employee->id,
            Request::create('/', 'GET', ['month' => self::MONTH, 'year' => self::YEAR])
        );

        $this->assertSame(200, $response->getStatusCode());

        return json_decode($response->getContent(), true)['summary'];
    }

    public function test_legacy_keys_keep_their_original_meaning(): void
    {
        $employee = $this->makeEmployee();
        $this->seedMonth($employee);

        $s = $this->summaryFor($employee);

        $this->assertEqualsWithDelta(100.0, $s['incentives_total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['allowances_total'], 0.01);
        $this->assertEqualsWithDelta(50.0, $s['points_credit_total'], 0.01);
        $this->assertEqualsWithDelta(20.0, $s['points_debit_total'], 0.01);
        // direct rows ONLY - attendance must stay out so legacy clients that also
        // subtract attendance_deduction_total don't count it twice
        $this->assertEqualsWithDelta(300.0, $s['deductions_total'], 0.01);
        $this->assertEqualsWithDelta(120.0, $s['attendance_deduction_total'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $s['advances_installment_total'], 0.01);
    }

    /**
     * NOTE: the historical client-side formula forgot to add `points_credit_total`,
     * so a client recomputing net itself lands 50 low here. `estimated_net` is the
     * authoritative value and is correct. This test pins the gap so it cannot be
     * closed (or widened) silently.
     */
    public function test_legacy_net_formula_gap_is_only_the_missing_points_credit(): void
    {
        $employee = $this->makeEmployee();
        $this->seedMonth($employee);

        $s = $this->summaryFor($employee);

        $legacyNet = max(0, round(
            $s['base_salary']
            + $s['incentives_total']
            + $s['allowances_total']
            - $s['deductions_total']
            - $s['attendance_deduction_total']
            - $s['points_debit_total']
            - $s['advances_installment_total'],
            2
        ));

        $this->assertEqualsWithDelta(6150.0, $s['gross_salary'], 0.01);
        $this->assertEqualsWithDelta(4710.0, $s['estimated_net'], 0.01, 'estimated_net is authoritative and correct');
        $this->assertEqualsWithDelta($s['net_salary'], $s['estimated_net'], 0.01);
        $this->assertTrue($s['balances']);

        // the legacy formula is short by exactly the credit points it never added
        $this->assertEqualsWithDelta(
            $s['net_salary'] - $s['points_credit_total'],
            $legacyNet,
            0.01,
            'legacy client-side formula is short by points_credit_total - client needs updating'
        );
        $this->assertEqualsWithDelta(
            $s['net_salary'],
            max(0, round(
                $s['base_salary']
                + $s['incentives_total']
                + $s['allowances_total']
                + $s['points_credit_total']
                - $s['deductions_total']
                - $s['attendance_deduction_total']
                - $s['points_debit_total']
                - $s['advances_installment_total'],
                2
            )),
            0.01,
            'adding points_credit_total makes the client-side formula agree with the server'
        );
    }

    public function test_new_explicit_keys_expose_the_agreed_totals(): void
    {
        $employee = $this->makeEmployee();
        $this->seedMonth($employee);

        $s = $this->summaryFor($employee);

        $this->assertEqualsWithDelta(150.0, $s['total_additions'], 0.01);
        $this->assertEqualsWithDelta(420.0, $s['total_deductions'], 0.01, 'total_deductions = direct (300) + attendance (120)');
        $this->assertEqualsWithDelta(1440.0, $s['total_all_deductions'], 0.01);
        $this->assertEqualsWithDelta(4710.0, $s['net_salary'], 0.01);
    }

    public function test_stale_flag_is_set_when_the_saved_salary_no_longer_matches(): void
    {
        $employee = $this->makeEmployee();
        $this->seedMonth($employee);

        app(SalaryCalculationService::class)->calculate($employee, self::MONTH, self::YEAR);
        $this->assertFalse($this->summaryFor($employee)['is_stale'], 'freshly calculated row is not stale');

        // a transaction added after the salary was saved must surface as stale
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 250, 'deduction_type' => 'جديد', 'reason' => 'لاحق', 'status' => 'approved']);

        $s = $this->summaryFor($employee);
        $this->assertTrue($s['is_stale']);
        $this->assertNotEqualsWithDelta($s['salary_net'], $s['estimated_net'], 0.01);

        // and the stored snapshot must never leak into the live net
        $this->assertEqualsWithDelta(6150 - 1690, $s['net_salary'], 0.01);
    }

    public function test_api_net_never_falls_back_to_a_stale_saved_row(): void
    {
        $employee = $this->makeEmployee();
        $this->seedMonth($employee);

        // save a deliberately wrong snapshot
        Salary::create([
            'employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR,
            'base_salary' => 6000, 'gross_salary' => 6010, 'total_deductions' => 250,
            'net_salary' => 5760, 'status' => 'draft',
        ]);

        $breakdown = app(SalaryBreakdownService::class)->build($employee, self::MONTH, self::YEAR);

        $this->assertEqualsWithDelta(6150.0, $breakdown['gross_salary'], 0.01);
        $this->assertEqualsWithDelta(4710.0, $breakdown['net_salary'], 0.01);

        $s = $this->summaryFor($employee);
        $this->assertEqualsWithDelta(5760.0, $s['salary_net'], 0.01, 'stored value is still reported, just flagged');
        $this->assertEqualsWithDelta(4710.0, $s['estimated_net'], 0.01);
        $this->assertTrue($s['is_stale']);
    }
}
