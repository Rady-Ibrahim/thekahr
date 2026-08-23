<?php

namespace App\Http\Controllers\Api;

use App\Models\Deduction;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeductionController
{
    use RestrictToSubordinates;

    public function index(Request $request): JsonResponse
    {
        $query = Deduction::with('employee');

        if ($request->filled('employee_id'))   $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('deduction_type')) $query->where('deduction_type', $request->deduction_type);
        if ($request->filled('month'))         $query->where('month', $request->month);
        if ($request->filled('year'))          $query->where('year', $request->year);

        $this->scopeSubordinates($query);

        $deductions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $deductions]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'month'          => 'required|integer|min:1|max:12',
            'year'           => 'required|integer|min:2020',
            'amount'         => 'required|numeric|min:0',
            'deduction_type' => 'required|string|max:100',
            'reason'         => 'nullable|string',
        ]);

        $this->validateSubordinate((int) $validated['employee_id']);

        $me = $this->getCurrentEmployee();
        $validated['applied_by_id'] = $me?->id;
        $validated['status']        = 'approved';

        $deduction = Deduction::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الخصم بنجاح',
            'data'    => $deduction->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Deduction::with(['employee', 'appliedBy'])->findOrFail($id)]);
    }

    public function approve($id): JsonResponse
    {
        Deduction::findOrFail($id)->update(['status' => 'approved']);
        return response()->json(['success' => true, 'message' => 'تم اعتماد الخصم بنجاح']);
    }

    public function reject($id): JsonResponse
    {
        Deduction::findOrFail($id)->update(['status' => 'rejected']);
        return response()->json(['success' => true, 'message' => 'تم رفض الخصم']);
    }

    public function destroy($id): JsonResponse
    {
        Deduction::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف الخصم بنجاح']);
    }
}
