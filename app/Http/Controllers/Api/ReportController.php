<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Incentive;
use App\Models\Salary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController
{
    public function employees(Request $request): JsonResponse
    {
        $query = Employee::query();

        if ($request->filled('department')) $query->where('department', $request->department);
        if ($request->filled('status'))     $query->where('status', $request->status);

        $employees = $query->with('manager')->get()->map(function ($emp) {
            $month = now()->month;
            $year  = now()->year;
            $attendance = Attendance::where('employee_id', $emp->id)
                ->whereMonth('attendance_date', $month)->whereYear('attendance_date', $year)->get();

            return [
                'id'              => $emp->id,
                'employee_code'   => $emp->employee_code,
                'name'            => $emp->name,
                'position'        => $emp->position,
                'department'      => $emp->department,
                'status'          => $emp->status,
                'joining_date'    => $emp->joining_date,
                'base_salary'     => $emp->base_salary,
                'present_days'    => $attendance->where('status', 'present')->count(),
                'absent_days'     => $attendance->where('status', 'absent')->count(),
                'late_count'      => $attendance->where('status', 'late')->count(),
                'total_hours'     => $attendance->sum('working_hours'),
                'manager'         => $emp->manager?->name,
            ];
        });

        return response()->json(['success' => true, 'data' => $employees, 'total' => $employees->count()]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $employees = Employee::where('status', 'active')->get();
        $report    = [];

        foreach ($employees as $emp) {
            $records = Attendance::where('employee_id', $emp->id)
                ->whereMonth('attendance_date', $month)
                ->whereYear('attendance_date', $year)
                ->get();

            $report[] = [
                'employee_code'   => $emp->employee_code,
                'name'            => $emp->name,
                'department'      => $emp->department,
                'present'         => $records->where('status', 'present')->count(),
                'absent'          => $records->where('status', 'absent')->count(),
                'late'            => $records->where('status', 'late')->count(),
                'on_leave'        => $records->where('status', 'on_leave')->count(),
                'late_minutes'    => $records->sum('late_minutes'),
                'working_hours'   => $records->sum('working_hours'),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $report,
            'month'   => $month,
            'year'    => $year,
        ]);
    }

    public function salaries(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $salaries = Salary::with('employee')
            ->where('month', $month)->where('year', $year)
            ->get()
            ->map(fn($s) => [
                'id'                 => $s->id,
                'employee'           => $s->employee?->name,
                'base_salary'        => (float) $s->base_salary,
                'gross_salary'       => (float) $s->gross_salary,
                'total_incentives'   => (float) $s->total_incentives,
                'total_allowances'   => (float) $s->total_allowances,
                'total_commissions'  => (float) $s->total_commissions,
                'total_points_credit'=> (float) $s->total_points_credit,
                'total_points_debit' => (float) $s->total_points_debit,
                'total_deductions'   => (float) $s->total_deductions,
                'total_advances'     => (float) $s->total_advances,
                'total_violations'   => (float) $s->total_violations,
                'net_salary'         => (float) $s->net_salary,
                'status'             => $s->status,
            ]);

        $summary = [
            'total_gross'           => $salaries->sum('gross_salary'),
            'total_net'             => $salaries->sum('net_salary'),
            'total_incentives'      => $salaries->sum('total_incentives'),
            'total_allowances'      => $salaries->sum('total_allowances'),
            'total_commissions'     => $salaries->sum('total_commissions'),
            'total_points_credit'   => $salaries->sum('total_points_credit'),
            'total_points_debit'    => $salaries->sum('total_points_debit'),
            'total_deductions'      => $salaries->sum('total_deductions'),
            'total_advances'        => $salaries->sum('total_advances'),
            'total_violations'      => $salaries->sum('total_violations'),
            'by_status'             => $salaries->groupBy('status')->map->count(),
        ];

        return response()->json(['success' => true, 'data' => $salaries, 'summary' => $summary]);
    }

    public function incentivesReport(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $incentives = Incentive::with('employee')
            ->where('month', $month)->where('year', $year)->get()
            ->map(fn($i) => [
                'id'             => $i->id,
                'employee'       => $i->employee?->name,
                'incentive_type' => $i->incentive_type,
                'amount'         => (float) $i->amount,
                'reason'         => $i->reason,
                'status'         => $i->status,
                'date'           => $i->created_at->toDateString(),
            ]);

        $summary = [
            'total'         => $incentives->sum('amount'),
            'by_type'       => $incentives->groupBy('incentive_type')->map->sum('amount'),
            'by_status'     => $incentives->groupBy('status')->map->count(),
            'by_employee'   => $incentives->groupBy('employee')->map->sum('amount'),
        ];

        return response()->json(['success' => true, 'data' => $incentives, 'summary' => $summary]);
    }

    public function monthlyAdminSummary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        return response()->json([
            'success' => true,
            'data'    => [
                'period'             => ['month' => $month, 'year' => $year],
                'employees'          => [
                    'total'  => Employee::count(),
                    'active' => Employee::where('status', 'active')->count(),
                ],
                'salary'             => [
                    'total_gross' => Salary::where('month', $month)->where('year', $year)->sum('gross_salary'),
                    'total_net' => Salary::where('month', $month)->where('year', $year)->sum('net_salary'),
                    'paid_count'  => Salary::where('month', $month)->where('year', $year)->where('status', 'paid')->count(),
                ],
            ],
        ]);
    }
}
