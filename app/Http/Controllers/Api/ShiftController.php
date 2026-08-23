<?php

namespace App\Http\Controllers\Api;

use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController
{
    public function index(Request $request): JsonResponse
    {
        $query = Shift::with(['lateRules', 'earlyExitRules']);

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $shifts = $request->filled('per_page')
            ? $query->orderBy('start_time')->paginate($request->per_page)
            : $query->orderBy('start_time')->get();

        return response()->json(['success' => true, 'data' => $shifts]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'is_active' => 'nullable|boolean',
            'late_rules' => 'nullable|array',
            'late_rules.*.min_delay_minutes' => 'required|integer|min:0',
            'late_rules.*.max_delay_minutes' => 'nullable|integer|min:0',
            'late_rules.*.deduction_type' => 'required|string|max:50',
            'late_rules.*.deduction_value' => 'nullable|numeric|min:0',
            'early_exit_rules' => 'nullable|array',
            'early_exit_rules.*.min_early_minutes' => 'required|integer|min:0',
            'early_exit_rules.*.max_early_minutes' => 'nullable|integer|min:0',
            'early_exit_rules.*.deduction_type' => 'required|string|max:50',
            'early_exit_rules.*.deduction_value' => 'nullable|numeric|min:0',
        ]);

        if (!empty($validated['late_rules'])) {
            $graceMin = (int) ($validated['grace_period_minutes'] ?? 20) + 1;
            foreach ($validated['late_rules'] as $index => $rule) {
                if ((int) $rule['min_delay_minutes'] < $graceMin) {
                    return response()->json([
                        'success' => false,
                        'message' => "قاعدة التأخير رقم " . ($index + 1) . ": الحد الأدنى للتأخير لا يمكن أن يقل عن {$graceMin} دقيقة — الحساب يبدأ بعد فترة السماح",
                    ], 422);
                }
            }
        }

        $shift = Shift::create([
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 15,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['late_rules'])) {
            foreach ($validated['late_rules'] as $rule) {
                $shift->lateRules()->create($rule);
            }
        }

        if (!empty($validated['early_exit_rules'])) {
            foreach ($validated['early_exit_rules'] as $rule) {
                $shift->earlyExitRules()->create($rule);
            }
        }

        $shift->load(['lateRules', 'earlyExitRules']);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الوردية بنجاح',
            'data' => $shift,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $shift = Shift::with(['lateRules', 'earlyExitRules', 'employeeAssignments.employee'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $shift]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $shift = Shift::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|nullable|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'is_active' => 'nullable|boolean',
            'late_rules' => 'nullable|array',
            'late_rules.*.id' => 'nullable|exists:shift_late_rules,id',
            'late_rules.*.min_delay_minutes' => 'required|integer|min:0',
            'late_rules.*.max_delay_minutes' => 'nullable|integer|min:0',
            'late_rules.*.deduction_type' => 'required|string|max:50',
            'late_rules.*.deduction_value' => 'nullable|numeric|min:0',
            'early_exit_rules' => 'nullable|array',
            'early_exit_rules.*.id' => 'nullable|exists:shift_early_exit_rules,id',
            'early_exit_rules.*.min_early_minutes' => 'required|integer|min:0',
            'early_exit_rules.*.max_early_minutes' => 'nullable|integer|min:0',
            'early_exit_rules.*.deduction_type' => 'required|string|max:50',
            'early_exit_rules.*.deduction_value' => 'nullable|numeric|min:0',
        ]);

        if ($request->has('late_rules') && !empty($validated['late_rules'])) {
            $graceMin = (int) ($validated['grace_period_minutes'] ?? $shift->grace_period_minutes) + 1;
            foreach ($validated['late_rules'] as $index => $rule) {
                if ((int) $rule['min_delay_minutes'] < $graceMin) {
                    return response()->json([
                        'success' => false,
                        'message' => "قاعدة التأخير رقم " . ($index + 1) . ": الحد الأدنى للتأخير لا يمكن أن يقل عن {$graceMin} دقيقة — الحساب يبدأ بعد فترة السماح",
                    ], 422);
                }
            }
        }

        $shift->update($validated);

        if ($request->has('late_rules')) {
            $existingLateIds = $shift->lateRules()->pluck('id')->toArray();
            $incomingLateIds = array_filter(array_column($validated['late_rules'] ?? [], 'id'));

            foreach ($existingLateIds as $existingId) {
                if (!in_array($existingId, $incomingLateIds)) {
                    $shift->lateRules()->where('id', $existingId)->delete();
                }
            }

            foreach ($validated['late_rules'] as $rule) {
                if (!empty($rule['id'])) {
                    $shift->lateRules()->where('id', $rule['id'])->update([
                        'min_delay_minutes' => $rule['min_delay_minutes'],
                        'max_delay_minutes' => $rule['max_delay_minutes'],
                        'deduction_type' => $rule['deduction_type'],
                        'deduction_value' => $rule['deduction_value'] ?? null,
                    ]);
                } else {
                    $shift->lateRules()->create($rule);
                }
            }
        }

        if ($request->has('early_exit_rules')) {
            $existingEarlyIds = $shift->earlyExitRules()->pluck('id')->toArray();
            $incomingEarlyIds = array_filter(array_column($validated['early_exit_rules'] ?? [], 'id'));

            foreach ($existingEarlyIds as $existingId) {
                if (!in_array($existingId, $incomingEarlyIds)) {
                    $shift->earlyExitRules()->where('id', $existingId)->delete();
                }
            }

            foreach ($validated['early_exit_rules'] as $rule) {
                if (!empty($rule['id'])) {
                    $shift->earlyExitRules()->where('id', $rule['id'])->update([
                        'min_early_minutes' => $rule['min_early_minutes'],
                        'max_early_minutes' => $rule['max_early_minutes'],
                        'deduction_type' => $rule['deduction_type'],
                        'deduction_value' => $rule['deduction_value'] ?? null,
                    ]);
                } else {
                    $shift->earlyExitRules()->create($rule);
                }
            }
        }

        $shift->load(['lateRules', 'earlyExitRules']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الوردية بنجاح',
            'data' => $shift,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $shift = Shift::withCount('employeeAssignments')->findOrFail($id);

        if ($shift->employee_assignments_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف الوردية لأنها مرتبطة بموظفين',
            ], 422);
        }

        $shift->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الوردية بنجاح']);
    }
}
