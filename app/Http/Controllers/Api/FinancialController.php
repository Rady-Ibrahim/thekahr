<?php

namespace App\Http\Controllers\Api;

use App\Models\Advance;
use App\Models\Allowance;
use App\Models\CarViolation;
use App\Models\Commission;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Models\Incentive;
use App\Models\Salary;
use App\Services\AttendancePenaltyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialController
{
    public function __construct(private AttendancePenaltyService $attendancePenaltyService) {}

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

        $commissions = Commission::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->with('collection:id,collection_number,total_amount')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'type' => 'commission',
                'amount' => (float) $c->amount,
                'reason' => $c->reason,
                'date' => $c->created_at->toDateString(),
                'status' => $c->status,
                'collection' => $c->collection,
            ]);

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

        $advances = Advance::where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'partially_paid', 'pending', 'approved'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'type' => 'advance',
                'amount' => (float) $a->amount,
                'reason' => $a->reason,
                'date' => $a->created_at->toDateString(),
                'status' => $a->status,
                'installment_amount' => (float) $a->installment_amount,
                'remaining_installments' => $a->remaining_installments,
                'remaining_amount' => (float) $a->remaining_amount,
            ]);

        $violations = CarViolation::where('employee_id', $employee->id)
            ->whereMonth('violation_date', $month)
            ->whereYear('violation_date', $year)
            ->orderByDesc('violation_date')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'type' => 'violation',
                'violation_type' => $v->violation_type,
                'amount' => (float) $v->fine_amount,
                'reason' => $v->reason,
                'date' => $v->violation_date->toDateString(),
                'status' => $v->status,
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

        $attendanceSummary = $this->attendancePenaltyService
            ->calculateAttendanceDeductionForSalary($employee, $month, $year, (float) $employee->base_salary);
        $attendanceDeduction = (float) $attendanceSummary['amount'];

        if ($attendanceDeduction > 0) {
            $deductions->push([
                'id' => null,
                'type' => 'attendance_deduction',
                'deduction_type' => 'خصم تأخير / انصراف مبكر',
                'amount' => $attendanceDeduction,
                'reason' => $attendanceSummary['label'],
                'date' => null,
                'status' => 'computed',
            ]);
        }

        $pointsCreditTotal = (float) EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('type', 'credit')->sum('total_amount');
        $pointsDebitTotal  = (float) EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->where('type', 'debit')->sum('total_amount');

        $summary = [
            'base_salary' => (float) $employee->base_salary,
            'incentives_total' => (float) collect($incentives)->where('status', 'approved')->sum('amount'),
            'allowances_total' => (float) collect($allowances)->sum('amount'),
            'commissions_total' => (float) collect($commissions)->where('status', 'approved')->sum('amount'),
            'commissions_pending_total' => (float) collect($commissions)->where('status', 'pending')->sum('amount'),
            'points_credit_total' => $pointsCreditTotal,
            'points_debit_total' => $pointsDebitTotal,
            'points_net_total' => $pointsCreditTotal - $pointsDebitTotal,
            'deductions_total' => (float) collect($deductions)->where('status', 'approved')->sum('amount'),
            'advances_installment_total' => (float) collect($advances)
                ->whereIn('status', ['active', 'partially_paid'])
                ->where('remaining_installments', '>', 0)
                ->sum('installment_amount'),
            'violations_total' => (float) collect($violations)->where('status', 'pending')->sum('amount'),
            'attendance_deduction_total' => $attendanceDeduction,
            'salary_net' => $salary ? (float) $salary->net_salary : null,
            'salary_gross' => $salary ? (float) $salary->gross_salary : null,
            'salary_status' => $salary?->status,
        ];

        if (!$salary) {
            $gross = $summary['base_salary']
                + $summary['incentives_total']
                + $summary['allowances_total']
                + $summary['commissions_total']
                + $summary['points_credit_total'];
            $estimatedNet = $gross
                - $summary['deductions_total']
                - $summary['advances_installment_total']
                - $summary['violations_total']
                - $summary['points_debit_total']
                - $summary['attendance_deduction_total'];
            $summary['estimated_net'] = max(0, round($estimatedNet, 2));
        } else {
            $summary['estimated_net'] = (float) $salary->net_salary;
        }

        return [
            'salary' => $salary,
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
