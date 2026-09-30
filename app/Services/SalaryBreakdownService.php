<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Allowance;
use App\Models\Attendance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Models\Incentive;
use Carbon\Carbon;

/**
 * Single source of truth for every salary figure shown anywhere in the system.
 *
 * The salary cards, the financial statement, the salary detail modal and the
 * persisted `salaries` row must all be produced by this one calculation, otherwise
 * the summary cards drift away from `net_salary` and the statement contradicts
 * itself.
 *
 * The contract is always:
 *
 *   gross_salary           = base_salary + total_additions
 *   total_deductions       = direct_deductions + attendance_deductions (late/early/absence)
 *   total_all_deductions   = total_deductions + points_debit + advances + violations
 *   net_salary             = max(0, gross_salary - total_all_deductions)
 */
class SalaryBreakdownService
{
    public function __construct(private AttendancePenaltyService $attendancePenaltyService) {}

    /**
     * Build the complete, self-consistent breakdown for one employee/month.
     *
     * @return array<string, mixed>
     */
    public function build(Employee $employee, int $month, int $year): array
    {
        $baseSalary = (float) $employee->base_salary;
        $components = [];

        // ── Earnings ────────────────────────────────────────────────────────
        $incentives = Incentive::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('status', 'approved')
            ->orderBy('id')->get();
        $totalIncentives = (float) $incentives->sum('amount');
        foreach ($incentives as $inc) {
            $components[] = $this->component(
                'incentive',
                (string) $inc->incentive_type,
                $inc->id,
                (float) $inc->amount,
                $inc->reason ?? $inc->description,
            );
        }

        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd   = $monthStart->copy()->endOfMonth();
        $allowances = Allowance::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $monthEnd)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart))
            ->orderBy('id')->get();
        $totalAllowances = (float) $allowances->sum('amount');
        foreach ($allowances as $allowance) {
            $components[] = $this->component(
                'allowance',
                (string) $allowance->allowance_type,
                $allowance->id,
                (float) $allowance->amount,
                $allowance->reason,
            );
        }

        $totalPointsCredit = (float) EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('type', 'credit')->sum('total_amount');
        if ($totalPointsCredit > 0) {
            $components[] = $this->component('points_credit', 'مكافأة نقاط (له)', null, $totalPointsCredit);
        }

        // Commissions & car violations were removed with the operations module.
        $totalCommissions = 0.0;
        $totalViolations  = 0.0;

        $totalAdditions = $totalIncentives + $totalAllowances + $totalCommissions + $totalPointsCredit;
        $grossSalary    = $baseSalary + $totalAdditions;

        // ── Deductions: approved manual rows ────────────────────────────────
        $directDeductions = Deduction::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('status', 'approved')
            ->orderBy('id')->get();
        $totalDirectDeductions = (float) $directDeductions->sum('amount');
        foreach ($directDeductions as $deduction) {
            $components[] = $this->component(
                'deduction',
                (string) $deduction->deduction_type,
                $deduction->id,
                -(float) $deduction->amount,
                $deduction->reason,
            );
        }

        $totalPointsDebit = (float) EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('type', 'debit')->sum('total_amount');
        if ($totalPointsDebit > 0) {
            $components[] = $this->component('points_debit', 'خصم نقاط (عليه)', null, -$totalPointsDebit);
        }

        // ── Deductions: attendance (late / early exit / absence), per day ────
        $attendance = $this->attendanceDeduction($employee, $month, $year, $baseSalary);
        foreach ($attendance['rows'] as $row) {
            $components[] = $this->component(
                'attendance_deduction',
                $row['name'],
                $row['attendance_id'],
                -$row['amount'],
                $row['reason'],
            );
        }
        $totalAttendanceDeductions = (float) $attendance['amount'];

        // ── Advances ────────────────────────────────────────────────────────
        $activeAdvances = Advance::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'partially_paid'])
            ->where('remaining_installments', '>', 0)
            ->orderBy('id')->get();
        $totalAdvances = (float) $activeAdvances->sum('installment_amount');
        foreach ($activeAdvances as $advance) {
            $components[] = $this->component(
                'advance',
                'قسط سلفة',
                $advance->id,
                -(float) $advance->installment_amount,
                $advance->reason,
            );
        }

        // ── Totals ──────────────────────────────────────────────────────────
        // total_deductions follows the agreed definition: direct rows + attendance
        // penalties (late / early exit / absence days).
        $totalDeductions = $totalDirectDeductions + $totalAttendanceDeductions;

        $totalAllDeductions = $totalDeductions + $totalPointsDebit + $totalAdvances + $totalViolations;
        $netSalary          = max(0, round($grossSalary - $totalAllDeductions, 2));

        return [
            'month'  => $month,
            'year'   => $year,
            'base_salary' => round($baseSalary, 2),

            'additions' => [
                'incentives'    => round($totalIncentives, 2),
                'allowances'    => round($totalAllowances, 2),
                'commissions'   => round($totalCommissions, 2),
                'points_credit' => round($totalPointsCredit, 2),
                'total'         => round($totalAdditions, 2),
            ],

            'deductions' => [
                'direct'     => round($totalDirectDeductions, 2),
                'attendance' => round($totalAttendanceDeductions, 2),
                'total'      => round($totalDeductions, 2),
                'points_debit' => round($totalPointsDebit, 2),
                'advances'   => round($totalAdvances, 2),
                'violations' => round($totalViolations, 2),
                'grand_total' => round($totalAllDeductions, 2),
            ],

            'gross_salary' => round($grossSalary, 2),
            'net_salary'   => $netSalary,
            'attendance'   => $attendance['summary'],
            'components'   => $components,
            'active_advances' => $activeAdvances,
        ];
    }

    /**
     * Per-day attendance deductions so the statement can list every single
     * deducted penalty / absence day instead of one opaque lump sum.
     *
     * The total is byte-for-byte identical to
     * AttendancePenaltyService::calculateAttendanceDeductionForSalary().
     *
     * @return array{amount: float, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function attendanceDeduction(Employee $employee, int $month, int $year, float $baseSalary): array
    {
        $summary    = $this->attendancePenaltyService->calculateAttendanceDeductionForSalary($employee, $month, $year, $baseSalary);
        $dailyRate  = $this->workingDaysInMonth($month, $year) > 0
            ? $baseSalary / $this->workingDaysInMonth($month, $year)
            : 0.0;

        $records = Attendance::where('employee_id', $employee->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->orderBy('attendance_date')
            ->get();

        $rows     = [];
        $absent   = 0;
        $shortfallDays = 0;
        $lateMinutes   = 0;

        foreach ($records as $record) {
            $date  = $record->attendance_date instanceof Carbon
                ? $record->attendance_date->toDateString()
                : (string) $record->attendance_date;

            if ($record->status === 'absent') {
                $absent++;
                if ($dailyRate > 0) {
                    $rows[] = [
                        'attendance_id' => $record->id,
                        'date'    => $date,
                        'kind'    => 'absent',
                        'name'    => "خصم غياب - {$date}",
                        'reason'  => 'يوم غياب',
                        'minutes' => 0,
                        'amount'  => round($dailyRate, 2),
                    ];
                }
                continue;
            }

            $amount = round((float) ($record->deduction_amount ?? 0), 2);
            if ($record->hours_status === Attendance::HOURS_SHORTFALL) {
                $shortfallDays++;
            }

            $late   = (int) ($record->late_minutes ?? 0);
            $early  = (int) ($record->early_exit_minutes ?? 0);
            $lateMinutes += $late + $early;

            if ($amount <= 0) {
                continue;
            }

            $reasons = [];
            if ($late > 0)  $reasons[] = "تأخير {$late} دقيقة";
            if ($early > 0) $reasons[] = "انصراف مبكر {$early} دقيقة";
            if ($reasons === []) $reasons[] = 'خصم تأخير/انصراف مبكر';

            $rows[] = [
                'attendance_id' => $record->id,
                'date'    => $date,
                'kind'    => 'penalty',
                'name'    => "خصم تأخير/انصراف مبكر - {$date}",
                'reason'  => implode('، ', $reasons),
                'minutes' => $late + $early,
                'amount'  => $amount,
            ];
        }

        $amount = round((float) collect($rows)->sum('amount'), 2);

        return [
            'amount'  => $amount,
            'rows'    => $rows,
            'summary' => [
                'absent_days'      => $absent,
                'shortfall_days'   => $shortfallDays,
                'late_minutes'     => $lateMinutes,
                'daily_rate'       => round($dailyRate, 2),
                'penalty_days'     => count(array_filter($rows, fn($r) => $r['kind'] === 'penalty')),
                'label'            => $summary['label'],
                'rows'             => $rows,
            ],
        ];
    }

    /**
     * The reconciliation every screen must display so the statement adds up.
     *
     * @param  array<string, mixed>  $breakdown
     * @return array<string, float|bool>
     */
    public function reconcile(array $breakdown): array
    {
        $gross = (float) $breakdown['gross_salary'];
        $ded   = (float) $breakdown['deductions']['grand_total'];
        $net   = (float) $breakdown['net_salary'];

        return [
            'base_salary'        => (float) $breakdown['base_salary'],
            'total_additions'    => (float) $breakdown['additions']['total'],
            'gross_salary'       => round($gross, 2),
            'total_deductions'   => (float) $breakdown['deductions']['total'],
            'points_debit'       => (float) $breakdown['deductions']['points_debit'],
            'advances'           => (float) $breakdown['deductions']['advances'],
            'violations'         => (float) $breakdown['deductions']['violations'],
            'total_all_deductions' => $ded,
            'net_salary'         => $net,
            'balances'           => abs(round(($gross - $ded) - $net, 2)) < 0.01,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(string $type, string $name, $id, float $amount, ?string $reason = null): array
    {
        return [
            'type'   => $type,
            'name'   => $name,
            'id'     => $id,
            'amount' => round($amount, 2),
            'reason' => $reason,
        ];
    }

    private function workingDaysInMonth(int $month, int $year): int
    {
        $start = Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();

        $count = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if (!$day->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }
}
