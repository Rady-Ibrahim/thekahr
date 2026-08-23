<?php

namespace App\Http\Controllers\Api\Traits;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

trait RestrictToSubordinates
{
    private function isAdminUser(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        if ($user->hasRole('super_admin')) return true;
        if ($user->hasRole('admin')) return true;

        $managementRoles = [
            'manager', 'finance_manager', 'hr_manager', 'operations_manager',
            'delivery_manager', 'warehouse_manager',
            'approver_level_1', 'approver_level_2', 'approver_level_3',
        ];
        foreach ($managementRoles as $role) {
            if ($user->hasRole($role)) return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('manage_team_financials')) return true;

        return false;
    }

    private function getCurrentEmployee(): ?Employee
    {
        $user = Auth::user();
        if (!$user) return null;

        return $user->employee
            ?? Employee::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->first();
    }

    private function isManager(): bool
    {
        $emp = $this->getCurrentEmployee();
        return $emp && $emp->is_manager;
    }

    private function getMySubordinateIds(): array
    {
        $emp = $this->getCurrentEmployee();
        if (!$emp) return [];
        return $emp->subordinates()->pluck('id')->toArray();
    }

    private function validateSubordinate(int $employeeId): void
    {
        if ($this->isAdminUser()) return;

        $emp = $this->getCurrentEmployee();
        if (!$emp) {
            abort(401, 'غير مصرح - لا يوجد ملف موظف مرتبط');
        }

        if (!$emp->is_manager) {
            if ($employeeId !== $emp->id) {
                abort(403, 'غير مصرح لك. يمكنك إضافة سجلات لنفسك فقط.');
            }
            return;
        }

        $exists = $emp->subordinates()->where('id', $employeeId)->exists();
        if (!$exists) {
            abort(403, 'هذا الموظف ليس ضمن فريقك');
        }
    }

    private function scopeSubordinates($query, string $employeeColumn = 'employee_id')
    {
        if ($this->isAdminUser()) return $query;

        $emp = $this->getCurrentEmployee();
        if (!$emp) return $query;

        if (!$emp->is_manager) {
            $query->where($employeeColumn, $emp->id);
            return $query;
        }

        $subIds = $this->getMySubordinateIds();
        $subIds[] = $emp->id;
        $query->whereIn($employeeColumn, $subIds);

        return $query;
    }
}
