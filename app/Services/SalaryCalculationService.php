<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Salary;
use App\Models\SalaryComponentLog;
use Illuminate\Support\Facades\DB;

class SalaryCalculationService
{
    public function __construct(private SalaryBreakdownService $breakdownService) {}

    public function calculate(Employee $employee, int $month, int $year): Salary
    {
        DB::beginTransaction();

        try {
            // Delete existing draft salary
            Salary::where('employee_id', $employee->id)
                  ->where('month', $month)
                  ->where('year', $year)
                  ->where('status', 'draft')
                  ->delete();

            // Every figure below comes from the one shared breakdown so the saved
            // row can never disagree with the salary cards / financial statement.
            $breakdown = $this->breakdownService->build($employee, $month, $year);
            $totals    = $this->breakdownService->reconcile($breakdown);

            $salary = Salary::create([
                'employee_id'       => $employee->id,
                'month'             => $month,
                'year'              => $year,
                'base_salary'       => $totals['base_salary'],
                'gross_salary'      => $totals['gross_salary'],
                'total_incentives'  => $breakdown['additions']['incentives'],
                'total_allowances'  => $breakdown['additions']['allowances'],
                'total_commissions' => $breakdown['additions']['commissions'],
                'total_deductions'  => $totals['total_deductions'],
                'total_advances'    => $totals['advances'],
                'total_violations'  => $totals['violations'],
                'total_points_credit' => $breakdown['additions']['points_credit'],
                'total_points_debit'  => $totals['points_debit'],
                'net_salary'        => $totals['net_salary'],
                'status'            => 'draft',
            ]);

            // Log components - every earning AND every deducted item, one row each.
            foreach ($breakdown['components'] as $comp) {
                SalaryComponentLog::create([
                    'salary_id'      => $salary->id,
                    'component_type' => $comp['type'],
                    'component_name' => $comp['name'],
                    'component_id'   => $comp['id'],
                    'amount'         => $comp['amount'],
                    'notes'          => $comp['reason'] ?? null,
                ]);
            }

            // NOTE: advance installments are deliberately NOT touched here.
            // Calculating a salary must never move an advance: it is a read-only
            // projection of the month. The installment is consumed exactly once, at
            // real disbursement time, by AdvanceDeductionService::deductForSalary().
            //
            // Previously this loop ran here, so every press of "حساب الرواتب" burned
            // one installment per advance. Calculating N months zeroed an N-month
            // advance and flipped it to "مسدد" while nothing had been paid.

            DB::commit();

            return $salary->load('components');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function calculateBulk(int $month, int $year, ?array $employeeIds = null): array
    {
        $query = Employee::where('status', 'active');
        if ($employeeIds) $query->whereIn('id', $employeeIds);

        $employees = $query->get();
        $results   = [];

        foreach ($employees as $employee) {
            try {
                $salary    = $this->calculate($employee, $month, $year);
                $results[] = ['employee_id' => $employee->id, 'name' => $employee->name, 'salary_id' => $salary->id, 'net_salary' => $salary->net_salary, 'status' => 'success'];
            } catch (\Exception $e) {
                $results[] = ['employee_id' => $employee->id, 'name' => $employee->name, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
