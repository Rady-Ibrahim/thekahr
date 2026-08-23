<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Models\Collection;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\Incentive;
use App\Models\Request as RequestModel;
use App\Models\Salary;
use Carbon\Carbon;
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

    public function requests(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $requests = RequestModel::with(['customer', 'createdBy'])
            ->whereBetween('created_at', [$from, $to . ' 23:59:59'])
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'request_number'  => $r->request_number,
                'customer_name'   => $r->customer?->name ?? $r->customer_name,
                'company_name'    => $r->company_name,
                'total_amount'    => (float) $r->total_amount,
                'status'          => $r->status,
                'payment_type'    => $r->payment_type,
                'items_count'     => $r->items_count,
                'total_quantity'  => $r->total_quantity,
                'created_by'      => $r->createdBy?->name,
                'created_at'      => $r->created_at->toDateString(),
            ]);

        $summary = [
            'total'         => $requests->count(),
            'total_value'   => $requests->sum('total_amount'),
            'by_status'     => $requests->groupBy('status')->map->count(),
            'by_customer'   => $requests->groupBy('customer_name')->map(fn($g) => ['count' => $g->count(), 'total' => $g->sum('total_amount')]),
        ];

        return response()->json(['success' => true, 'data' => $requests, 'summary' => $summary]);
    }

    public function collections(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $collections = Collection::with(['delivery.request.customer', 'driver'])
            ->whereBetween('collected_date', [$from, $to])
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'collection_number' => $c->collection_number,
                'total_amount'     => (float) $c->total_amount,
                'payment_method'   => $c->payment_method,
                'collection_status'=> $c->collection_status,
                'driver'           => $c->driver?->name,
                'collected_date'   => $c->collected_date,
            ]);

        $summary = [
            'total'           => $collections->sum('total_amount'),
            'count'           => $collections->count(),
            'by_method'       => $collections->groupBy('payment_method')->map->sum('total_amount'),
            'by_status'       => $collections->groupBy('collection_status')->map->sum('total_amount'),
            'by_driver'       => $collections->groupBy('driver')->map->sum('total_amount'),
        ];

        return response()->json(['success' => true, 'data' => $collections, 'summary' => $summary]);
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

    public function performance(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $topDelivery = Employee::withCount(['deliveries as completed_deliveries' => function ($q) use ($month, $year) {
            $q->where('status', 'completed')
              ->whereMonth('created_at', $month)
              ->whereYear('created_at', $year);
        }])->orderByDesc('completed_deliveries')->limit(10)->get(['id', 'name', 'employee_code', 'department'])
        ->map(fn($e) => [
            'name'                => $e->name,
            'employee_code'       => $e->employee_code,
            'department'          => $e->department,
            'completed_deliveries'=> (int) $e->completed_deliveries,
        ]);

        $topCollection = Collection::where('collection_status', 'deposited')
            ->whereMonth('collected_date', $month)->whereYear('collected_date', $year)
            ->with('driver')
            ->selectRaw('driver_id, SUM(total_amount) as total')
            ->groupBy('driver_id')
            ->orderByDesc('total')
            ->limit(10)->get()
            ->map(fn($c) => [
                'driver' => $c->driver?->name,
                'total'  => (float) $c->total,
            ]);

        $topAttendance = Employee::with(['attendances' => function ($q) use ($month, $year) {
            $q->whereMonth('attendance_date', $month)->whereYear('attendance_date', $year);
        }])->get()->map(function ($emp) {
            return [
                'name'             => $emp->name,
                'employee_code'    => $emp->employee_code,
                'present_days'     => $emp->attendances->where('status', 'present')->count(),
                'late_minutes'     => $emp->attendances->sum('late_minutes'),
            ];
        })->sortByDesc('present_days')->values()->take(10);

        return response()->json([
            'success' => true,
            'data'    => [
                'top_delivery'   => $topDelivery,
                'top_collection' => $topCollection,
                'top_attendance' => $topAttendance,
            ],
        ]);
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
        $start = Carbon::createFromDate($year, $month, 1);
        $end   = $start->copy()->endOfMonth();

        return response()->json([
            'success' => true,
            'data'    => [
                'period'             => ['month' => $month, 'year' => $year],
                'employees'          => [
                    'total'  => Employee::count(),
                    'active' => Employee::where('status', 'active')->count(),
                ],
                'requests'           => [
                    'total'         => RequestModel::whereBetween('created_at', [$start, $end])->count(),
                    'total_value'   => RequestModel::whereBetween('created_at', [$start, $end])->sum('total_amount'),
                    'delivered'     => RequestModel::whereBetween('created_at', [$start, $end])->where('status', 'delivered')->count(),
                ],
                'collections'        => [
                    'total'   => Collection::whereBetween('collected_date', [$start->toDateString(), $end->toDateString()])->sum('total_amount'),
                    'count'   => Collection::whereBetween('collected_date', [$start->toDateString(), $end->toDateString()])->count(),
                ],
                'salary'             => [
                    'total_gross' => Salary::where('month', $month)->where('year', $year)->sum('gross_salary'),
                    'total_net'   => Salary::where('month', $month)->where('year', $year)->sum('net_salary'),
                    'paid_count'  => Salary::where('month', $month)->where('year', $year)->where('status', 'paid')->count(),
                ],
                'deliveries'         => [
                    'total'     => Delivery::whereBetween('created_at', [$start, $end])->count(),
                    'completed' => Delivery::whereBetween('created_at', [$start, $end])->where('status', 'completed')->count(),
                    'failed'    => Delivery::whereBetween('created_at', [$start, $end])->where('status', 'failed')->count(),
                ],
            ],
        ]);
    }
}
