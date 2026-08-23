<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPER_ADMIN = 'super_admin';
    case HR_MANAGER = 'hr_manager';
    case FINANCE_MANAGER = 'finance_manager';
    case OPERATIONS_MANAGER = 'operations_manager';
    case WAREHOUSE_MANAGER = 'warehouse_manager';
    case DELIVERY_MANAGER = 'delivery_manager';
    case APPROVER_LEVEL_1 = 'approver_level_1';
    case APPROVER_LEVEL_2 = 'approver_level_2';
    case APPROVER_LEVEL_3 = 'approver_level_3';
    case MANAGER = 'manager';
    case DRIVER = 'driver';
    case EMPLOYEE = 'employee';
    case REPORT_VIEWER = 'report_viewer';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'مدير النظام',
            self::HR_MANAGER => 'مدير الموارد البشرية',
            self::FINANCE_MANAGER => 'مدير المالية',
            self::OPERATIONS_MANAGER => 'مدير التشغيل',
            self::WAREHOUSE_MANAGER => 'مدير المخزن',
            self::DELIVERY_MANAGER => 'مدير التوصيل',
            self::APPROVER_LEVEL_1 => 'موافق المستوى الأول',
            self::APPROVER_LEVEL_2 => 'موافق المستوى الثاني',
            self::APPROVER_LEVEL_3 => 'موافق المستوى الثالث',
            self::MANAGER => 'مدير',
            self::DRIVER => 'سائق / مندوب',
            self::EMPLOYEE => 'الموظف',
            self::REPORT_VIEWER => 'عارض التقارير',
        };
    }
}
