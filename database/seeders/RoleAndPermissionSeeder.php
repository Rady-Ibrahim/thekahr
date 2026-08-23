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
            'manager' => 'مدير',
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

            // Employee points
            ['name' => 'view_employee_points', 'group' => 'employees', 'description' => 'عرض نقاط الموظفين'],

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

        // Manager team-financial permissions (allowances, incentives, deductions)
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $teamFinancialPerms = Permission::whereIn('name', [
                'manage_allowances',
                'manage_incentives',
                'manage_deductions',
            ])->pluck('id');

            if ($teamFinancialPerms->isNotEmpty()) {
                $managerRole->permissions()->syncWithoutDetaching($teamFinancialPerms);
            }
        }
    }
}
