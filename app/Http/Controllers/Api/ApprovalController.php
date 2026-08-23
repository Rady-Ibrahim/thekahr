<?php

namespace App\Http\Controllers\Api;

use App\Models\Approval;
use App\Models\Collection;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\Salary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController
{
    public function pending(Request $request): JsonResponse
    {
        $pendingApprovals = Approval::where('status', 'pending')
            ->where('approvable_type', RequestModel::class)
            ->whereIn('approval_type', ['reviewer_request_review', 'manager_request_review'])
            ->with('approver')
            ->orderByDesc('created_at')
            ->get();

        $requestIds = $pendingApprovals->pluck('approvable_id')->unique();
        $requestsById = RequestModel::whereIn('id', $requestIds)
            ->with(['customer', 'createdBy.manager', 'preparedBy', 'reviewerEmployee', 'items'])
            ->get()
            ->keyBy('id');

        $pendingRequests = $pendingApprovals->map(function ($approval) use ($requestsById) {
            $request = $requestsById->get($approval->approvable_id);
            if ($request) {
                $request->setAttribute('pending_approval_id', $approval->id);
                $request->setAttribute('pending_approval_type', $approval->approval_type);
                $request->setAttribute('pending_approver', $approval->approver);
            }
            return $request;
        })->filter()->values();

        $pendingCollectionsQuery = Collection::where('collection_status', 'pending')
            ->with('delivery.request.customer', 'driver.manager')
            ->orderByDesc('created_at');

        $employee = Employee::where('user_id', auth()->id())->first();
        $user = $request->user();

        // Direct manager sees only subordinates' collections; HR/super_admin see all
        if ($user && !$user->hasAnyRole(['super_admin', 'hr_manager'])) {
            if ($employee) {
                $pendingCollectionsQuery->whereHas('driver', function ($q) use ($employee) {
                    $q->where('reporting_manager_id', $employee->id);
                });
            } else {
                $pendingCollectionsQuery->whereRaw('1 = 0');
            }
        }

        $pendingCollections = $pendingCollectionsQuery->get();

        $pendingSalaries = Salary::where('status', 'pending_approval')
            ->with('employee')
            ->orderByDesc('created_at')
            ->get();

        $pendingCommissions = Commission::where('status', 'pending')
            ->with(['employee', 'collection'])
            ->orderByDesc('created_at')
            ->get();

        $summary = [
            'total_pending'      => $pendingRequests->count() + $pendingCollections->count() + $pendingSalaries->count() + $pendingCommissions->count(),
            'pending_requests'   => $pendingRequests->count(),
            'pending_reviewer_requests' => $pendingRequests->where('pending_approval_type', 'reviewer_request_review')->count(),
            'pending_manager_requests' => $pendingRequests->where('pending_approval_type', 'manager_request_review')->count(),
            'pending_collections'=> $pendingCollections->count(),
            'pending_salaries'   => $pendingSalaries->count(),
            'pending_commissions'=> $pendingCommissions->count(),
            'pending_commissions_amount' => (float) $pendingCommissions->sum('amount'),
        ];

        return response()->json([
            'success'     => true,
            'summary'     => $summary,
            'data'        => [
                'requests'    => $pendingRequests,
                'collections' => $pendingCollections,
                'salaries'    => $pendingSalaries,
                'commissions' => $pendingCommissions,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $query = Approval::with('approver');

        if ($request->filled('type'))        $query->where('approvable_type', $request->type);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('approver_id')) $query->where('approved_by_id', $request->approver_id);

        $approvals = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $approvals]);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $approval  = Approval::findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string']);

        $approval->update([
            'status'         => 'approved',
            'approved_by_id' => auth()->user()->employee_id ?? 1,
            'notes'          => $validated['notes'] ?? null,
            'approved_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت الموافقة بنجاح',
            'data'    => $approval,
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $approval  = Approval::findOrFail($id);
        $validated = $request->validate(['rejection_reason' => 'required|string']);

        $approval->update([
            'status'           => 'rejected',
            'approved_by_id'   => auth()->user()->employee_id ?? 1,
            'rejection_reason' => $validated['rejection_reason'],
            'approved_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الرفض',
            'data'    => $approval,
        ]);
    }
}
