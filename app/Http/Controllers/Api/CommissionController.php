<?php

namespace App\Http\Controllers\Api;

use App\Models\Commission;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController
{
    public function index(Request $request): JsonResponse
    {
        $query = Commission::with('employee');

        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('month'))       $query->where('month', $request->month);
        if ($request->filled('year'))        $query->where('year', $request->year);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $commissions = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $commissions]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->normalizePayload($request->all());

        $validated = validator($input, [
            'employee_id'     => 'required|exists:employees,id',
            'month'           => 'required|integer|min:1|max:12',
            'year'            => 'required|integer|min:2020',
            'amount'          => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'total_sales'     => 'nullable|numeric|min:0',
            'reason'          => 'nullable|string',
        ])->validate();

        $validated['status'] = 'pending';
        $commission = Commission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل العمولة بنجاح',
            'data'    => $commission->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Commission::with(['employee', 'approver'])->findOrFail($id)]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $commission = Commission::findOrFail($id);
        if ($commission->status === 'paid') {
            return response()->json(['success' => false, 'message' => 'لا يمكن تعديل عمولة مصروفة'], 422);
        }

        $input = $this->normalizePayload($request->all());
        $validated = validator($input, [
            'employee_id'     => 'sometimes|exists:employees,id',
            'month'           => 'sometimes|integer|min:1|max:12',
            'year'            => 'sometimes|integer|min:2020',
            'amount'          => 'sometimes|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'total_sales'     => 'nullable|numeric|min:0',
            'reason'          => 'nullable|string',
        ])->validate();

        $commission->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العمولة بنجاح',
            'data' => $commission->load('employee'),
        ]);
    }

    public function approve($id): JsonResponse
    {
        $commission = Commission::findOrFail($id);
        $approverId = Employee::where('user_id', auth()->id())->value('id');

        $commission->update([
            'status' => 'approved',
            'approved_by_id' => $approverId,
        ]);

        return response()->json(['success' => true, 'message' => 'تم اعتماد العمولة بنجاح', 'data' => $commission]);
    }

    public function reject($id): JsonResponse
    {
        Commission::findOrFail($id)->update(['status' => 'rejected']);
        return response()->json(['success' => true, 'message' => 'تم رفض العمولة']);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'commission_ids' => 'required|array|min:1',
            'commission_ids.*' => 'exists:commissions,id',
        ]);

        $approverId = Employee::where('user_id', auth()->id())->value('id');
        $count = Commission::whereIn('id', $validated['commission_ids'])
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by_id' => $approverId,
            ]);

        return response()->json([
            'success' => true,
            'message' => "تم اعتماد {$count} عمولة بنجاح",
            'count' => $count,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Commission::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف العمولة بنجاح']);
    }

    private function normalizePayload(array $input): array
    {
        if (!array_key_exists('amount', $input) && array_key_exists('commission_amount', $input)) {
            $input['amount'] = $input['commission_amount'];
        }
        if (($input['amount'] ?? '') === '' && ($input['total_sales'] ?? '') !== '' && ($input['commission_rate'] ?? '') !== '') {
            $input['amount'] = round(((float) $input['total_sales'] * (float) $input['commission_rate']) / 100, 2);
        }

        unset($input['commission_amount'], $input['calculation_method']);

        return $input;
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $commissions = Commission::where('month', $month)->where('year', $year)
            ->with('employee')->get();

        $summary = [
            'total'      => $commissions->sum('amount'),
            'approved'   => $commissions->where('status', 'approved')->sum('amount'),
            'pending'    => $commissions->where('status', 'pending')->sum('amount'),
            'count'      => $commissions->count(),
        ];

        return response()->json(['success' => true, 'data' => $commissions, 'summary' => $summary]);
    }
}
