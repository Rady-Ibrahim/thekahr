<?php

namespace App\Http\Controllers\Api;

use App\Models\Allowance;
use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllowanceController
{
    use RestrictToSubordinates;

    public function index(Request $request): JsonResponse
    {
        $query = Allowance::with('employee');

        if ($request->filled('employee_id'))    $query->where('employee_id', $request->employee_id);
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('allowance_type')) {
            $query->where('allowance_type', 'like', '%' . $request->allowance_type . '%');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('start_date', $request->month)
                ->whereYear('start_date', $request->year);
        }

        $this->scopeSubordinates($query);

        $allowances = $query->orderByDesc('start_date')->paginate($request->get('per_page', 15));
        $allowances->getCollection()->transform(function ($allowance) {
            $allowance->month = optional($allowance->start_date)->month;
            $allowance->year = optional($allowance->start_date)->year;
            $allowance->is_recurring = (bool) $allowance->recurring;
            return $allowance;
        });

        return response()->json(['success' => true, 'data' => $allowances]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'allowance_type' => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0',
            'month'          => 'nullable|integer|min:1|max:12',
            'year'           => 'nullable|integer|min:2000|max:2100',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'recurring'      => 'boolean',
            'is_recurring'   => 'boolean',
            'notes'          => 'nullable|string',
            'reason'         => 'nullable|string',
        ]);

        $this->validateSubordinate((int) $validated['employee_id']);

        $me = $this->getCurrentEmployee();
        $month = $validated['month'] ?? now()->month;
        $year = $validated['year'] ?? now()->year;
        $validated['allowance_type'] = trim($validated['allowance_type']);
        $validated['start_date'] = $validated['start_date'] ?? sprintf('%04d-%02d-01', $year, $month);
        $validated['recurring'] = $validated['recurring'] ?? $validated['is_recurring'] ?? false;
        unset($validated['month'], $validated['year'], $validated['is_recurring']);

        $validated['applied_by_id'] = $me?->id;
        $validated['status'] = 'active';
        $allowance = Allowance::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة البدل بنجاح',
            'data'    => $allowance->load('employee'),
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $allowance = Allowance::with('employee')->findOrFail($id);
        $allowance->month = optional($allowance->start_date)->month;
        $allowance->year = optional($allowance->start_date)->year;
        $allowance->is_recurring = (bool) $allowance->recurring;

        return response()->json(['success' => true, 'data' => $allowance]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $allowance = Allowance::findOrFail($id);
        $validated = $request->validate([
            'employee_id'    => 'sometimes|exists:employees,id',
            'allowance_type' => 'sometimes|string|max:100',
            'amount'         => 'sometimes|numeric|min:0',
            'month'          => 'nullable|integer|min:1|max:12',
            'year'           => 'nullable|integer|min:2000|max:2100',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date',
            'recurring'      => 'boolean',
            'is_recurring'   => 'boolean',
            'status'         => 'sometimes|in:active,inactive,paused',
            'notes'          => 'nullable|string',
            'reason'         => 'nullable|string',
        ]);

        if (!empty($validated['employee_id'])) {
            $this->validateSubordinate((int) $validated['employee_id']);
        }

        if (!empty($validated['month']) || !empty($validated['year'])) {
            $month = $validated['month'] ?? optional($allowance->start_date)->month ?? now()->month;
            $year = $validated['year'] ?? optional($allowance->start_date)->year ?? now()->year;
            $validated['start_date'] = sprintf('%04d-%02d-01', $year, $month);
        }
        if (array_key_exists('is_recurring', $validated)) {
            $validated['recurring'] = $validated['is_recurring'];
        }
        unset($validated['month'], $validated['year'], $validated['is_recurring']);

        $allowance->update($validated);

        return response()->json(['success' => true, 'message' => 'تم تحديث البدل بنجاح', 'data' => $allowance]);
    }

    public function destroy($id): JsonResponse
    {
        Allowance::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف البدل بنجاح']);
    }

    public function types(): JsonResponse
    {
        $types = Allowance::query()
            ->whereNotNull('allowance_type')
            ->where('allowance_type', '!=', '')
            ->distinct()
            ->orderBy('allowance_type')
            ->pluck('allowance_type')
            ->values();

        return response()->json(['success' => true, 'data' => $types]);
    }

    public function employeeAllowances($employeeId): JsonResponse
    {
        $allowances = Allowance::where('employee_id', $employeeId)->where('status', 'active')->get();
        $total      = $allowances->sum('amount');

        return response()->json([
            'success'    => true,
            'data'       => $allowances,
            'total'      => $total,
            'by_type'    => $allowances->groupBy('allowance_type')->map->sum('amount'),
        ]);
    }
}
