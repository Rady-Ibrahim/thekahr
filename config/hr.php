<?php

return [
    // Employee Statuses
    'employee_statuses' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'suspended' => 'موقوف',
        'resigned' => 'استقال',
        'on_leave' => 'في إجازة',
    ],

    // Request Statuses
    'request_statuses' => [
        'draft' => 'مسودة',
        'prepared' => 'تم التحضير',
        'under_review' => 'تحت المراجعة',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'ready_for_delivery' => 'جاهز للتسليم',
        'in_delivery' => 'في الطريق',
        'delivered' => 'تم التسليم',
        'collected' => 'تم التحصيل',
        'closed' => 'مغلق',
    ],

    // Delivery Statuses
    'delivery_statuses' => [
        'pending' => 'معلق',
        'in_transit' => 'في الطريق',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'partially_delivered' => 'تسليم جزئي',
    ],

    // Attendance Statuses
    'attendance_statuses' => [
        'present' => 'حاضر',
        'absent' => 'غائب',
        'late' => 'متأخر',
        'early_leave' => 'مغادرة مبكرة',
        'on_leave' => 'في إجازة',
        'excused' => 'معذور',
    ],

    // Payment Methods
    'payment_methods' => [
        'cash' => 'نقدي',
        'bank_transfer' => 'تحويل بنكي',
        'check' => 'شيك',
        'instapay' => 'إنستاباي',
        'fawry' => 'فوري',
    ],

    // Salary Statuses
    'salary_statuses' => [
        'draft' => 'مسودة',
        'pending_approval' => 'في انتظار الموافقة',
        'approved' => 'معتمد',
        'paid' => 'مدفوع',
        'rejected' => 'مرفوض',
        'on_hold' => 'معلق',
    ],

    // Approval Statuses
    'approval_statuses' => [
        'pending' => 'معلق',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'escalated' => 'مرفوع',
    ],

    // Roles
    'roles' => [
        'super_admin' => 'مدير النظام',
        'hr_manager' => 'مدير الموارد البشرية',
        'finance_manager' => 'مدير المالية',
        'operations_manager' => 'مدير التشغيل',
        'warehouse_manager' => 'مدير المخزن',
        'delivery_manager' => 'مدير التوصيل',
        'approver_level_1' => 'موافق المستوى الأول',
        'approver_level_2' => 'موافق المستوى الثاني',
        'approver_level_3' => 'موافق المستوى الثالث',
        'driver' => 'السائق',
        'employee' => 'الموظف',
        'report_viewer' => 'عارض التقارير',
    ],

    // Allowance Types
    'allowance_types' => [
        'transportation' => 'بدل انتقال',
        'fuel' => 'بدل وقود',
        'missions' => 'بدل مأموريات',
        'housing' => 'بدل سكن',
        'meal' => 'بدل طعام',
        'phone' => 'بدل هاتف',
        'shift' => 'بدل وردية',
    ],

    // Incentive Types
    'incentive_types' => [
        'performance' => 'أداء',
        'sales' => 'مبيعات',
        'attendance' => 'حضور',
        'delivery' => 'توصيل',
        'other' => 'أخرى',
    ],

    // Deduction Types
    'deduction_types' => [
        'tax' => 'ضريبة',
        'insurance' => 'تأمين',
        'loan' => 'قرض',
        'damage' => 'أضرار',
        'disciplinary' => 'تأديبي',
        'other' => 'أخرى',
    ],

    // Leave Request Types
    'leave_types' => [
        'sick' => 'إجازة مرضية',
        'leave' => 'إجازة عادية',
        'late' => 'تأخير',
        'early' => 'مغادرة مبكرة',
        'excuse' => 'عذر',
    ],

    // Violation Types
    'violation_types' => [
        'speeding' => 'تجاوز السرعة',
        'parking' => 'مخالفة وقوف',
        'accident' => 'حادث',
        'license' => 'مخالفة الرخصة',
        'insurance' => 'مخالفة التأمين',
        'maintenance' => 'مخالفة الصيانة',
        'other' => 'أخرى',
    ],

    // Working Hours Settings
    // DEPRECATED: Use dynamic shifts system instead (see /shifts management UI).
    // These values serve as fallback defaults when no shift is assigned to an employee.
    'working_hours' => [
        'daily_hours' => 8,
        'check_in_time' => '08:00',
        'check_out_time' => '17:00',
        'late_threshold_minutes' => 15,
        'half_day_deduction_after_minutes' => 120,
        'timezone' => 'Africa/Cairo',
        // If an employee forgets to check out, the open shift is auto-closed
        // after this many hours PAST the shift's scheduled end time.
        // (4-hour grace allows handovers between concurrent shifts.)
        'auto_close_grace_hours' => 4,
        // Backward-compatible legacy threshold (hours after check-in) used when
        // the shift's scheduled end time cannot be determined.
        'auto_close_after_hours' => 20,
    ],

    // Salary Calculation Settings
    'salary' => [
        'tax_percentage' => 5.0,
        'insurance_percentage' => 3.0,
        'advance_deduction_method' => 'monthly',
        'commission_calculation' => 'automatic',
    ],

    // Approval Levels
    'approval_levels' => [
        1 => 'المستوى الأول',
        2 => 'المستوى الثاني',
        3 => 'المستوى الثالث (الأخير)',
    ],

    // Pagination
    'pagination' => [
        'per_page' => 15,
        'max_per_page' => 100,
    ],

    // Cache Settings
    'cache' => [
        'ttl' => 3600,
        'enable' => true,
    ],

    // Notifications
    'notifications' => [
        'enable_email' => true,
        'enable_sms' => true,
        'enable_in_app' => true,
    ],

    // GPS Settings
    'gps' => [
        'enable' => true,
        'accuracy_threshold' => 50,
        'update_interval' => 30,
    ],

    // Export Settings
    'export' => [
        'enable_pdf' => true,
        'enable_excel' => true,
        'enable_csv' => true,
    ],
];
