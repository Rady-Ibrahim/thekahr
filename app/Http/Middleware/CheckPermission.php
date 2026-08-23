<?php

namespace App\Http\Middleware;

use App\Enums\EmployeeTypeEnum;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 401);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        // Collections: HR and driver/representatives can create; managers can approve
        if (in_array('create_collections', $permissions, true)) {
            if ($user->hasAnyRole(['hr_manager', 'driver'])) {
                return $next($request);
            }

            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee && $employee->employee_type === EmployeeTypeEnum::DRIVER_REPRESENTATIVE) {
                return $next($request);
            }
        }

        if (in_array('approve_collections', $permissions, true)) {
            if ($user->hasAnyRole(['hr_manager', 'manager'])) {
                return $next($request);
            }

            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee && $employee->employee_type === EmployeeTypeEnum::MANAGER) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
        ], 403);
    }
}
