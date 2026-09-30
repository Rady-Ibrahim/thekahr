<?php

namespace Tests\Feature;

use App\Models\Advance;
use App\Models\Allowance;
use App\Models\Attendance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Models\Incentive;
use App\Models\SalaryComponentLog;
use App\Services\SalaryBreakdownService;
use App\Services\SalaryCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Guards the payroll equation that used to break: the salary cards were computed
 * live while `net_salary` came from a stale stored snapshot, so the statement
 * contradicted itself (e.g. 6000 + 10 - 0 = 5760 for September 2026).
 *
 * The contract locked here:
 *   gross_salary        = base_salary + total_additions
 *   total_deductions    = approved direct deductions + attendance penalties
 *                         (late / early exit / absence days)
 *   total_all_deductions= total_deductions + points_debit + advances + violations
 *   net_salary          = gross_salary - total_all_deductions
 */
class SalaryBreakdownConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = 9;
    private const YEAR  = 2026;

    private static int $seq = 0;

    private function makeEmployee(float $salary = 6000): Employee
    {
        self::$seq++;

        return Employee::create([
            'employee_code' => 'SB' . self::$seq . '_' . uniqid(),
            'name'          => 'Breakdown Emp ' . self::$seq,
            'phone'         => '010' . str_pad((string) self::$seq, 8, '0', STR_PAD_LEFT) . substr(uniqid(), -4),
            'position'      => 'Test',
            'department'    => 'Test',
            'joining_date'  => now()->subMonth()->toDateString(),
            'base_salary'   => $salary,
            'status'        => 'active',
        ]);
    }

    private function dailyRate(float $baseSalary, int $month, int $year): float
    {
        $start = Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();
        $days  = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (!$d->isWeekend()) $days++;
        }

        return $days > 0 ? round($baseSalary / $days, 2) : 0.0;
    }

    private function attendance(Employee $employee, string $date, string $status = 'present', float $penalty = 0.0, int $late = 0, int $early = 0): Attendance
    {
        return Attendance::create([
            'employee_id'        => $employee->id,
            'attendance_date'    => $date,
            'check_in_time'      => '08:00:00',
            'check_out_time'     => '17:00:00',
            'status'             => $status,
            'late_minutes'       => $late,
            'early_exit_minutes' => $early,
            'deduction_amount'   => $penalty,
            'hours_status'       => Attendance::HOURS_FULFILLED,
        ]);
    }

    private function advance(Employee $employee, float $total, float $installment, string $date = '2026-09-01'): Advance
    {
        return Advance::create([
            'employee_id'          => $employee->id,
            'amount'               => $total,
            'advance_date'         => $date,
            'installments_count'   => (int) round($total / $installment),
            'installment_amount'   => $installment,
            'paid_installments'    => 0,
            'remaining_installments' => (int) round($total / $installment),
            'remaining_amount'     => $total,
            'status'               => 'active',
            'reason'               => 'سلفة اختبار',
        ]);
    }

    public function test_breakdown_equation_balances_across_every_component_type(): void
    {
        $employee = $this->makeEmployee(6000);
        $rate     = $this->dailyRate(6000, self::MONTH, self::YEAR);

        Incentive::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 100, 'incentive_type' => 'أداء', 'status' => 'approved']);
        Allowance::create(['employee_id' => $employee->id, 'allowance_type' => 'بدل', 'amount' => 50, 'start_date' => '2026-09-01', 'status' => 'active']);
        EmployeePoint::create(['employee_id' => $employee->id, 'type' => 'credit', 'points' => 10, 'point_price' => 5, 'total_amount' => 50, 'reason' => 'نقاط أداء', 'month' => self::MONTH, 'year' => self::YEAR]);
        EmployeePoint::create(['employee_id' => $employee->id, 'type' => 'debit', 'points' => 4, 'point_price' => 5, 'total_amount' => 20, 'reason' => 'نقاط مخالفة', 'month' => self::MONTH, 'year' => self::YEAR]);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 300, 'deduction_type' => 'غياب', 'reason' => 'خصم يدوي', 'status' => 'approved']);
        $this->advance($employee, 1000, 100);
        $this->attendance($employee, '2026-09-14', 'present', 50, 30, 0);
        $this->attendance($employee, '2026-09-15', 'absent');

        $breakdown = app(SalaryBreakdownService::class)->build($employee, self::MONTH, self::YEAR);
        $totals    = app(SalaryBreakdownService::class)->reconcile($breakdown);

        $expectedAttendance = round(50 + $rate, 2);
        $expectedDeductions = round(300 + $expectedAttendance, 2);
        $expectedAll        = round($expectedDeductions + 20 + 100, 2);

        $this->assertSame(200.0, $breakdown['additions']['total'], 'additions = incentives + allowances + points credit');
        $this->assertSame(6200.0, $breakdown['gross_salary']);
        $this->assertSame(300.0, $breakdown['deductions']['direct']);
        $this->assertSame($expectedAttendance, $breakdown['deductions']['attendance']);
        $this->assertSame($expectedDeductions, $breakdown['deductions']['total'], 'total_deductions = direct + attendance');
        $this->assertSame($expectedAll, $totals['total_all_deductions'], 'grand total also folds in points debit + advance');
        $this->assertSame(round(6200 - $expectedAll, 2), $totals['net_salary']);
        $this->assertTrue($totals['balances'], 'the statement must add up');
    }

    public function test_saved_salary_row_is_internally_consistent(): void
    {
        $employee = $this->makeEmployee(4800);

        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 369, 'deduction_type' => 'غياب', 'reason' => 'غياب يدوي', 'status' => 'approved']);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 1000, 'deduction_type' => 'أخرى', 'reason' => 'خصم يدوي', 'status' => 'approved']);
        Incentive::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 55, 'incentive_type' => 'أداء', 'status' => 'approved']);
        EmployeePoint::create(['employee_id' => $employee->id, 'type' => 'credit', 'points' => 158, 'point_price' => 5, 'total_amount' => 790, 'reason' => 'نقاط', 'month' => self::MONTH, 'year' => self::YEAR]);
        $this->advance($employee, 10000, 1000, '2026-09-01');
        // mirrors the real September 2026 case: 5 deducted penalty days x 50
        $this->attendance($employee, '2026-09-13', 'present', 50, 20, 0);
        $this->attendance($employee, '2026-09-15', 'present', 50, 0, 20);
        $this->attendance($employee, '2026-09-18', 'present', 50, 10, 0);
        $this->attendance($employee, '2026-09-21', 'present', 50, 25, 0);
        $this->attendance($employee, '2026-09-22', 'present', 50, 0, 15);

        $salary = app(SalaryCalculationService::class)->calculate($employee, self::MONTH, self::YEAR);

        $this->assertEqualsWithDelta(5645.0, (float) $salary->gross_salary, 0.01);
        $this->assertEqualsWithDelta(1619.0, (float) $salary->total_deductions, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $salary->total_advances, 0.01);
        $this->assertEqualsWithDelta(3026.0, (float) $salary->net_salary, 0.01);

        $this->assertEqualsWithDelta(
            (float) $salary->net_salary,
            (float) $salary->gross_salary
                - (float) $salary->total_deductions
                - (float) $salary->total_points_debit
                - (float) $salary->total_advances
                - (float) $salary->total_violations,
            0.01,
            'net_salary must equal gross minus every deduction bucket'
        );
    }

    public function test_every_deducted_penalty_day_is_logged_as_its_own_row(): void
    {
        $employee = $this->makeEmployee(6000);
        $rate     = $this->dailyRate(6000, self::MONTH, self::YEAR);

        $this->attendance($employee, '2026-09-14', 'present', 50, 30, 0);
        $this->attendance($employee, '2026-09-15', 'present', 100, 60, 0);
        $this->attendance($employee, '2026-09-16', 'absent');

        $salary = app(SalaryCalculationService::class)->calculate($employee, self::MONTH, self::YEAR);

        $rows = SalaryComponentLog::where('salary_id', $salary->id)
            ->where('component_type', 'attendance_deduction')
            ->orderBy('id')->get();

        // one row per charged day (2 penalty days + 1 absence day), never one lump sum
        $this->assertCount(3, $rows);
        $this->assertStringContainsString('2026-09-14', $rows[0]->component_name);
        $this->assertStringContainsString('2026-09-16', $rows[2]->component_name);
        $this->assertEqualsWithDelta((float) $salary->total_deductions, abs((float) $rows->sum('amount')), 0.01);
        $this->assertEqualsWithDelta(round(50 + 100 + $rate, 2), (float) $salary->total_deductions, 0.01);

        // each attendance row keeps its own reason for the statement / modal
        $this->assertStringContainsString('تأخير', (string) $rows[0]->notes);
        $this->assertStringContainsString('غياب', (string) $rows[2]->notes);
    }

    public function test_pending_deductions_are_excluded_from_totals(): void
    {
        $employee = $this->makeEmployee(5000);

        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 400, 'deduction_type' => 'معتمد', 'reason' => 'معتمد', 'status' => 'approved']);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 999, 'deduction_type' => 'معلق', 'reason' => 'معلق', 'status' => 'pending']);
        Deduction::create(['employee_id' => $employee->id, 'month' => self::MONTH, 'year' => self::YEAR, 'amount' => 777, 'deduction_type' => 'مرفوض', 'reason' => 'مرفوض', 'status' => 'rejected']);

        $breakdown = app(SalaryBreakdownService::class)->build($employee, self::MONTH, self::YEAR);

        $this->assertSame(400.0, $breakdown['deductions']['direct']);
        $this->assertSame(4600.0, $breakdown['net_salary']);
    }

    public function test_zero_penalty_day_is_not_logged_as_a_deduction_row(): void
    {
        $employee = $this->makeEmployee(5000);

        $this->attendance($employee, '2026-09-14', 'present', 0, 0, 0);
        $this->attendance($employee, '2026-09-15', 'present', 0, 30, 0);

        $salary = app(SalaryCalculationService::class)->calculate($employee, self::MONTH, self::YEAR);

        $this->assertSame(0, SalaryComponentLog::where('salary_id', $salary->id)
            ->where('component_type', 'attendance_deduction')->count());
        $this->assertEqualsWithDelta(5000.0, (float) $salary->net_salary, 0.01);
    }
}
