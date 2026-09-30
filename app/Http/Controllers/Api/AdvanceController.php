<?php

namespace App\Http\Controllers\Api;

use App\Models\Advance;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvanceController
{
    use RestrictToSubordinates;

    public function index(Request $request): JsonResponse
    {
        $query = Advance::with('employee');

        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $this->scopeSubordinates($query);

        $advances = $query->orderByDesc('advance_date')->paginate($request->get('per_page', 15));

        $totals = [
            'total_amount'    => $query->sum('amount'),
            'total_remaining' => $query->sum('remaining_amount'),
        ];

        return response()->json(['success' => true, 'data' => $advances, 'totals' => $totals]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->normalizePayload($request->all());

        $validated = validator($input, [
            'employee_id'        => 'required|exists:employees,id',
            'amount'             => 'required|numeric|min:0',
            'advance_date'       => 'required|date',
            'installments_count' => 'required|integer|min:1',
            'reason'             => 'nullable|string',
        ])->validate();

        $this->validateSubordinate((int) $validated['employee_id']);

        $installmentAmount = round($validated['amount'] / $validated['installments_count'], 2);

        $advance = Advance::create(array_merge($validated, [
            'installment_amount'      => $installmentAmount,
            'paid_installments'       => 0,
            'remaining_installments'  => $validated['installments_count'],
            'remaining_amount'        => $validated['amount'],
            'status'                  => 'pending',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل السلفة بنجاح',
            'data'    => $advance->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Advance::with('employee')->findOrFail($id)]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $advance = Advance::findOrFail($id);
        if ($advance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل سلفة بعد اعتمادها'], 422);
        }

        $input = $this->normalizePayload($request->all());
        $validated = validator($input, [
            'employee_id'        => 'sometimes|exists:employees,id',
            'amount'             => 'sometimes|numeric|min:0',
            'advance_date'       => 'sometimes|date',
            'installments_count' => 'sometimes|integer|min:1',
            'reason'             => 'nullable|string',
        ])->validate();

        if (array_key_exists('amount', $validated) || array_key_exists('installments_count', $validated)) {
            $amount = $validated['amount'] ?? (float) $advance->amount;
            $installments = $validated['installments_count'] ?? $advance->installments_count;
            $validated['installment_amount'] = round($amount / $installments, 2);
            $validated['remaining_installments'] = $installments;
            $validated['remaining_amount'] = $amount;
        }

        if (!empty($validated['employee_id'])) {
            $this->validateSubordinate((int) $validated['employee_id']);
        }

        $advance->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السلفة بنجاح',
            'data' => $advance->load('employee'),
        ]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['notes' => 'nullable|string']);

        $advance = Advance::findOrFail($id);

        if ($advance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'تم التعامل مع هذه السلفة مسبقاً'], 422);
        }

        $advance->update(['status' => 'active']);

        return response()->json(['success' => true, 'message' => 'تم اعتماد السلفة وبدء الخصم', 'data' => $advance->fresh()]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string']);

        $advance = Advance::findOrFail($id);

        if ($advance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'تم التعامل مع هذه السلفة مسبقاً'], 422);
        }

        // 'paid' means مسدد / fully settled - it must never be used for a rejection,
        // otherwise a rejected advance reports its full amount as already collected
        // in employeeSummary()'s total_paid.
        $advance->update([
            'status' => 'rejected',
            'reason' => $validated['reason'] ?? $advance->reason,
        ]);

        return response()->json(['success' => true, 'message' => 'تم رفض السلفة', 'data' => $advance->fresh()]);
    }

    public function destroy($id): JsonResponse
    {
        $advance = Advance::findOrFail($id);
        if ($advance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'لا يمكن حذف سلفة بعد اعتمادها'], 422);
        }

        $advance->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف السلفة بنجاح']);
    }

    public function employeeSummary($employeeId): JsonResponse
    {
        $advances = Advance::where('employee_id', $employeeId)->get();

        $counted = $advances->whereNotIn('status', ['rejected']);

        $summary = [
            'total_advances'  => $counted->sum('amount'),
            'total_remaining' => $counted->whereIn('status', ['active', 'partially_paid'])->sum('remaining_amount'),
            // amount actually collected, per advance, is amount - remaining_amount.
            // Summing remaining_amount here would always be 0 for a settled advance.
            'total_paid'      => $counted->sum(fn (Advance $a) => (float) $a->amount - (float) $a->remaining_amount),
            'total_rejected'  => $advances->where('status', 'rejected')->sum('amount'),
            'active_count'    => $counted->whereIn('status', ['active', 'partially_paid'])->count(),
        ];

        return response()->json(['success' => true, 'data' => $advances, 'summary' => $summary]);
    }

    private function normalizePayload(array $input): array
    {
        if (!array_key_exists('advance_date', $input) && array_key_exists('request_date', $input)) {
            $input['advance_date'] = $input['request_date'];
        }
        if (!array_key_exists('installments_count', $input) && array_key_exists('installments', $input)) {
            $input['installments_count'] = $input['installments'];
        }

        unset($input['request_date'], $input['installments'], $input['start_month']);

        return $input;
    }
}
