<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeeTabPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeTabPermissionController
{
    /** Predefined tabs (key = numeric ID agreed with mobile) */
    public const PREDEFINED_TABS = [
        ['tab_name' => 'الرواتب',           'tab_key' => '1'],
        ['tab_name' => 'الحوافز',            'tab_key' => '2'],
        ['tab_name' => 'الخصومات',           'tab_key' => '3'],
        ['tab_name' => 'السلف',              'tab_key' => '4'],
        ['tab_name' => 'الحضور والانصراف',   'tab_key' => '10'],
        ['tab_name' => 'مجموعات الدردشة',   'tab_key' => '11'],
    ];

    /**
     * [PUBLIC/ADMIN] Get all available tabs: predefined + custom previously saved.
     * GET /api/employee-tabs/available
     */
    public function availableTabs(): JsonResponse
    {
        $predefinedKeys = collect(self::PREDEFINED_TABS)->pluck('tab_key')->toArray();

        // Custom tabs = any tab_key not in predefined list
        $customTabs = EmployeeTabPermission::whereNotIn('tab_key', $predefinedKeys)
            ->select('tab_name', 'tab_key')
            ->distinct()
            ->orderBy('tab_name')
            ->get()
            ->map(fn($t) => [
                'tab_name'     => $t->tab_name,
                'tab_key'      => $t->tab_key,
                'is_predefined' => false,
            ])
            ->toArray();

        $predefined = array_map(
            fn($t) => array_merge($t, ['is_predefined' => true]),
            self::PREDEFINED_TABS
        );

        return response()->json([
            'success' => true,
            'data'    => array_merge($predefined, $customTabs),
        ]);
    }

    /**
     * [ADMIN] Get all employees who have tab permissions configured.
     * GET /api/employee-tabs
     */
    public function index(): JsonResponse
    {
        $employees = Employee::with('tabPermissions')
            ->whereHas('tabPermissions')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department', 'position', 'status']);

        return response()->json([
            'success' => true,
            'data'    => $employees,
        ]);
    }

    /**
     * [ADMIN] Get tabs for a specific employee.
     * GET /api/employee-tabs/{employeeId}
     */
    public function show(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $tabs = EmployeeTabPermission::where('employee_id', $employeeId)
            ->orderBy('sort_order')
            ->get(['id', 'tab_name', 'tab_key', 'sort_order']);

        return response()->json([
            'success' => true,
            'data'    => [
                'employee' => [
                    'id'            => $employee->id,
                    'name'          => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'department'    => $employee->department,
                    'position'      => $employee->position,
                ],
                'tabs' => $tabs,
            ],
        ]);
    }

    /**
     * [ADMIN] Save (sync) tabs for a specific employee.
     * POST /api/employee-tabs/{employeeId}
     * Body: { tabs: [{ tab_name, tab_key, sort_order }] }
     */
    public function save(Request $request, int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);

        $validated = $request->validate([
            'tabs'                  => 'present|array',
            'tabs.*.tab_name'       => 'required|string|max:100',
            'tabs.*.tab_key'        => 'required|max:100',
            'tabs.*.sort_order'     => 'nullable|integer|min:0',
        ]);

        // Delete existing and re-insert (sync approach)
        EmployeeTabPermission::where('employee_id', $employeeId)->delete();

        $tabs = collect($validated['tabs'])->map(function ($tab, $index) use ($employeeId) {
            return [
                'employee_id' => $employeeId,
                'tab_name'    => $tab['tab_name'],
                'tab_key'     => (string) $tab['tab_key'],
                'sort_order'  => $tab['sort_order'] ?? $index,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        })->toArray();

        EmployeeTabPermission::insert($tabs);

        $savedTabs = EmployeeTabPermission::where('employee_id', $employeeId)
            ->orderBy('sort_order')
            ->get(['id', 'tab_name', 'tab_key', 'sort_order']);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الصلاحيات بنجاح',
            'data'    => $savedTabs,
        ]);
    }

    /**
     * [ADMIN] Delete all tabs for a specific employee.
     * DELETE /api/employee-tabs/{employeeId}
     */
    public function destroy(int $employeeId): JsonResponse
    {
        Employee::findOrFail($employeeId);
        EmployeeTabPermission::where('employee_id', $employeeId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف صلاحيات التابات بنجاح',
        ]);
    }

    /**
     * [MOBILE] Get the allowed tabs for the currently authenticated employee.
     * GET /api/me/tabs
     */
    public function myTabs(): JsonResponse
    {
        $user     = Auth::user();
        $employee = $user->employee ?? Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على بيانات الموظف',
            ], 404);
        }

        $tabs = EmployeeTabPermission::where('employee_id', $employee->id)
            ->orderBy('sort_order')
            ->get(['tab_name', 'tab_key', 'sort_order']);

        return response()->json([
            'success' => true,
            'data'    => [
                'employee_id'   => $employee->id,
                'employee_name' => $employee->name,
                'tabs'          => $tabs,
            ],
        ]);
    }
}
