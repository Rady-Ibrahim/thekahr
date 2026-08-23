<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        ['name' => 'view_employees', 'group' => 'employees', 'description' => 'عرض الموظفين'],
        ['name' => 'create_employees', 'group' => 'employees', 'description' => 'إنشاء موظفين'],
        ['name' => 'edit_employees', 'group' => 'employees', 'description' => 'تعديل الموظفين'],
        ['name' => 'delete_employees', 'group' => 'employees', 'description' => 'حذف الموظفين'],

        ['name' => 'view_requests', 'group' => 'requests', 'description' => 'عرض الطلبات'],
        ['name' => 'create_requests', 'group' => 'requests', 'description' => 'إنشاء طلبات'],
        ['name' => 'edit_requests', 'group' => 'requests', 'description' => 'تعديل الطلبات'],
        ['name' => 'delete_requests', 'group' => 'requests', 'description' => 'حذف الطلبات'],
        ['name' => 'approve_requests', 'group' => 'requests', 'description' => 'موافقة الطلبات'],

        ['name' => 'view_deliveries', 'group' => 'deliveries', 'description' => 'عرض التسليمات'],
        ['name' => 'create_deliveries', 'group' => 'deliveries', 'description' => 'إنشاء تسليمات'],
        ['name' => 'edit_deliveries', 'group' => 'deliveries', 'description' => 'تعديل التسليمات'],
        ['name' => 'approve_routes', 'group' => 'deliveries', 'description' => 'موافقة خطوط السير'],

        ['name' => 'view_collections', 'group' => 'collections', 'description' => 'عرض التحصيلات'],
        ['name' => 'create_collections', 'group' => 'collections', 'description' => 'إنشاء تحصيلات'],
        ['name' => 'approve_collections', 'group' => 'collections', 'description' => 'موافقة التحصيلات'],

        ['name' => 'view_attendance', 'group' => 'attendance', 'description' => 'عرض الحضور'],
        ['name' => 'manage_attendance', 'group' => 'attendance', 'description' => 'إدارة الحضور'],

        ['name' => 'view_salaries', 'group' => 'salaries', 'description' => 'عرض الرواتب'],
        ['name' => 'create_salaries', 'group' => 'salaries', 'description' => 'إنشاء رواتب'],
        ['name' => 'approve_salaries', 'group' => 'salaries', 'description' => 'موافقة الرواتب'],
        ['name' => 'manage_incentives', 'group' => 'salaries', 'description' => 'إدارة الحوافز'],
        ['name' => 'manage_deductions', 'group' => 'salaries', 'description' => 'إدارة الخصومات'],
        ['name' => 'manage_allowances', 'group' => 'salaries', 'description' => 'إدارة البدلات'],
        ['name' => 'manage_team_financials', 'group' => 'salaries', 'description' => 'إدارة مالية الفريق'],

        ['name' => 'view_reports', 'group' => 'reports', 'description' => 'عرض التقارير'],
        ['name' => 'export_reports', 'group' => 'reports', 'description' => 'تصدير التقارير'],

        ['name' => 'view_dashboard', 'group' => 'dashboard', 'description' => 'عرض لوحة التحكم'],

        ['name' => 'view_shifts', 'group' => 'shifts', 'description' => 'عرض الورديات'],

        ['name' => 'view_incentives', 'group' => 'salaries', 'description' => 'عرض الحوافز'],
        ['name' => 'view_deductions', 'group' => 'salaries', 'description' => 'عرض الخصومات'],
        ['name' => 'view_advances', 'group' => 'salaries', 'description' => 'عرض السلف'],
        ['name' => 'view_allowances', 'group' => 'salaries', 'description' => 'عرض البدلات'],
        ['name' => 'view_commissions', 'group' => 'salaries', 'description' => 'عرض العمولات'],

        ['name' => 'view_employee_points', 'group' => 'employees', 'description' => 'عرض نقاط الموظفين'],

        ['name' => 'view_prepaid_requests', 'group' => 'requests', 'description' => 'عرض تحضير الطلبيه'],

        ['name' => 'view_routes', 'group' => 'deliveries', 'description' => 'عرض خطوط السير'],

        ['name' => 'view_car_violations', 'group' => 'car_violations', 'description' => 'عرض مخالفات السيارات'],

        ['name' => 'view_customers', 'group' => 'customers', 'description' => 'عرض العملاء'],

        ['name' => 'view_warehouses', 'group' => 'warehouses', 'description' => 'عرض المخازن'],

        ['name' => 'view_items', 'group' => 'items', 'description' => 'عرض الأصناف'],

        ['name' => 'view_approvals', 'group' => 'approvals', 'description' => 'عرض الموافقات'],

        ['name' => 'view_chat_groups', 'group' => 'chat_groups', 'description' => 'عرض مجموعات الدردشة'],

        ['name' => 'view_notifications', 'group' => 'notifications', 'description' => 'عرض الإشعارات'],

        ['name' => 'view_work_locations', 'group' => 'work_locations', 'description' => 'عرض مواقع العمل'],

        ['name' => 'view_tab_permissions', 'group' => 'settings', 'description' => 'عرض صلاحيات التابات'],

        ['name' => 'view_users', 'group' => 'users', 'description' => 'عرض المستخدمين'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }

    public function down(): void
    {
    }
};
