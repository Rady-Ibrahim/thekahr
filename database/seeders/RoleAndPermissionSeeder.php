<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin' => 'مدير النظام',
            'hr_manager' => 'مدير الموارد البشرية',
            'finance_manager' => 'مدير المالية',
            'operations_manager' => 'مدير التشغيل',
            'warehouse_manager' => 'مدير المخزن',
            'delivery_manager' => 'مدير التوصيل',
            'approver_level_1' => 'موافق المستوى الأول',
            'approver_level_2' => 'موافق المستوى الثاني',
            'approver_level_3' => 'موافق المستوى الثالث',
            'manager' => 'مدير',
            'driver' => 'سائق / مندوب',
            'employee' => 'الموظف',
            'report_viewer' => 'عارض التقارير',
        ];

        foreach ($roles as $key => $value) {
            Role::firstOrCreate(
                ['name' => $key],
                ['description' => $value]
            );
        }

        $permissions = [
            // Employee Management
            ['name' => 'view_employees', 'group' => 'employees', 'description' => 'عرض الموظفين'],
            ['name' => 'create_employees', 'group' => 'employees', 'description' => 'إنشاء موظفين'],
            ['name' => 'edit_employees', 'group' => 'employees', 'description' => 'تعديل الموظفين'],
            ['name' => 'delete_employees', 'group' => 'employees', 'description' => 'حذف الموظفين'],

            // Requests Management
            ['name' => 'view_requests', 'group' => 'requests', 'description' => 'عرض الطلبات'],
            ['name' => 'create_requests', 'group' => 'requests', 'description' => 'إنشاء طلبات'],
            ['name' => 'edit_requests', 'group' => 'requests', 'description' => 'تعديل الطلبات'],
            ['name' => 'delete_requests', 'group' => 'requests', 'description' => 'حذف الطلبات'],
            ['name' => 'approve_requests', 'group' => 'requests', 'description' => 'موافقة الطلبات'],

            // Deliveries
            ['name' => 'view_deliveries', 'group' => 'deliveries', 'description' => 'عرض التسليمات'],
            ['name' => 'create_deliveries', 'group' => 'deliveries', 'description' => 'إنشاء تسليمات'],
            ['name' => 'edit_deliveries', 'group' => 'deliveries', 'description' => 'تعديل التسليمات'],
            ['name' => 'approve_routes', 'group' => 'deliveries', 'description' => 'موافقة خطوط السير'],

            // Collections
            ['name' => 'view_collections', 'group' => 'collections', 'description' => 'عرض التحصيلات'],
            ['name' => 'create_collections', 'group' => 'collections', 'description' => 'إنشاء تحصيلات'],
            ['name' => 'approve_collections', 'group' => 'collections', 'description' => 'موافقة التحصيلات'],

            // Attendance
            ['name' => 'view_attendance', 'group' => 'attendance', 'description' => 'عرض الحضور'],
            ['name' => 'manage_attendance', 'group' => 'attendance', 'description' => 'إدارة الحضور'],

            // Salary
            ['name' => 'view_salaries', 'group' => 'salaries', 'description' => 'عرض الرواتب'],
            ['name' => 'create_salaries', 'group' => 'salaries', 'description' => 'إنشاء رواتب'],
            ['name' => 'approve_salaries', 'group' => 'salaries', 'description' => 'موافقة الرواتب'],
            ['name' => 'manage_incentives', 'group' => 'salaries', 'description' => 'إدارة الحوافز'],
            ['name' => 'manage_deductions', 'group' => 'salaries', 'description' => 'إدارة الخصومات'],
            ['name' => 'manage_allowances', 'group' => 'salaries', 'description' => 'إدارة البدلات'],
            ['name' => 'manage_team_financials', 'group' => 'salaries', 'description' => 'إدارة مالية الفريق'],

            // Reports
            ['name' => 'view_reports', 'group' => 'reports', 'description' => 'عرض التقارير'],
            ['name' => 'export_reports', 'group' => 'reports', 'description' => 'تصدير التقارير'],

            // Dashboard
            ['name' => 'view_dashboard', 'group' => 'dashboard', 'description' => 'عرض لوحة التحكم'],

            // Shifts
            ['name' => 'view_shifts', 'group' => 'shifts', 'description' => 'عرض الورديات'],

            // Read-only salary sub-permissions (view-only variants)
            ['name' => 'view_incentives', 'group' => 'salaries', 'description' => 'عرض الحوافز'],
            ['name' => 'view_deductions', 'group' => 'salaries', 'description' => 'عرض الخصومات'],
            ['name' => 'view_advances', 'group' => 'salaries', 'description' => 'عرض السلف'],
            ['name' => 'view_allowances', 'group' => 'salaries', 'description' => 'عرض البدلات'],
            ['name' => 'view_commissions', 'group' => 'salaries', 'description' => 'عرض العمولات'],

            // Employee points
            ['name' => 'view_employee_points', 'group' => 'employees', 'description' => 'عرض نقاط الموظفين'],

            // Prepaid requests
            ['name' => 'view_prepaid_requests', 'group' => 'requests', 'description' => 'عرض تحضير الطلبيه'],

            // Delivery routes
            ['name' => 'view_routes', 'group' => 'deliveries', 'description' => 'عرض خطوط السير'],

            // Car violations
            ['name' => 'view_car_violations', 'group' => 'car_violations', 'description' => 'عرض مخالفات السيارات'],

            // Customers
            ['name' => 'view_customers', 'group' => 'customers', 'description' => 'عرض العملاء'],

            // Warehouses
            ['name' => 'view_warehouses', 'group' => 'warehouses', 'description' => 'عرض المخازن'],

            // Items
            ['name' => 'view_items', 'group' => 'items', 'description' => 'عرض الأصناف'],

            // Approvals
            ['name' => 'view_approvals', 'group' => 'approvals', 'description' => 'عرض الموافقات'],

            // Chat groups
            ['name' => 'view_chat_groups', 'group' => 'chat_groups', 'description' => 'عرض مجموعات الدردشة'],

            // Notifications
            ['name' => 'view_notifications', 'group' => 'notifications', 'description' => 'عرض الإشعارات'],

            // Work locations
            ['name' => 'view_work_locations', 'group' => 'work_locations', 'description' => 'عرض مواقع العمل'],

            // Tab permissions
            ['name' => 'view_tab_permissions', 'group' => 'settings', 'description' => 'عرض صلاحيات التابات'],

            // Users
            ['name' => 'view_users', 'group' => 'users', 'description' => 'عرض المستخدمين'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $permissions = Permission::all();
            $superAdminRole->permissions()->sync($permissions);
        }

        $collectionPermissions = Permission::whereIn('name', [
            'view_collections',
            'create_collections',
        ])->pluck('id');

        foreach (['hr_manager', 'driver'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && $collectionPermissions->isNotEmpty()) {
                $role->permissions()->syncWithoutDetaching($collectionPermissions);
            }
        }

        // Direct managers approve collections from mobile; HR can too (dashboard)
        $managerApprovePermissions = Permission::whereIn('name', [
            'view_collections',
            'approve_collections',
        ])->pluck('id');

        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole && $managerApprovePermissions->isNotEmpty()) {
            $managerRole->permissions()->syncWithoutDetaching($managerApprovePermissions);
        }

        // Manager team-financial permissions (allowances, incentives, deductions)
        $teamFinancialPerms = Permission::whereIn('name', [
            'manage_allowances',
            'manage_incentives',
            'manage_deductions',
        ])->pluck('id');

        if ($managerRole && $teamFinancialPerms->isNotEmpty()) {
            $managerRole->permissions()->syncWithoutDetaching($teamFinancialPerms);
        }

        $hrRole = Role::where('name', 'hr_manager')->first();
        $approveCollection = Permission::where('name', 'approve_collections')->first();
        if ($hrRole && $approveCollection) {
            $hrRole->permissions()->syncWithoutDetaching([$approveCollection->id]);
        }
    }
}
