<?php

namespace App\Http\Controllers\Api;

use App\Models\Allowance;
use App\Models\Employee;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeShiftController
{
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeShift::with(['employee', 'shift']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        if ($request->filled('active')) {
            $query->whereNull('effective_to');
        }

        $assignments = $query->orderByDesc('effective_from')->paginate($request->get('per_page', 50));

        return response()->json(['success' => true, 'data' => $assignments]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'extra_hours' => 'nullable|numeric|min:0.1',
            'hourly_rate' => 'nullable|numeric|min:0',
            'shift_value' => 'nullable|numeric|min:0',
            'extra_start_date' => 'nullable|date',
            'extra_end_date' => 'nullable|date|after_or_equal:extra_start_date',
        ]);

        $conflict = $this->findOverlap($validated['employee_id'], $validated['effective_from'], $validated['effective_to'] ?? null);

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إضافة الموظف ' . $conflict->employee?->name . ' لأنه معيّن بالفعل على وردية أخرى (' . $conflict->shift?->name . '). يجب أن يكون الموظف في وردية واحدة فقط',
            ], 422);
        }

        $data = DB::transaction(function () use ($validated) {
            $assignment = EmployeeShift::create([
                'employee_id'    => $validated['employee_id'],
                'shift_id'       => $validated['shift_id'],
                'effective_from' => $validated['effective_from'],
                'effective_to'   => $validated['effective_to'] ?? null,
            ]);

            $allowance = $this->syncShiftValueAllowance(
                $assignment,
                $validated['shift_value'] ?? null,
                $validated['extra_hours'] ?? null,
                $validated['hourly_rate'] ?? null,
                $validated['extra_start_date'] ?? null,
                $validated['extra_end_date'] ?? null
            );

            return compact('assignment', 'allowance');
        });

        return response()->json([
            'success' => true,
            'message' => $data['allowance']
                ? 'تم تعيين الوردية للموظف وإضافة بدل الوردية إلى الراتب بنجاح'
                : 'تم تعيين الوردية للموظف بنجاح',
            'data'      => $data['assignment']->load(['employee', 'shift']),
            'allowance' => $data['allowance'],
        ], 201);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'extra_hours' => 'nullable|numeric|min:0.1',
            'hourly_rate' => 'nullable|numeric|min:0',
            'shift_value' => 'nullable|numeric|min:0',
            'extra_start_date' => 'nullable|date',
            'extra_end_date' => 'nullable|date|after_or_equal:extra_start_date',
        ]);

        $conflicts = [];
        foreach ($validated['employee_ids'] as $empId) {
            $conflict = $this->findOverlap($empId, $validated['effective_from'], $validated['effective_to'] ?? null);
            if ($conflict) {
                $conflicts[] = [
                    'employee_id' => $empId,
                    'employee_name' => $conflict->employee?->name,
                    'existing_shift' => $conflict->shift?->name,
                ];
            }
        }

        if (count($conflicts) > 0) {
            $names = collect($conflicts)->pluck('employee_name')->filter()->implode('، ');

            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إضافة ' . $names . ' لأنهم معيّنون بالفعل على ورديات أخرى. كل موظف يجب أن يكون في وردية واحدة فقط',
                'conflicts' => $conflicts,
            ], 422);
        }

        $created = DB::transaction(function () use ($validated) {
            $created = [];
            foreach ($validated['employee_ids'] as $empId) {
                $assignment = EmployeeShift::create([
                    'employee_id'    => $empId,
                    'shift_id'       => $validated['shift_id'],
                    'effective_from' => $validated['effective_from'],
                    'effective_to'   => $validated['effective_to'] ?? null,
                ]);

                $this->syncShiftValueAllowance(
                    $assignment,
                    $validated['shift_value'] ?? null,
                    $validated['extra_hours'] ?? null,
                    $validated['hourly_rate'] ?? null,
                    $validated['extra_start_date'] ?? null,
                    $validated['extra_end_date'] ?? null
                );

                $created[] = $assignment;
            }

            return $created;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين الوردية لـ ' . count($created) . ' موظف بنجاح',
            'data' => $created,
        ], 201);
    }

    public function current(Request $request, $employeeId): JsonResponse
    {
        $assignment = EmployeeShift::with('shift')
            ->where('employee_id', $employeeId)
            ->active(now())
            ->first();

        if (!$assignment) {
            $defaultShift = \App\Models\Shift::where('is_active', true)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'assignment' => null,
                    'shift' => $defaultShift,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'assignment' => $assignment,
                'shift' => $assignment->shift,
            ],
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $assignment = EmployeeShift::findOrFail($id);

        $this->linkedShiftAllowanceQuery($assignment->employee_id, $assignment->id)->delete();

        $assignment->delete();

        return response()->json(['success' => true, 'message' => 'تم إلغاء تعيين الوردية']);
    }

    private function allowanceLink(int $assignmentId): string
    {
        return 'مرتبطة بتعيين وردية #' . $assignmentId;
    }

    private function linkedShiftAllowanceQuery(int $employeeId, int $assignmentId)
    {
        $link = $this->allowanceLink($assignmentId);

        return Allowance::where('employee_id', $employeeId)
            ->where('allowance_type', 'بدل وردية')
            ->where(function ($q) use ($link) {
                $q->where('notes', $link)
                  ->orWhere('notes', 'like', '%| ' . $link);
            });
    }

    private function syncShiftValueAllowance(EmployeeShift $assignment, $shiftValue, $extraHours = null, $hourlyRate = null, $extraStart = null, $extraEnd = null): ?Allowance
    {
        $link = $this->allowanceLink($assignment->id);

        if ($extraHours !== null && $extraHours !== '' && (float) $extraHours > 0
            && $hourlyRate !== null && $hourlyRate !== '' && (float) $hourlyRate >= 0) {
            $window     = $this->shiftAllowanceWindow($assignment, $extraStart, $extraEnd);
            $days       = $window['days'];
            $amount     = round((float) $extraHours * (float) $hourlyRate * $days, 2);
            $hoursLabel = rtrim(rtrim(number_format((float) $extraHours, 2), '0'), '.');
            $detail     = $days > 1
                ? 'بدل ساعات إضافية (' . $hoursLabel . ' ساعة/يوم × ' . $days . ' يوم)'
                : 'بدل ساعات إضافية (' . $hoursLabel . ' ساعة/يوم)';
            $notes      = $detail . ' | ' . $link;
            $startDate  = $window['start'];
            $endDate    = $window['end'];
        } elseif ($shiftValue !== null && $shiftValue !== '' && (float) $shiftValue > 0) {
            $amount    = round((float) $shiftValue, 2);
            $notes     = $link;
            $startDate = $assignment->effective_from ? $assignment->effective_from->toDateString() : now()->toDateString();
            $endDate   = $assignment->effective_to ? $assignment->effective_to->toDateString() : null;
        } else {
            return null;
        }

        $this->linkedShiftAllowanceQuery($assignment->employee_id, $assignment->id)->delete();

        return Allowance::create([
            'employee_id'    => $assignment->employee_id,
            'allowance_type' => 'بدل وردية',
            'amount'         => $amount,
            'start_date'     => $startDate,
            'end_date'       => $endDate,
            'recurring'      => false,
            'status'         => 'active',
            'notes'          => $notes,
        ]);
    }

    private function shiftAllowanceWindow(EmployeeShift $assignment, $extraStart, $extraEnd): array
    {
        // Dedicated extra-hours window wins; if only a start date is given,
        // the allowance covers that single day (end date = start date).
        if ($extraStart || $extraEnd) {
            $start = $extraStart ? Carbon::parse($extraStart)->toDateString() : ($assignment->effective_from ? $assignment->effective_from->toDateString() : now()->toDateString());
            $end   = $extraEnd ? Carbon::parse($extraEnd)->toDateString() : $start;
        } else {
            $start = $assignment->effective_from ? $assignment->effective_from->toDateString() : now()->toDateString();
            $end   = $assignment->effective_to ? $assignment->effective_to->toDateString() : $start;
        }

        return [
            'start' => $start,
            'end'   => $end,
            'days'  => $end ? max(1, Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1) : 1,
        ];
    }

    private function findOverlap(int $employeeId, string $effectiveFrom, ?string $effectiveTo): ?EmployeeShift
    {
        return EmployeeShift::with(['employee', 'shift'])
            ->where('employee_id', $employeeId)
            ->where(function ($q) use ($effectiveTo) {
                $q->where('effective_from', '<=', $effectiveTo ?? '9999-12-31');
            })
            ->where(function ($q) use ($effectiveFrom) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $effectiveFrom);
            })
            ->first();
    }
}
