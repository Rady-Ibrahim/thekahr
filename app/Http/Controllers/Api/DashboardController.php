<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\Delivery;
use App\Models\Collection;
use App\Models\Salary;
use App\Models\Approval;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function metrics()
    {
        $today = now()->toDateString();

        $metrics = [
            // Employee Metrics
            'employees' => [
                'total' => Employee::count(),
                'active' => Employee::where('status', 'active')->count(),
                'present_today' => Attendance::where('attendance_date', $today)
                    ->where('status', 'present')->count(),
                'late_today' => Attendance::where('attendance_date', $today)
                    ->where('status', 'late')->count(),
                'absent_today' => Employee::where('status', 'active')
                    ->whereDoesntHave('attendances', function ($q) use ($today) {
                        $q->where('attendance_date', $today);
                    })->count(),
                'no_checkout' => Attendance::where('attendance_date', $today)
                    ->whereNotNull('check_in_time')
                    ->whereNull('check_out_time')->count(),
            ],

            // Operations Metrics
            'operations' => [
                'new_requests' => RequestModel::where('status', 'draft')->count(),
                'prepared_requests' => RequestModel::where('status', 'prepared')->count(),
                'under_review' => RequestModel::where('status', 'under_review')->count(),
                'approved_requests' => RequestModel::where('status', 'approved')->count(),
                'ready_for_delivery' => RequestModel::where('status', 'ready_for_delivery')->count(),
                'delivered_requests' => RequestModel::where('status', 'delivered')->count(),
            ],

            // Delivery Metrics
            'deliveries' => [
                'pending' => Delivery::where('status', 'pending')->count(),
                'in_transit' => Delivery::where('status', 'in_transit')->count(),
                'completed' => Delivery::where('status', 'completed')->count(),
                'failed' => Delivery::where('status', 'failed')->count(),
            ],

            // Collections Metrics
            'collections' => [
                'pending' => Collection::where('collection_status', 'pending')->sum('total_amount'),
                'collected_today' => Collection::where('collected_date', $today)
                    ->where('collection_status', '!=', 'rejected')
                    ->sum('total_amount'),
                'collected_month' => Collection::whereMonth('collected_date', now()->month)
                    ->whereYear('collected_date', now()->year)
                    ->where('collection_status', '!=', 'rejected')
                    ->sum('total_amount'),
            ],

            // Approvals Metrics
            'approvals' => [
                'pending_approvals' => Approval::where('status', 'pending')->count(),
                'pending_requests' => RequestModel::where('status', 'under_review')->count(),
                'pending_salaries' => Salary::where('status', 'pending_approval')->count(),
            ],

            // Salary Metrics
            'payroll' => [
                'total_salary_amount' => Salary::where('status', 'approved')
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->sum('net_salary'),
                'pending_salaries' => Salary::where('status', 'pending_approval')->count(),
                'paid_salaries' => Salary::where('status', 'paid')->count(),
            ],
        ];

        return response()->json(['data' => $metrics]);
    }

    public function employeesChart()
    {
        $data = [
            'active' => Employee::where('status', 'active')->count(),
            'inactive' => Employee::where('status', 'inactive')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'suspended' => Employee::where('status', 'suspended')->count(),
            'resigned' => Employee::where('status', 'resigned')->count(),
        ];

        return response()->json(['data' => $data]);
    }

    public function requestsChart()
    {
        $data = [
            'draft' => RequestModel::where('status', 'draft')->count(),
            'under_review' => RequestModel::where('status', 'under_review')->count(),
            'approved' => RequestModel::where('status', 'approved')->count(),
            'delivered' => RequestModel::where('status', 'delivered')->count(),
            'rejected' => RequestModel::where('status', 'rejected')->count(),
        ];

        return response()->json(['data' => $data]);
    }

    public function attendanceChart()
    {
        $today = now()->toDateString();
        $present = Attendance::where('attendance_date', $today)->where('status', 'present')->count();
        $late    = Attendance::where('attendance_date', $today)->where('status', 'late')->count();
        $onLeave = Attendance::where('attendance_date', $today)->where('status', 'on_leave')->count();
        $active  = Employee::where('status', 'active')->count();
        $attendedToday = Attendance::where('attendance_date', $today)->count();

        $data = [
            'present' => $present,
            'absent' => max(0, $active - $attendedToday),
            'late' => $late,
            'on_leave' => $onLeave,
        ];

        return response()->json(['data' => $data]);
    }

    public function collectionsChart()
    {
        $monthData = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthData[$month->format('M')] = Collection::whereMonth('collected_date', $month->month)
                ->whereYear('collected_date', $month->year)->sum('total_amount');
        }

        return response()->json(['data' => $monthData]);
    }

    public function performanceMetrics()
    {
        $topEmployees = Employee::with('deliveries')
            ->withCount('deliveries')
            ->orderByDesc('deliveries_count')
            ->limit(10)
            ->get()
            ->map(function ($employee) {
                return [
                    'name' => $employee->name,
                    'deliveries' => $employee->deliveries_count,
                    'position' => $employee->position,
                ];
            });

        return response()->json(['data' => $topEmployees]);
    }
}
