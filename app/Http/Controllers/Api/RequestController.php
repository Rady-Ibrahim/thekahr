<?php

namespace App\Http\Controllers\Api;

use App\Models\Request as RequestModel;
use App\Models\RequestItem;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Approval;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RequestController
{
    public function index(Request $request)
    {
        $query = RequestModel::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('request_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%");
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('payment_type') && Schema::hasColumn('requests', 'payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        $requests = $query->with(['customer', 'items', 'createdBy.manager', 'assignedEmployee', 'preparedBy', 'reviewerEmployee'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employeeId = $this->currentEmployeeId();

        $query = RequestModel::where(function ($q) use ($employeeId) {
            $q->where('created_by_id', $employeeId)
                ->orWhere('prepared_by_id', $employeeId)
                ->orWhere('assigned_employee_id', $employeeId)
                ->orWhere('reviewer_employee_id', $employeeId);
        });

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->with([
                'customer',
                'items.item',
                'createdBy.manager',
                'assignedEmployee',
                'preparedBy',
                'reviewerEmployee',
                'reviewedBy',
                'approvedBy',
            ])
            ->orderByDesc('updated_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse' => 'sometimes|string',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'employee_id' => 'nullable|exists:employees,id',
            'reviewer_employee_id' => 'nullable|exists:employees,id',
            'estimated_delivery_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'sometimes|string',
            'items' => 'sometimes|array',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $requestNumber = 'REQ-' . now()->format('YmdHis');
        $validated['request_number'] = $requestNumber;
        $validated['status'] = in_array($validated['status'] ?? 'draft', ['pending', 'under_review'], true) ? 'under_review' : 'draft';
        $validated['created_by_id'] = $this->currentEmployeeId();
        $validated['assigned_employee_id'] = $validated['assigned_employee_id'] ?? $validated['employee_id'] ?? null;
        $validated['estimated_delivery_date'] = $validated['estimated_delivery_date'] ?? $validated['delivery_date'] ?? null;
        $validated['customer_name'] = $customer->name;
        $validated['company_name'] = $customer->company_name;
        unset($validated['employee_id'], $validated['delivery_date'], $validated['items']);

        $requestModel = DB::transaction(function () use ($validated, $request) {
            $requestModel = RequestModel::create($validated);

            if ($request->filled('items')) {
                $this->syncItems($requestModel, $request->input('items', []));
            }

            if ($requestModel->status === 'under_review') {
                $requestModel->update([
                    'prepared_at' => now(),
                    'prepared_by_id' => $this->currentEmployeeId(),
                ]);
                if ($requestModel->reviewer_employee_id) {
                    $this->createReviewerApproval($requestModel, $requestModel->reviewer_employee_id, 'تم إرسال الطلب للمراجعة');
                }
            }

            if ($requestModel->assigned_employee_id) {
                $this->notifyEmployee(
                    $requestModel->assigned_employee_id,
                    'تم تعيين طلب لك',
                    'تم تعيين الطلب ' . $requestModel->request_number . ' لك',
                    $requestModel,
                    'request_assignment'
                );
            }

            return $requestModel->fresh(['customer', 'items.item', 'createdBy.manager', 'assignedEmployee', 'reviewerEmployee']);
        });

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الطلب بنجاح',
            'data' => $requestModel,
        ], 201);
    }

    public function storePrepaid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],
            'items_count' => 'nullable|integer|min:1',
            'orders_count' => 'nullable|integer|min:1',
            'prepared_by_id' => 'nullable|exists:employees,id',
            'reviewer_employee_id' => 'nullable|exists:employees,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after_or_equal:started_at',
            'notes' => 'nullable|string',
        ]);

        $currentEmployee = $this->currentEmployee();

        $customerId = $validated['customer_id'] ?? null;
        $customer = $customerId ? Customer::find($customerId) : null;

        $payload = [
            'request_number' => \App\Models\Request::nextPrepaidNumber(),
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name,
            'company_name' => $customer?->company_name,
            'items_count' => $validated['items_count'] ?? 0,
            'total_quantity' => $validated['orders_count'] ?? 0,
            'status' => 'under_review',
            'created_by_id' => $currentEmployee?->id,
            'prepared_by_id' => $validated['prepared_by_id'] ?? $currentEmployee?->id,
            'prepared_at' => now(),
            'started_at' => $validated['started_at'] ?? now(),
            'ended_at' => $validated['ended_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        if (Schema::hasColumn('requests', 'orders_count')) {
            $payload['orders_count'] = $validated['orders_count'] ?? 0;
        }
        if (Schema::hasColumn('requests', 'payment_type')) {
            $payload['payment_type'] = 'prepaid';
        }
        if (Schema::hasColumn('requests', 'reviewer_employee_id')) {
            $payload['reviewer_employee_id'] = $validated['reviewer_employee_id'] ?? null;
        } else {
            $payload['assigned_employee_id'] = $validated['reviewer_employee_id'] ?? null;
        }

        $requestModel = RequestModel::create($payload);

        if ($payload['prepared_by_id'] ?? null) {
            $this->notifyEmployee(
                $payload['prepared_by_id'],
                'تم إنشاء طلب تحضير طلبيه',
                'تم إنشاء الطلب ' . $requestModel->request_number . ' وإرساله للمراجعة',
                $requestModel,
                'prepaid_created'
            );
        }

        if ($payload['reviewer_employee_id'] ?? null) {
            $this->createReviewerApproval($requestModel, $payload['reviewer_employee_id'], 'تم ترحيل طلب تحضير الطلبيه للمراجعة');
        }

        return response()->json([
            'success' => true,
            'message' => 'تم ترحيل طلب تحضير الطلبيه للمراجعة',
            'data' => $requestModel->load(['customer', 'preparedBy', 'reviewerEmployee', 'assignedEmployee']),
        ], 201);
    }

    public function show($id)
    {
        $request = RequestModel::with([
            'customer',
            'items',
            'items.item',
            'createdBy.manager',
            'assignedEmployee',
            'preparedBy',
            'reviewerEmployee',
            'approvals',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $request,
        ]);
    }

    public function update(Request $request, $id)
    {
        $requestModel = RequestModel::findOrFail($id);

        $validated = $request->validate([
            'customer_id' => 'sometimes|exists:customers,id',
            'warehouse' => 'nullable|string',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'employee_id' => 'nullable|exists:employees,id',
            'estimated_delivery_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'reviewer_employee_id' => 'nullable|exists:employees,id',
            'started_at' => 'nullable|date',
            'ended_at' => 'nullable|date|after_or_equal:started_at',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

        return DB::transaction(function () use ($requestModel, $validated) {
            $payload = collect($validated)->only(['customer_id', 'warehouse', 'notes', 'reviewer_employee_id'])->toArray();
            $payload['assigned_employee_id'] = $validated['assigned_employee_id'] ?? $validated['employee_id'] ?? $requestModel->assigned_employee_id;
            $payload['estimated_delivery_date'] = $validated['estimated_delivery_date'] ?? $validated['delivery_date'] ?? $requestModel->estimated_delivery_date;
            $payload['started_at'] = $validated['started_at'] ?? $requestModel->started_at;
            $payload['ended_at'] = $validated['ended_at'] ?? $requestModel->ended_at;
            if (($validated['status'] ?? null) === 'under_review') {
                $payload['status'] = 'under_review';
                $payload['prepared_at'] = $requestModel->prepared_at ?? now();
                $payload['prepared_by_id'] = $requestModel->prepared_by_id ?? $this->currentEmployeeId();
            }

            if (isset($validated['customer_id'])) {
                $customer = Customer::find($validated['customer_id']);
                $payload['customer_name'] = $customer?->name;
                $payload['company_name'] = $customer?->company_name;
            }

            $requestModel->update($payload);

            if (array_key_exists('items', $validated)) {
                $requestModel->items()->delete();
                $this->syncItems($requestModel, $validated['items'] ?? []);
            }

            if (($validated['status'] ?? null) === 'under_review' && !empty($payload['reviewer_employee_id'])) {
                $this->createReviewerApproval($requestModel, (int) $payload['reviewer_employee_id'], 'تم إرسال الطلب للمراجعة');
            }

            if (array_key_exists('assigned_employee_id', $payload) && $payload['assigned_employee_id']) {
                $this->notifyEmployee(
                    (int) $payload['assigned_employee_id'],
                    'تم تعيين طلب لك',
                    'تم تعيين الطلب ' . $requestModel->request_number . ' لك',
                    $requestModel,
                    'request_assignment'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الطلب بنجاح',
                'data' => $requestModel->fresh(['customer', 'items.item', 'createdBy.manager', 'assignedEmployee', 'reviewerEmployee']),
            ]);
        });
    }

    public function addItems(Request $request, $id)
    {
        $requestModel = RequestModel::findOrFail($id);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($validated['items'] as $item) {
            $dbItem = Item::findOrFail($item['item_id']);
            $totalPrice = $item['quantity'] * $item['unit_price'];

            RequestItem::create([
                'request_id' => $id,
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'unit' => $dbItem->unit,
                'unit_price' => $item['unit_price'],
                'total_price' => $totalPrice,
            ]);

            $totalAmount += $totalPrice;
            $totalQuantity += $item['quantity'];
        }

        $requestModel->update([
            'total_amount' => $requestModel->total_amount + $totalAmount,
            'total_quantity' => $requestModel->total_quantity + $totalQuantity,
            'items_count' => $requestModel->items()->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العناصر بنجاح',
            'data' => $requestModel,
        ]);
    }

    public function submitForReview($id)
    {
        $request = RequestModel::findOrFail($id);

        if ($request->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن مراجعة الطلب في هذه الحالة',
            ], 422);
        }

        $request->update([
            'status' => 'under_review',
            'prepared_at' => now(),
            'prepared_by_id' => auth()->user()->employee_id ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الطلب للمراجعة',
            'data' => $request,
        ]);
    }

    public function submitReviewerReview(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::findOrFail($id);
        $validated = $request->validate([
            'reviewer_employee_id' => 'required|exists:employees,id',
            'notes' => 'nullable|string',
        ]);

        $requestModel->update([
            'status' => 'under_review',
            'reviewer_employee_id' => $validated['reviewer_employee_id'],
            'prepared_at' => $requestModel->prepared_at ?? now(),
            'prepared_by_id' => $requestModel->prepared_by_id ?? $this->currentEmployeeId(),
            'notes' => $validated['notes'] ?? $requestModel->notes,
        ]);

        $approval = $this->createReviewerApproval($requestModel, $validated['reviewer_employee_id'], $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الطلب لموظف المراجعة',
            'data' => [
                'request' => $requestModel->fresh(['customer', 'createdBy', 'preparedBy', 'reviewerEmployee']),
                'approval' => $approval,
            ],
        ]);
    }

    public function transferToEmployee(Request $request, $id): JsonResponse
    {
        return $this->submitReviewerReview($request, $id);
    }

    public function receivedPending(Request $request): JsonResponse
    {
        return $this->reviewerPending($request);
    }

    public function reviewerPending(Request $request): JsonResponse
    {
        $reviewerId = $request->get('reviewer_id', $this->currentEmployeeId());

        $requests = RequestModel::where('status', 'under_review')
            ->where('reviewer_employee_id', $reviewerId)
            ->whereHas('approvals', function ($q) use ($reviewerId) {
                $q->where('approval_type', 'reviewer_request_review')
                    ->where('status', 'pending')
                    ->where('approved_by_id', $reviewerId);
            })
            ->with(['customer', 'items.item', 'createdBy.manager', 'preparedBy', 'reviewerEmployee'])
            ->orderByDesc('updated_at')
            ->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function reviewerApprove(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::with(['createdBy.manager', 'preparedBy'])->findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string']);
        $reviewerId = $this->currentEmployeeId();

        Approval::where('approvable_type', RequestModel::class)
            ->where('approvable_id', $requestModel->id)
            ->where('approval_type', 'reviewer_request_review')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by_id' => $reviewerId,
                'notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

        $requestModel->update([
            'reviewed_by_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        $managerId = $requestModel->createdBy?->reporting_manager_id
            ?? $requestModel->preparedBy?->reporting_manager_id;

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تمت مراجعة الطلب',
                'تمت مراجعة الطلب ' . $requestModel->request_number . ' بواسطة المراجع',
                $requestModel,
                'prepaid_reviewed'
            );
        }

        if (!$managerId) {
            $requestModel->update([
                'status' => 'approved',
                'approved_by_id' => $reviewerId,
                'approved_at' => now(),
                'notes' => $validated['notes'] ?? $requestModel->notes,
            ]);
            if ($requestModel->prepared_by_id) {
                $this->notifyEmployee(
                    $requestModel->prepared_by_id,
                    'تم اعتماد الطلب',
                    'تم اعتماد الطلب ' . $requestModel->request_number,
                    $requestModel,
                    'prepaid_approved'
                );
            }
            return response()->json([
                'success' => true,
                'message' => 'تمت مراجعة الطلب واعتماده (لا يوجد مدير مربوط بالطلب)',
                'data' => $requestModel->fresh(['customer', 'createdBy', 'reviewerEmployee']),
            ]);
        }

        $managerApproval = $this->createManagerApproval($requestModel, $managerId, $validated['notes'] ?? 'تمت مراجعة الطلب وإرساله للمدير');

        return response()->json([
            'success' => true,
            'message' => 'تمت مراجعة الطلب وإرساله إلى المدير',
            'data' => [
                'request' => $requestModel->fresh(['customer', 'createdBy.manager', 'reviewerEmployee']),
                'approval' => $managerApproval,
            ],
        ]);
    }

    public function reviewerApproveFinal(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::with(['createdBy.manager', 'preparedBy'])->findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string']);
        $reviewerId = $this->currentEmployeeId();

        Approval::where('approvable_type', RequestModel::class)
            ->where('approvable_id', $requestModel->id)
            ->where('approval_type', 'reviewer_request_review')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by_id' => $reviewerId,
                'notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

        $requestModel->update([
            'status' => 'approved',
            'reviewed_by_id' => $reviewerId,
            'reviewed_at' => now(),
            'approved_by_id' => $reviewerId,
            'approved_at' => now(),
            'notes' => $validated['notes'] ?? $requestModel->notes,
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم اعتماد الطلب',
                'تم اعتماد الطلب ' . $requestModel->request_number . ' نهائياً بواسطة المراجع (بدون مراجعة المدير)',
                $requestModel,
                'prepaid_approved'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تمت مراجعة الطلب واعتماده نهائياً بدون مراجعة المدير',
            'data' => $requestModel->fresh(['customer', 'createdBy', 'preparedBy', 'reviewerEmployee', 'reviewedBy', 'approvedBy']),
        ]);
    }

    public function reviewerReject(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::findOrFail($id);
        $validated = $request->validate(['reason' => 'required|string']);
        $reviewerId = $this->currentEmployeeId();

        Approval::where('approvable_type', RequestModel::class)
            ->where('approvable_id', $requestModel->id)
            ->where('approval_type', 'reviewer_request_review')
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'approved_by_id' => $reviewerId,
                'rejection_reason' => $validated['reason'],
                'approved_at' => now(),
            ]);

        $requestModel->update([
            'status' => 'rejected',
            'reviewed_by_id' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم رفض الطلب',
                'تم رفض الطلب ' . $requestModel->request_number . ' بواسطة المراجع - السبب: ' . $validated['reason'],
                $requestModel,
                'prepaid_rejected'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب من موظف المراجعة',
            'data' => $requestModel->load(['customer', 'reviewerEmployee']),
        ]);
    }

    public function prepare(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::findOrFail($id);
        $validated = $request->validate([
            'prepared_by_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
        ]);

        $preparedById = $validated['prepared_by_id'] ?? $this->currentEmployeeId();
        $requestModel->update([
            'status' => 'prepared',
            'prepared_by_id' => $preparedById,
            'prepared_at' => now(),
            'notes' => $validated['notes'] ?? $requestModel->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحضير الطلب',
            'data' => $requestModel->load(['customer', 'preparedBy']),
        ]);
    }

    public function submitManagerReview(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::with(['createdBy', 'preparedBy'])->findOrFail($id);
        $validated = $request->validate([
            'manager_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
        ]);

        $employee = $requestModel->createdBy ?: $requestModel->preparedBy ?: $this->currentEmployee();
        $managerId = $validated['manager_id'] ?? $employee?->reporting_manager_id;

        if (!$managerId) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مدير مربوط بالموظف، اختر manager_id أو حدث مدير الموظف من الداش بورد',
            ], 422);
        }

        $approval = $this->createManagerApproval($requestModel, $managerId, $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الطلب لمراجعة المدير',
            'data' => [
                'request' => $requestModel->fresh(['customer', 'preparedBy', 'reviewerEmployee']),
                'approval' => $approval,
            ],
        ]);
    }

    public function managerPending(Request $request): JsonResponse
    {
        $managerId = $request->get('manager_id', $this->currentEmployeeId());

        $requests = RequestModel::where('status', 'under_review')
            ->whereHas('approvals', function ($approvalQuery) use ($managerId) {
                $approvalQuery->where('approval_type', 'manager_request_review')
                    ->where('status', 'pending')
                    ->where('approved_by_id', $managerId);
            })
            ->with(['customer', 'items.item', 'createdBy', 'preparedBy', 'reviewerEmployee'])
            ->orderByDesc('updated_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function managerApprove(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::findOrFail($id);
        $validated = $request->validate(['notes' => 'nullable|string']);
        $managerId = $this->currentEmployeeId();

        Approval::where('approvable_type', RequestModel::class)
            ->where('approvable_id', $requestModel->id)
            ->where('approval_type', 'manager_request_review')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by_id' => $managerId,
                'notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

        $requestModel->update([
            'status' => 'approved',
            'reviewed_by_id' => $managerId,
            'reviewed_at' => now(),
            'approved_by_id' => $managerId,
            'approved_at' => now(),
            'notes' => $validated['notes'] ?? $requestModel->notes,
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم اعتماد الطلب',
                'تم اعتماد الطلب ' . $requestModel->request_number . ' بواسطة المدير',
                $requestModel,
                'prepaid_approved'
            );
        }

        if ($requestModel->reviewer_employee_id) {
            $this->notifyEmployee(
                $requestModel->reviewer_employee_id,
                'تم اعتماد الطلب من المدير',
                'تم اعتماد الطلب ' . $requestModel->request_number . ' الذي راجعته بواسطة المدير',
                $requestModel,
                'prepaid_approved'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تمت موافقة المدير على الطلب',
            'data' => $requestModel->load(['customer', 'preparedBy', 'reviewedBy']),
        ]);
    }

    public function managerReject(Request $request, $id): JsonResponse
    {
        $requestModel = RequestModel::findOrFail($id);
        $validated = $request->validate(['reason' => 'required|string']);
        $managerId = $this->currentEmployeeId();

        Approval::where('approvable_type', RequestModel::class)
            ->where('approvable_id', $requestModel->id)
            ->where('approval_type', 'manager_request_review')
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'approved_by_id' => $managerId,
                'rejection_reason' => $validated['reason'],
                'approved_at' => now(),
            ]);

        $requestModel->update([
            'status' => 'rejected',
            'reviewed_by_id' => $managerId,
            'reviewed_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم رفض الطلب',
                'تم رفض الطلب ' . $requestModel->request_number . ' بواسطة المدير - السبب: ' . $validated['reason'],
                $requestModel,
                'prepaid_rejected'
            );
        }

        if ($requestModel->reviewer_employee_id) {
            $this->notifyEmployee(
                $requestModel->reviewer_employee_id,
                'تم رفض الطلب من المدير',
                'تم رفض الطلب ' . $requestModel->request_number . ' الذي راجعته بواسطة المدير - السبب: ' . $validated['reason'],
                $requestModel,
                'prepaid_rejected'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب من المدير',
            'data' => $requestModel->load(['customer', 'preparedBy', 'reviewedBy']),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $requestModel = RequestModel::findOrFail($id);

        $validated = $request->validate([
            'notes' => 'sometimes|string',
        ]);

        $requestModel->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_id' => auth()->user()->employee_id ?? 1,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم اعتماد الطلب',
                'تم اعتماد الطلب ' . $requestModel->request_number,
                $requestModel,
                'prepaid_approved'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد الطلب بنجاح',
            'data' => $requestModel,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        RequestModel::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الطلب بنجاح',
        ]);
    }

    private function syncItems(RequestModel $requestModel, array $items): void
    {
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $dbItem = Item::findOrFail($item['item_id']);
            $totalPrice = $item['quantity'] * $item['unit_price'];

            RequestItem::create([
                'request_id' => $requestModel->id,
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'unit' => $dbItem->unit,
                'unit_price' => $item['unit_price'],
                'total_price' => $totalPrice,
            ]);

            $totalAmount += $totalPrice;
            $totalQuantity += $item['quantity'];
        }

        $requestModel->update([
            'total_amount' => $totalAmount,
            'total_quantity' => $totalQuantity,
            'items_count' => count($items),
        ]);
    }

    private function currentEmployee(): ?Employee
    {
        if (auth()->id()) {
            $employee = Employee::where('user_id', auth()->id())->first();
            if ($employee) return $employee;
        }

        return Employee::find(1);
    }

    private function currentEmployeeId(): int
    {
        return $this->currentEmployee()?->id ?? 1;
    }

    private function notifyEmployee(int $employeeId, string $title, string $message, object $related, string $type = 'manager_review'): void
    {
        $employee = Employee::with('user')->find($employeeId);
        if (!$employee || !$employee->user_id) {
            return;
        }

        Notification::create([
            'user_id' => $employee->user_id,
            'title' => $title,
            'message' => $message,
            'notification_type' => $type,
            'related_model' => get_class($related),
            'related_id' => $related->id,
        ]);

        if ($employee->user->onesignal_player_id) {
            app(OneSignalService::class)->sendNotification(
                [$employee->user->onesignal_player_id],
                $title,
                $message,
                [
                    'related_model' => get_class($related),
                    'related_id' => (string) $related->id,
                    'type' => $type,
                ]
            );
        }
    }

    private function createReviewerApproval(RequestModel $requestModel, int $reviewerId, ?string $notes = null): Approval
    {
        $approval = Approval::updateOrCreate(
            [
                'approvable_type' => RequestModel::class,
                'approvable_id' => $requestModel->id,
                'approval_type' => 'reviewer_request_review',
                'status' => 'pending',
            ],
            [
                'approval_level' => 1,
                'approved_by_id' => $reviewerId,
                'notes' => $notes,
            ]
        );

        $this->notifyEmployee(
            $reviewerId,
            'طلب جديد يحتاج مراجعتك',
            'تم إرسال الطلب ' . $requestModel->request_number . ' لمراجعتك',
            $requestModel
        );

        return $approval;
    }

    private function createManagerApproval(RequestModel $requestModel, int $managerId, ?string $notes = null): Approval
    {
        $requestModel->update(['status' => 'under_review']);

        $approval = Approval::updateOrCreate(
            [
                'approvable_type' => RequestModel::class,
                'approvable_id' => $requestModel->id,
                'approval_type' => 'manager_request_review',
                'status' => 'pending',
            ],
            [
                'approval_level' => 2,
                'approved_by_id' => $managerId,
                'notes' => $notes,
            ]
        );

        $this->notifyEmployee(
            $managerId,
            'طلب جديد يحتاج موافقة المدير',
            'تم إرسال الطلب ' . $requestModel->request_number . ' لموافقتك',
            $requestModel
        );

        return $approval;
    }

    public function reject(Request $request, $id)
    {
        $requestModel = RequestModel::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required_without:reason|string',
            'reason' => 'required_without:rejection_reason|string',
        ]);

        $requestModel->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'] ?? $validated['reason'],
        ]);

        if ($requestModel->prepared_by_id) {
            $this->notifyEmployee(
                $requestModel->prepared_by_id,
                'تم رفض الطلب',
                'تم رفض الطلب ' . $requestModel->request_number . ' - السبب: ' . ($validated['rejection_reason'] ?? $validated['reason']),
                $requestModel,
                'prepaid_rejected'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الطلب',
            'data' => $requestModel,
        ]);
    }
}
