<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController
{
    /**
     * GET /api/department
     * GET /api/departments
     */
    public function index(Request $request): JsonResponse
    {
        $dbDepartments = Department::orderBy('name')->get();

        if ($dbDepartments->isNotEmpty()) {
            $data = $dbDepartments->map(function ($dept) {
                return [
                    'id'          => $dept->id,
                    'name'        => $dept->name,
                    'description' => $dept->description,
                    'manager_id'  => $dept->manager_id,
                ];
            });
        } else {
            // Fallback to distinct departments from employees table
            $empDepartments = Employee::whereNotNull('department')
                ->where('department', '!=', '')
                ->select('department')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');

            $data = $empDepartments->map(function ($deptName, $index) {
                return [
                    'id'          => $index + 1,
                    'name'        => $deptName,
                    'description' => null,
                    'manager_id'  => null,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $data->values(),
        ]);
    }
}
