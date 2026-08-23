<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\IdealEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IdealEmployeeController
{
    /**
     * [MOBILE/ADMIN] get the selected ideal employee for the month and each week.
     * GET /api/ideal?month=8&year=2026
     */
    public function index(Request $request): JsonResponse
    {
        $month = (int) ($request->filled('month') ? $request->month : Carbon::now()->month);
        $year  = (int) ($request->filled('year')  ? $request->year  : Carbon::now()->year);

        $monthDate = Carbon::create($year, $month, 1);

        $selectedMonth = IdealEmployee::query()
            ->with('employee')
            ->where('period', 'month')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $selectedWeeks = IdealEmployee::query()
            ->with('employee')
            ->where('period', 'week')
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('week');

        $weeks = collect($this->buildWeeks($year, $month))->map(function ($week) use ($selectedWeeks) {
            return [
                'week'           => $week['week'],
                'start_date'     => $week['start_date'],
                'end_date'       => $week['end_date'],
                'ideal_employee' => $this->formatEmployee($selectedWeeks->get($week['week'])?->employee),
            ];
        });

        return response()->json([
            'status'  => true,
            'message' => 'Ideal employees retrieved successfully',
            'data'    => [
                'month' => [
                    'month_number'   => $month,
                    'month_name'     => $monthDate->format('F'),
                    'year'           => $year,
                    'ideal_employee' => $this->formatEmployee($selectedMonth?->employee ?? null),
                ],
                'weeks' => $weeks,
            ],
        ]);
    }

    /**
     * POST /api/ideal
     * Assign (or clear) the ideal employee for the month or a specific week.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'period'      => 'required|in:month,week',
            'month'       => 'required|integer|min:1|max:12',
            'year'        => 'required|integer|min:2000|max:2100',
            'week'        => 'required_if:period,week|nullable|integer|min:1|max:6',
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        $period     = $validated['period'];
        $month      = (int) $validated['month'];
        $year       = (int) $validated['year'];
        $week       = $period === 'week' ? (int) $validated['week'] : null;

        $key = [
            'period' => $period,
            'month'  => $month,
            'year'   => $year,
            'week'   => $week,
        ];

        if (!$employeeId) {
            IdealEmployee::where($key)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'تم إزالة الموظف المثالي بنجاح',
                'data'    => $key + ['employee_id' => null],
            ]);
        }

        $idealEmployee = IdealEmployee::updateOrCreate($key, [
            'employee_id'   => $employeeId,
            'created_by_id' => auth()->id(),
        ]);

        $employee = Employee::find($employeeId);

        return response()->json([
            'status'  => true,
            'message' => 'تم اختيار الموظف المثالي بنجاح',
            'data'    => [
                'period'         => $period,
                'month'          => $month,
                'year'           => $year,
                'week'           => $week,
                'ideal_employee' => $this->formatEmployee($employee),
            ],
        ]);
    }

    /**
     * DELETE /api/ideal
     * Remove the selected ideal employee for the month or a specific week.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:month,week',
            'month'  => 'required|integer|min:1|max:12',
            'year'   => 'required|integer|min:2000|max:2100',
            'week'   => 'nullable|integer',
        ]);

        $key = [
            'period' => $validated['period'],
            'month'  => (int) $validated['month'],
            'year'   => (int) $validated['year'],
            'week'   => $validated['period'] === 'week' ? (int) ($validated['week'] ?? 0) : null,
        ];

        $deleted = IdealEmployee::where($key)->delete();

        return response()->json([
            'status'  => true,
            'message' => $deleted ? 'تم إزالة الموظف المثالي بنجاح' : 'لا يوجد موظف مثالي محدد',
            'data'    => [$key, 'deleted' => $deleted > 0],
        ]);
    }

    /**
     * Build the month's weeks (7-day chunks starting from the first day of the month).
     */
    private function buildWeeks(int $year, int $month): array
    {
        $first       = Carbon::create($year, $month, 1);
        $daysInMonth = $first->daysInMonth;
        $weeks       = [];
        $weekNumber  = 1;

        for ($day = 1; $day <= $daysInMonth; $day += 7) {
            $start = Carbon::create($year, $month, $day);
            $end   = $start->copy()->addDays(6);

            if ($end->month !== $month) {
                $end = Carbon::create($year, $month, $daysInMonth);
            }

            $weeks[] = [
                'week'       => $weekNumber,
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
            ];

            $weekNumber++;
        }

        return $weeks;
    }

    /**
     * Format the employee payload used by the mobile app.
     */
    private function formatEmployee(?Employee $employee): ?array
    {
        if (!$employee) {
            return null;
        }

        return [
            'id'         => $employee->id,
            'name'       => $employee->name,
            'image'      => $employee->image ?? null,
            'department' => $employee->department,
        ];
    }
}