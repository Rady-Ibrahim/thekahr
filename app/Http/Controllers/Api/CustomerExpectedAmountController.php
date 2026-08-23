<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerDailyExpectedAmount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerExpectedAmountController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $record = CustomerDailyExpectedAmount::updateOrCreate(
            [
                'customer_id' => $validated['customer_id'],
                'date' => today(),
            ],
            [
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ المبلغ المستحق',
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = CustomerDailyExpectedAmount::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $record->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المبلغ المستحق',
            'data' => $record,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        CustomerDailyExpectedAmount::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المبلغ المستحق',
        ]);
    }

    public function history($customerId): JsonResponse
    {
        $records = CustomerDailyExpectedAmount::where('customer_id', $customerId)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}
