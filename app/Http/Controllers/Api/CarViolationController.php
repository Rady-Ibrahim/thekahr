<?php

namespace App\Http\Controllers\Api;

use App\Models\CarViolation;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarViolationController
{
    use RestrictToSubordinates;

    public function index(Request $request): JsonResponse
    {
        $query = CarViolation::with('employee');

        if ($request->filled('employee_id'))   $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('violation_type')) $query->where('violation_type', $request->violation_type);

        $this->scopeSubordinates($query);

        $violations = $query->orderByDesc('violation_date')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $violations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'vehicle_number' => 'nullable|string|max:50',
            'violation_type' => 'required|string|max:100',
            'violation_date' => 'required|date',
            'violation_code' => 'nullable|string|max:50',
            'fine_amount'    => 'required|numeric|min:0',
            'reason'         => 'nullable|string',
        ]);

        $this->validateSubordinate((int) $validated['employee_id']);

        $validated['status'] = 'pending';
        $violation = CarViolation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل المخالفة بنجاح وسيتم خصمها من الراتب',
            'data'    => $violation->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => CarViolation::with('employee')->findOrFail($id)]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $violation = CarViolation::findOrFail($id);
        $validated = $request->validate([
            'status'      => 'sometimes|in:pending,paid,waived,disputed',
            'fine_amount' => 'sometimes|numeric|min:0',
            'reason'      => 'nullable|string',
        ]);

        $violation->update($validated);

        return response()->json(['success' => true, 'message' => 'تم تحديث المخالفة', 'data' => $violation]);
    }

    public function waive($id): JsonResponse
    {
        CarViolation::findOrFail($id)->update(['status' => 'waived']);
        return response()->json(['success' => true, 'message' => 'تم إلغاء المخالفة']);
    }

    public function employeeSummary($employeeId): JsonResponse
    {
        $violations = CarViolation::where('employee_id', $employeeId)->get();

        $summary = [
            'total_fines'    => $violations->sum('fine_amount'),
            'pending_fines'  => $violations->where('status', 'pending')->sum('fine_amount'),
            'paid_fines'     => $violations->where('status', 'paid')->sum('fine_amount'),
            'waived_fines'   => $violations->where('status', 'waived')->sum('fine_amount'),
            'count'          => $violations->count(),
        ];

        return response()->json(['success' => true, 'data' => $violations, 'summary' => $summary]);
    }
}
