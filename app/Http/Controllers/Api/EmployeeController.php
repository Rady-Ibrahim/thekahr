<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeTypeEnum;
use App\Exports\EmployeesExport;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Salary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController
{
    public function index(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_type')) {
            $query->where('employee_type', $request->employee_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sub_role')) {
            $query->where('sub_role', $request->sub_role);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $employees = $query->with(['manager', 'user.roles'])
            ->withCount('subordinates')
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'status', 'employee_type', 'sub_role', 'department']);
        $fileName = 'employees_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new EmployeesExport($filters), $fileName);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string',
            'email'         => ['required', 'email', Rule::unique('employees', 'email')->whereNull('deleted_at'), 'unique:users,email'],
            'phone'         => ['required', 'string', Rule::unique('employees', 'phone')->whereNull('deleted_at')],
            'employee_code' => ['required', 'string', Rule::unique('employees', 'employee_code')->whereNull('deleted_at')],
            'position'      => 'required|string',
            'department'    => 'required|string',
            'employee_type' => ['required', Rule::in(EmployeeTypeEnum::values())],
            'joining_date'  => 'required|date',
            'base_salary'   => 'required|numeric|min:0',
            'collection_commission_rate' => 'nullable|numeric|min:0|max:100',
            'status'        => 'required|in:active,inactive,suspended,resigned,on_leave',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'manager_id' => 'nullable|exists:employees,id',
            'car_number' => 'nullable|string',
            'car_license' => 'nullable|string',
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'national_id')->whereNull('deleted_at')],
            'notes' => 'nullable|string',
            'is_manager' => 'nullable|boolean',
            'password'      => 'required|string|min:6|confirmed',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['reporting_manager_id'] = $validated['reporting_manager_id'] ?? $validated['manager_id'] ?? null;
            $employeeType = $this->resolveEmployeeType($validated);
            unset($validated['manager_id'], $validated['is_manager']);
            $validated['employee_type'] = $employeeType->value;

            if (array_key_exists('national_id', $validated) && trim((string) $validated['national_id']) === '') {
                $validated['national_id'] = null;
            }

            $user = User::create([
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'] ?? null,
                'password'  => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            unset($validated['password'], $validated['password_confirmation']);
            $validated['user_id'] = $user->id;

            $employee = Employee::create($validated);
            $this->syncEmployeeTypeRole($user, $employeeType);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الموظف بنجاح',
                'data'    => $employee->load(['manager', 'user.roles']),
            ], 201);
        });
    }

    public function show($id)
    {
        $employee = Employee::with([
            'manager',
            'subordinates',
            'user.roles',
            'salaries',
            'attendances',
            'incentives',
            'deductions',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $employee,
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => ['sometimes', 'email', Rule::unique('employees', 'email')->whereNull('deleted_at')->ignore($id), Rule::unique('users', 'email')->ignore($employee->user_id)],
            'phone' => ['sometimes', 'string', Rule::unique('employees', 'phone')->whereNull('deleted_at')->ignore($id)],
            'position' => 'sometimes|string',
            'department' => 'sometimes|string',
            'employee_type' => ['sometimes', Rule::in(EmployeeTypeEnum::values())],
            'base_salary' => 'sometimes|numeric|min:0',
            'collection_commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,suspended,resigned,on_leave',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'manager_id' => 'nullable|exists:employees,id',
            'employee_code' => ['sometimes', 'string', Rule::unique('employees', 'employee_code')->whereNull('deleted_at')->ignore($id)],
            'joining_date' => 'sometimes|date',
            'car_number' => 'nullable|string',
            'car_license' => 'nullable|string',
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'national_id')->whereNull('deleted_at')->ignore($id)],
            'notes' => 'nullable|string',
            'is_manager' => 'nullable|boolean',
        ]);

        if (array_key_exists('manager_id', $validated) || array_key_exists('reporting_manager_id', $validated)) {
            $validated['reporting_manager_id'] = $validated['reporting_manager_id'] ?? $validated['manager_id'] ?? null;
        }

        if (array_key_exists('national_id', $validated) && trim((string) ($validated['national_id'] ?? '')) === '') {
            $validated['national_id'] = null;
        }

        $shouldSyncType = array_key_exists('employee_type', $validated) || array_key_exists('is_manager', $validated);
        $employeeType = $shouldSyncType
            ? $this->resolveEmployeeType($validated, $employee->employee_type)
            : null;

        unset($validated['manager_id'], $validated['is_manager']);
        if ($employeeType) {
            $validated['employee_type'] = $employeeType->value;
        }

        $employee->update($validated);

        if ($employeeType && $employee->user) {
            $this->syncEmployeeTypeRole($employee->user, $employeeType);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الموظف بنجاح',
            'data' => $employee->fresh(['manager', 'user.roles']),
        ]);
    }

    public function managers()
    {
        $employees = Employee::where(function ($query) {
                $query->where('employee_type', EmployeeTypeEnum::MANAGER->value)
                    ->orWhereHas('user.roles', function ($q) {
                        $q->where('name', 'manager')->orWhere('name', 'like', '%_manager');
                    })
                    ->orHas('subordinates');
            })
            ->with(['user.roles'])
            ->withCount('subordinates')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    public function peers(Request $request)
    {
        $currentEmployee = $this->currentEmployee();

        $query = Employee::with(['manager'])
            ->withCount('subordinates')
            ->where('status', 'active');

        if ($currentEmployee) {
            $query->where('id', '!=', $currentEmployee->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'current_employee' => $currentEmployee,
            'data' => $employees,
        ]);
    }

    public function myManager()
    {
        $employee = $this->currentEmployee()?->load('manager');

        return response()->json([
            'success' => true,
            'data' => $employee?->manager,
            'employee' => $employee,
        ]);
    }

    public function mySubordinates()
    {
        $employee = $this->currentEmployee();
        $subordinates = $employee
            ? Employee::where('reporting_manager_id', $employee->id)->orderBy('name')->get()
            : collect();

        return response()->json([
            'success' => true,
            'manager' => $employee,
            'data' => $subordinates,
        ]);
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        if ($employee->user_id) {
            User::where('id', $employee->user_id)->delete();
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الموظف بنجاح',
        ]);
    }

    public function getSalaryHistory($id)
    {
        $salaries = Salary::where('employee_id', $id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $salaries,
        ]);
    }

    public function getAttendanceRecords($id, Request $request)
    {
        $query = Attendance::where('employee_id', $id);

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('attendance_date', $request->month)
                ->whereYear('attendance_date', $request->year);
        } else {
            $query->whereMonth('attendance_date', now()->month)
                ->whereYear('attendance_date', now()->year);
        }

        $records = $query->orderBy('attendance_date')->get();

        $stats = [
            'present' => $records->where('status', 'present')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'late' => $records->where('status', 'late')->count(),
            'on_leave' => $records->where('status', 'on_leave')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $records,
            'statistics' => $stats,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended,resigned,on_leave',
        ]);

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الموظف بنجاح',
            'data' => $employee,
        ]);
    }

    public function updateManager(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $validated = $request->validate([
            'manager_id' => 'nullable|exists:employees,id',
            'reporting_manager_id' => 'nullable|exists:employees,id',
        ]);

        $managerId = $validated['reporting_manager_id'] ?? $validated['manager_id'] ?? null;
        if ($managerId && (int) $managerId === (int) $employee->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكن أن يكون الموظف مدير نفسه'], 422);
        }

        $employee->update(['reporting_manager_id' => $managerId]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث مدير الموظف',
            'data' => $employee->load('manager'),
        ]);
    }

    public function subordinates($id)
    {
        $manager = Employee::with('subordinates')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $manager->subordinates,
            'manager' => $manager,
        ]);
    }

    public function assignSubordinates(Request $request, $id)
    {
        $manager = Employee::findOrFail($id);
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        if (in_array((int) $manager->id, array_map('intval', $validated['employee_ids']), true)) {
            return response()->json(['success' => false, 'message' => 'لا يمكن إضافة المدير ضمن فريقه'], 422);
        }

        Employee::whereIn('id', $validated['employee_ids'])->update([
            'reporting_manager_id' => $manager->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث موظفي المدير',
            'data' => $manager->fresh('subordinates'),
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (!$employee->user_id) {
            return response()->json(['success' => false, 'message' => 'لا يوجد حساب مستخدم مرتبط بهذا الموظف'], 422);
        }

        User::where('id', $employee->user_id)->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }

    private function resolveEmployeeType(array $validated, ?EmployeeTypeEnum $fallback = null): EmployeeTypeEnum
    {
        if (!empty($validated['employee_type'])) {
            return EmployeeTypeEnum::from($validated['employee_type']);
        }

        if (array_key_exists('is_manager', $validated)) {
            return !empty($validated['is_manager'])
                ? EmployeeTypeEnum::MANAGER
                : ($fallback && $fallback !== EmployeeTypeEnum::MANAGER
                    ? $fallback
                    : EmployeeTypeEnum::EMPLOYEE);
        }

        return $fallback ?? EmployeeTypeEnum::EMPLOYEE;
    }

    private function syncEmployeeTypeRole(User $user, EmployeeTypeEnum $type): void
    {
        $typeRoles = [
            EmployeeTypeEnum::MANAGER->roleName(),
            EmployeeTypeEnum::EMPLOYEE->roleName(),
            EmployeeTypeEnum::DRIVER_REPRESENTATIVE->roleName(),
        ];

        foreach ($typeRoles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => match ($roleName) {
                    'manager' => 'مدير',
                    'driver' => 'سائق / مندوب',
                    default => 'موظف',
                }]
            );

            if ($roleName === $type->roleName()) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            } else {
                $user->roles()->detach($role->id);
            }
        }
    }

    private function currentEmployee(): ?Employee
    {
        if (auth()->id()) {
            $employee = Employee::where('user_id', auth()->id())->first();
            if ($employee) {
                return $employee;
            }
        }

        return Employee::find(1);
    }


    public function mobileList(Request $request)
    {
        $query = Employee::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        if ($request->filled('employee_type')) {
            $query->where('employee_type', $request->employee_type);
        }

        if ($request->filled('sub_role')) {
            $query->where('sub_role', $request->sub_role);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('name')->get([
            'id',
            'user_id',
            'name',
            'phone',
            'email',
            'employee_code',
            'department',
            'employee_type'

        ]);

        $data = $employees->map(function ($emp) {
            return [
                'id'                  => $emp->id,
                'user_id'             => $emp->user_id,
                'name'                => $emp->name,
                'phone'               => $emp->phone,
                'email'               => $emp->email,
                'employee_code'       => $emp->employee_code,
                'department'          => $emp->department,
                'employee_type'       => $emp->employee_type instanceof EmployeeTypeEnum ? $emp->employee_type->value : (string)$emp->employee_type,
                'employee_type_label' => $emp->employee_type_label,

            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
