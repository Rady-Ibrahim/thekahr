<?php

namespace App\Http\Controllers\Api;

use App\Models\Advance;
use App\Models\Allowance;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Models\Incentive;
use App\Models\Salary;
use App\Services\SalaryBreakdownService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController
{
    public function __construct(private SalaryBreakdownService $breakdownService) {}

    /**
     * Mobile: financial transactions for the logged-in employee.
     * GET /api/me/financials?month=7&year=2026
     */
    private function loadEmployeeFinancials(Employee $employee, int $month, int $year): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $salary = Salary::with('components')
            ->where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderByDesc('id')
            ->first();

        // ── The one calculation that feeds every number below ────────────────
        // The cards and the net are now produced by the same breakdown, so the
        // statement can no longer contradict itself.
        $breakdown = $this->breakdownService->build($employee, $month, $year);
        $totals    = $this->breakdownService->reconcile($breakdown);

        $incentives = Incentive::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($i) => [
                'id' => $i->id,
                'type' => 'incentive',
                'incentive_type' => $i->incentive_type,
                'amount' => (float) $i->amount,
                'reason' => $i->reason,
                'date' => $i->created_at->toDateString(),
                'status' => $i->status,
            ]);

        $allowances = Allowance::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart);
            })
            ->orderByDesc('start_date')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'type' => 'allowance',
                'allowance_type' => $a->allowance_type,
                'amount' => (float) $a->amount,
                'reason' => $a->reason,
                'date' => $a->start_date->toDateString(),
                'status' => $a->status,
            ]);

        $commissions = collect();
        $violations  = collect();

        $deductions = Deduction::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'type' => 'deduction',
                'deduction_type' => $d->deduction_type,
                'amount' => (float) $d->amount,
                'reason' => $d->reason,
                'date' => $d->created_at->toDateString(),
                'status' => $d->status,
            ]);

        // Every single deducted attendance day becomes its own row, so nothing is
        // hidden inside an opaque lump sum.
        foreach ($breakdown['attendance']['rows'] as $row) {
            $deductions->push([
                'id' => null,
                'attendance_id' => $row['attendance_id'],
                'type' => 'attendance_deduction',
                'deduction_type' => $row['name'],
                'amount' => (float) $row['amount'],
                'reason' => $row['reason'],
                'date' => $row['date'],
                'status' => 'computed',
            ]);
        }

        $advances = Advance::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'partially_paid', 'pending', 'approved'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'type' => 'advance',
                'amount' => (float) $a->amount,
                'reason' => $a->reason,
                'date' => $a->advance_date->toDateString(),
                'status' => $a->status,
                'installment_amount' => (float) $a->installment_amount,
                'remaining_installments' => $a->remaining_installments,
                'remaining_amount' => (float) $a->remaining_amount,
            ]);

        $points = EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'point',
                'total_amount' => (float) $p->total_amount,
                'points' => (float) $p->points,
                'point_price' => (float) $p->point_price,
                'reason' => $p->reason,
                'date' => $p->created_at->toDateString(),
                'direction' => $p->type,
            ]);

        $attendanceDeduction = (float) $breakdown['deductions']['attendance'];

        $summary = [
            'base_salary' => $totals['base_salary'],

            // Backwards-compatible keys (kept for the mobile app).
            'incentives_total'    => $breakdown['additions']['incentives'],
            'allowances_total'    => $breakdown['additions']['allowances'],
            'points_credit_total' => $breakdown['additions']['points_credit'],
            'points_debit_total'  => $totals['points_debit'],
            'points_net_total'    => round($breakdown['additions']['points_credit'] - $totals['points_debit'], 2),
            // Legacy keys: semantics are UNCHANGED from before, so existing clients
            // keep working. `deductions_total` stays direct-only because the legacy
            // `estimated_net` formula subtracted `attendance_deduction_total` on top
            // of it. Do not fold attendance into this key or clients double count it.
            'deductions_total'    => $breakdown['deductions']['direct'],
            'advances_installment_total' => $totals['advances'],
            'attendance_deduction_total' => $attendanceDeduction,

            // Unified / explicit keys.
            'total_additions'      => $totals['total_additions'],
            'gross_salary'         => $totals['gross_salary'],
            'direct_deductions_total' => $breakdown['deductions']['direct'],
            // direct + attendance (late / early exit / absence), per the agreed contract
            'total_deductions'     => $totals['total_deductions'],
            'total_all_deductions' => $totals['total_all_deductions'],
            'net_salary'           => $totals['net_salary'],
            'balances'             => $totals['balances'],

            'salary_net'    => $salary ? (float) $salary->net_salary : null,
            'salary_gross'  => $salary ? (float) $salary->gross_salary : null,
            'salary_status' => $salary?->status,
            'salary_id'     => $salary?->id,
            // True when a stored payroll row no longer matches the live figures.
            'is_stale'      => $salary
                ? abs((float) $salary->net_salary - $totals['net_salary']) > 0.01
                : false,
            'attendance'    => $breakdown['attendance'],
        ];

        $summary['estimated_net'] = $totals['net_salary'];

        return [
            'salary' => $salary,
            'breakdown' => $breakdown,
            'totals' => $totals,
            'incentives' => $incentives,
            'allowances' => $allowances,
            'commissions' => $commissions,
            'points' => $points,
            'deductions' => $deductions,
            'advances' => $advances,
            'violations' => $violations,
            'summary' => $summary,
        ];
    }

    public function myFinancials(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب',
            ], 404);
        }

        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $data = $this->loadEmployeeFinancials($employee, $month, $year);
        return response()->json([
            'success' => true,
            'month' => $month,
            'year' => $year,
            'employee' => $employee->only([
                'id', 'name', 'employee_code', 'position', 'department',
                'base_salary', 'collection_commission_rate',
            ]),
            'summary' => $data['summary'],
            'breakdown' => [
                'additions'   => $data['breakdown']['additions'],
                'deductions'  => $data['breakdown']['deductions'],
                'components'  => $data['breakdown']['components'],
                'attendance'  => $data['breakdown']['attendance'],
            ],
            'totals' => $data['totals'],
            'data' => [
                'salary' => $data['salary'],
                'incentives' => $data['incentives'],
                'allowances' => $data['allowances'],
                'commissions' => $data['commissions'],
                'points' => $data['points'],
                'deductions' => $data['deductions'],
                'advances' => $data['advances'],
                'violations' => $data['violations'],
            ],
        ]);
    }

    /**
     * Admin: financial statement for any employee.
     * GET /api/employees/{id}/financial-statement?month=&year=
     */
    public function employeeFinancials($employeeId, Request $request): JsonResponse
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'الموظف غير موجود'], 404);
        }

        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);

        $data = $this->loadEmployeeFinancials($employee, $month, $year);

        return response()->json([
            'success' => true,
            'month' => $month,
            'year' => $year,
            'employee' => $employee->only([
                'id', 'name', 'employee_code', 'position', 'department',
                'base_salary', 'collection_commission_rate',
            ]),
            'summary' => $data['summary'],
            'breakdown' => [
                'additions'   => $data['breakdown']['additions'],
                'deductions'  => $data['breakdown']['deductions'],
                'components'  => $data['breakdown']['components'],
                'attendance'  => $data['breakdown']['attendance'],
            ],
            'totals' => $data['totals'],
            'data' => [
                'salary' => $data['salary'],
                'incentives' => $data['incentives'],
                'allowances' => $data['allowances'],
                'commissions' => $data['commissions'],
                'points' => $data['points'],
                'deductions' => $data['deductions'],
                'advances' => $data['advances'],
                'violations' => $data['violations'],
            ],
        ]);
    }
}
