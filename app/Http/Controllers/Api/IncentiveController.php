<?php

namespace App\Http\Controllers\Api;

use App\Models\Incentive;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncentiveController
{
    use RestrictToSubordinates;

    public function index(Request $request): JsonResponse
    {
        $query = Incentive::with('employee');

        if ($request->filled('employee_id'))   $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('incentive_type')) $query->where('incentive_type', $request->incentive_type);
        if ($request->filled('month'))         $query->where('month', $request->month);
        if ($request->filled('year'))          $query->where('year', $request->year);

        $this->scopeSubordinates($query);

        $incentives = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $incentives]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'month'          => 'required|integer|min:1|max:12',
            'year'           => 'required|integer|min:2020',
            'amount'         => 'required|numeric|min:0',
            'incentive_type' => 'required|string|max:100',
            'reason'         => 'nullable|string',
        ]);

        $this->validateSubordinate((int) $validated['employee_id']);

        $me = $this->getCurrentEmployee();
        $data = array_merge($validated, [
            'status'         => 'approved',
            'approved_by_id' => $me?->id,
        ]);
        $incentive = Incentive::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الحافز بنجاح',
            'data'    => $incentive->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $incentive = Incentive::with(['employee', 'approver'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $incentive]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $incentive = Incentive::findOrFail($id);

        if ($incentive->status === 'approved') {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل حافز معتمد'], 422);
        }

        $validated = $request->validate([
            'amount'         => 'sometimes|numeric|min:0',
            'incentive_type' => 'sometimes|string|max:100',
            'reason'         => 'nullable|string',
        ]);

        $incentive->update($validated);

        return response()->json(['success' => true, 'message' => 'تم تحديث الحافز بنجاح', 'data' => $incentive]);
    }

    public function approve($id): JsonResponse
    {
        $incentive = Incentive::findOrFail($id);
        $incentive->update([
            'status'         => 'approved',
            'approved_by_id' => $this->getCurrentEmployee()?->id,
        ]);

        return response()->json(['success' => true, 'message' => 'تم اعتماد الحافز بنجاح', 'data' => $incentive]);
    }

    public function reject($id): JsonResponse
    {
        Incentive::findOrFail($id)->update(['status' => 'rejected']);
        return response()->json(['success' => true, 'message' => 'تم رفض الحافز']);
    }

    public function destroy($id): JsonResponse
    {
        $incentive = Incentive::findOrFail($id);
        if ($incentive->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف حافز مصروف'], 422);
        }
        $incentive->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف الحافز بنجاح']);
    }
}
