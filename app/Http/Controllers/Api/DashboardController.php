<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\Salary;
use App\Models\Attendance;
use App\Models\NearExpiryItem;
use App\Models\NearExpirySale;

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

            // Payroll Metrics
            'payroll' => [
                'total_salary_amount' => Salary::where('status', 'approved')
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->sum('net_salary'),
                'pending_salaries' => Salary::where('status', 'pending_approval')->count(),
                'paid_salaries' => Salary::where('status', 'paid')->count(),
            ],

            // Near-Expiry Items & Sales
            'near_expiry' => [
                'items_count' => NearExpiryItem::count(),
                'expiring_soon' => NearExpiryItem::whereBetween('expiry_date', [
                    now()->toDateString(), now()->addDays(30)->toDateString(),
                ])->count(),
                'month_sales_quantity' => (int) NearExpirySale::where('status', 'approved')
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->sum('quantity_sold'),
                'month_incentives_total' => (float) NearExpirySale::where('status', 'approved')
                    ->where('month', now()->month)
                    ->where('year', now()->year)
                    ->sum('total_incentive'),
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
}
