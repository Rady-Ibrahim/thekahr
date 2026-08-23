<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with(['roles', 'permissions', 'employee'])
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")
                ->orWhere('phone', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate($request->per_page ?? 50);

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'],
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data'    => $user->load('roles'),
        ], 201);
    }

    public function show($id)
    {
        $user = User::with(['roles', 'permissions', 'employee'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function sidebarPermissions()
    {
        $sidebarPerms = [
            ['section' => 'القائمة الرئيسية', 'perms' => [
                ['name' => 'view_dashboard', 'label' => 'لوحة التحكم'],
            ]],
            ['section' => 'الموارد البشرية', 'perms' => [
                ['name' => 'view_employees', 'label' => 'الموظفين'],
                ['name' => 'view_attendance', 'label' => 'الحضور والانصراف'],
                ['name' => 'view_shifts', 'label' => 'الورديات'],
                ['name' => 'view_salaries', 'label' => 'الرواتب'],
                ['name' => 'view_incentives', 'label' => 'الحوافز'],
                ['name' => 'view_deductions', 'label' => 'الخصومات'],
                ['name' => 'view_advances', 'label' => 'السلف'],
                ['name' => 'view_allowances', 'label' => 'البدلات'],
                ['name' => 'view_employee_points', 'label' => 'نقاط الموظفين'],
            ]],
            ['section' => 'التشغيل', 'perms' => [
                ['name' => 'view_requests', 'label' => 'الطلبات'],
                ['name' => 'view_prepaid_requests', 'label' => ' تحضير الطلبيه'],
                ['name' => 'view_routes', 'label' => 'خطوط السير'],
                ['name' => 'view_deliveries', 'label' => 'التسليمات'],
                ['name' => 'view_collections', 'label' => 'التحصيلات'],
                ['name' => 'view_commissions', 'label' => 'العمولات'],
                ['name' => 'view_car_violations', 'label' => 'مخالفات السيارات'],
            ]],
            ['section' => 'الإدارة', 'perms' => [
                ['name' => 'view_customers', 'label' => 'العملاء'],
                ['name' => 'view_warehouses', 'label' => 'المخازن'],
                ['name' => 'view_items', 'label' => 'الأصناف'],
                ['name' => 'view_approvals', 'label' => 'الموافقات'],
                ['name' => 'view_chat_groups', 'label' => 'مجموعات الدردشة'],
                ['name' => 'view_notifications', 'label' => 'الإشعارات'],
                ['name' => 'view_reports', 'label' => 'التقارير'],
                ['name' => 'view_work_locations', 'label' => 'مواقع العمل'],
                ['name' => 'view_tab_permissions', 'label' => 'صلاحيات التابات'],
                ['name' => 'view_users', 'label' => 'المستخدمين'],
                ['name' => 'manage_team_financials', 'label' => 'إدارة مالية الفريق'],
            ]],
        ];

        return response()->json(['success' => true, 'data' => $sidebarPerms]);
    }

    public function allPermissions()
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name']);
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function roles()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function updatePermissions(Request $request, $id)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $user = User::findOrFail($id);
        $user->permissions()->sync($request->permissions ?? []);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصلاحيات بنجاح',
        ]);
    }
}
