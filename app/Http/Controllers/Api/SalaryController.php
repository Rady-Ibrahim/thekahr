<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\Salary;
use App\Services\SalaryCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryController
{
    public function __construct(private SalaryCalculationService $salaryService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Salary::with('employee');

        if ($request->filled('month'))       $query->where('month', $request->month);
        if ($request->filled('year'))        $query->where('year', $request->year);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        $salaries = $query->orderByDesc('year')->orderByDesc('month')->paginate($request->get('per_page', 15));

        $totals = [
            'total_gross'  => $query->sum('gross_salary'),
            'total_net'    => $query->sum('net_salary'),
            'total_count'  => $query->count(),
        ];

        return response()->json(['success' => true, 'data' => $salaries, 'totals' => $totals]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month'        => 'required|integer|min:1|max:12',
            'year'         => 'required|integer|min:2020',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $results = $this->salaryService->calculateBulk(
            $validated['month'],
            $validated['year'],
            $validated['employee_ids'] ?? null
        );

        $success = array_filter($results, fn($r) => $r['status'] === 'success');
        $failed  = array_filter($results, fn($r) => $r['status'] === 'failed');

        return response()->json([
            'success'        => true,
            'message'        => 'تم احتساب الرواتب: ' . count($success) . ' موظف',
            'data'           => $results,
            'success_count'  => count($success),
            'failed_count'   => count($failed),
        ]);
    }

    public function calculateSingle(Request $request, $employeeId): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year'  => 'required|integer|min:2020',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $salary   = $this->salaryService->calculate($employee, $validated['month'], $validated['year']);

        return response()->json([
            'success' => true,
            'message' => 'تم احتساب راتب ' . $employee->name . ' بنجاح',
            'data'    => $salary->load('components'),
        ]);
    }

    public function show($id): JsonResponse
    {
        $salary = Salary::with(['employee', 'components', 'approver'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $salary]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $salary    = Salary::findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string']);

        if (! in_array($salary->status, ['draft', 'pending_approval'])) {
            return response()->json(['success' => false, 'message' => 'لا يمكن اعتماد هذا الراتب في حالته الحالية'], 422);
        }

        $salary->update([
            'status'         => 'approved',
            'approved_by_id' => auth()->user()->employee_id ?? 1,
            'approval_notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'تم اعتماد الراتب بنجاح', 'data' => $salary]);
    }

    public function pay(Request $request, $id): JsonResponse
    {
        $salary    = Salary::findOrFail($id);
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bank_transfer,check,instapay',
        ]);

        if ($salary->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'يجب اعتماد الراتب أولاً قبل الصرف'], 422);
        }

        $salary->update([
            'status'         => 'paid',
            'payment_method' => $validated['payment_method'],
            'payment_date'   => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'تم صرف الراتب بنجاح', 'data' => $salary]);
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'salary_ids' => 'required|array',
            'salary_ids.*' => 'exists:salaries,id',
        ]);

        $count = Salary::whereIn('id', $validated['salary_ids'])
            ->whereIn('status', ['draft', 'pending_approval'])
            ->update([
                'status'         => 'approved',
                'approved_by_id' => auth()->user()->employee_id ?? 1,
            ]);

        return response()->json(['success' => true, 'message' => "تم اعتماد $count راتب بنجاح"]);
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        $salaries = Salary::where('month', $month)->where('year', $year)->with('employee')->get();

        $summary = [
            'month'             => $month,
            'year'              => $year,
            'total_employees'   => $salaries->count(),
            'total_base'        => $salaries->sum('base_salary'),
            'total_gross'       => $salaries->sum('gross_salary'),
            'total_incentives'  => $salaries->sum('total_incentives'),
            'total_allowances'  => $salaries->sum('total_allowances'),
            'total_commissions' => $salaries->sum('total_commissions'),
            'total_deductions'  => $salaries->sum('total_deductions'),
            'total_advances'    => $salaries->sum('total_advances'),
            'total_violations'  => $salaries->sum('total_violations'),
            'total_net'         => $salaries->sum('net_salary'),
            'by_status'         => $salaries->groupBy('status')->map->count(),
        ];

        return response()->json(['success' => true, 'data' => $summary]);
    }
}
