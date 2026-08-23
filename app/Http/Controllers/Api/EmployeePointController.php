<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\EmployeePoint;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeePointController
{
    use RestrictToSubordinates;

    /**
     * [ADMIN] Get list of points records.
     * GET /api/employee-points
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeePoint::with('employee:id,name,employee_code,department');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('day')) {
            $query->whereDay('created_at', $request->day);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('employee_code', 'like', "%{$search}%");
                  });
            });
        }

        $this->scopeSubordinates($query);

        $records = $query->orderByDesc('id')->paginate($request->get('per_page', 15));

        // Aggregate statistics for current filters
        $totalCreditPoints = (float) (clone $query)->where('type', 'credit')->sum('points');
        $totalDebitPoints  = (float) (clone $query)->where('type', 'debit')->sum('points');

        $totalCreditAmount = (float) (clone $query)->where('type', 'credit')->sum('total_amount');
        $totalDebitAmount  = (float) (clone $query)->where('type', 'debit')->sum('total_amount');

        return response()->json([
            'success' => true,
            'data'    => $records,
            'summary' => [
                'total_credit_points' => $totalCreditPoints,
                'total_debit_points'  => $totalDebitPoints,
                'total_credit_amount' => $totalCreditAmount,
                'total_debit_amount'  => $totalDebitAmount,
                'net_amount'          => $totalCreditAmount - $totalDebitAmount,
            ],
        ]);
    }

    /**
     * [ADMIN] Add new points record for an employee.
     * POST /api/employee-points
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids'   => 'required_without:employee_id|array',
            'employee_ids.*' => 'exists:employees,id',
            'employee_id'    => 'required_without:employee_ids|exists:employees,id',
            'type'           => 'required|in:credit,debit',
            'points'         => 'required|numeric|gt:0',
            'point_price'    => 'required|numeric|min:0',
            'reason'         => 'required|string|max:1000',
            'month'          => 'nullable|integer|min:1|max:12',
            'year'           => 'nullable|integer|min:2020|max:2050',
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', $validated['employee_ids'] ?? [$validated['employee_id']])));

        foreach ($employeeIds as $empId) {
            $this->validateSubordinate($empId);
        }

        $points     = (float) $validated['points'];
        $pointPrice = (float) $validated['point_price'];
        $total      = round($points * $pointPrice, 2);

        $month = $validated['month'] ?? now()->month;
        $year  = $validated['year'] ?? now()->year;

        $records = [];
        foreach ($employeeIds as $empId) {
            $records[] = EmployeePoint::create([
                'employee_id'   => $empId,
                'type'          => $validated['type'],
                'points'        => $points,
                'point_price'   => $pointPrice,
                'total_amount'  => $total,
                'reason'        => $validated['reason'],
                'month'         => $month,
                'year'          => $year,
                'created_by_id' => Auth::id(),
            ]);
        }

        $data = count($records) === 1
            ? $records[0]->load('employee:id,name,employee_code')
            : EmployeePoint::whereIn('id', array_column($records, 'id'))->with('employee:id,name,employee_code')->get();

        return response()->json([
            'success' => true,
            'message' => count($records) > 1
                ? 'تم إضافة النقاط لجميع الموظفين المحددين بنجاح'
                : 'تم إضافة النقاط بنجاح',
            'data'    => $data,
        ], 201);
    }

    /**
     * [ADMIN] Show specific points record.
     * GET /api/employee-points/{id}
     */
    public function show($id): JsonResponse
    {
        $record = EmployeePoint::with(['employee:id,name,employee_code,department', 'creator:id,name'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $record,
        ]);
    }

    /**
     * [ADMIN] Delete points record.
     * DELETE /api/employee-points/{id}
     */
    public function destroy($id): JsonResponse
    {
        $record = EmployeePoint::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف سجل النقاط بنجاح',
        ]);
    }

    /**
     * [MOBILE] Get authenticated employee's points history & balance.
     * GET /api/me/points
     */
    public function myPoints(Request $request): JsonResponse
    {
        $user     = Auth::user();
        $employee = $user->employee ?? Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على بيانات الموظف',
            ], 404);
        }

        $month = $request->filled('month') ? $request->month : now()->month;
        $year  = $request->filled('year')  ? $request->year  : now()->year;

        $query = EmployeePoint::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year);

        $records = $query->orderByDesc('id')->paginate($request->get('per_page', 20));

        $totalCreditPoints = (float) (clone $query)->where('type', 'credit')->sum('points');
        $totalDebitPoints  = (float) (clone $query)->where('type', 'debit')->sum('points');
        $totalCreditAmount = (float) (clone $query)->where('type', 'credit')->sum('total_amount');
        $totalDebitAmount  = (float) (clone $query)->where('type', 'debit')->sum('total_amount');

        return response()->json([
            'success' => true,
            'data'    => [
                'employee' => [
                    'id'            => $employee->id,
                    'name'          => $employee->name,
                    'employee_code' => $employee->employee_code,
                ],
                'summary' => [
                    'total_credit_points' => $totalCreditPoints,
                    'total_debit_points'  => $totalDebitPoints,
                    'net_points'          => $totalCreditPoints - $totalDebitPoints,
                    'total_credit_amount' => $totalCreditAmount,
                    'total_debit_amount'  => $totalDebitAmount,
                    'net_amount'          => $totalCreditAmount - $totalDebitAmount,
                ],
                'records' => $records,
            ],
        ]);
    }
}
