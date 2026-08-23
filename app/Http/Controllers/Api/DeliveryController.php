<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Collection;
use App\Models\CollectionDetail;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\Route as RouteModel;
use App\Models\VehicleTracking;
use App\Services\CollectionCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeliveryController
{
    public function index(Request $request): JsonResponse
    {
        $query = Delivery::with(['request.customer', 'driver', 'salesRep', 'route', 'routeStop.customer']);

        if ($request->filled('status'))    $query->where('status', $this->normalizeStatus($request->status));
        if ($request->filled('driver_id')) $query->where('driver_id', $request->driver_id);
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $deliveries = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));
        $summary = [
            'total' => (clone $query)->count(),
            'delivered' => (clone $query)->where('status', 'completed')->count(),
            'not_delivered' => (clone $query)->where('status', 'failed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
        ];

        return response()->json(['success' => true, 'data' => $deliveries, 'summary' => $summary]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_id'     => 'nullable|exists:requests,id',
            'driver_id'      => 'nullable|exists:employees,id',
            'employee_id'    => 'nullable|exists:employees,id',
            'sales_rep_id'   => 'nullable|exists:employees,id',
            'route_id'       => 'nullable|exists:routes,id',
            'route_stop_id'  => 'nullable|exists:route_stops,id',
            'vehicle_number' => 'nullable|string|max:50',
            'expected_collection_amount' => 'nullable|numeric|min:0',
            'collected_amount' => 'nullable|numeric|min:0',
            'packages_count' => 'nullable|integer|min:0',
            'collection_notify_employee_id' => 'nullable|exists:employees,id',
            'notify_employee_id' => 'nullable|exists:employees,id',
            'status' => 'nullable|in:delivered,not_delivered,completed,failed,pending',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['employee_id']) && empty($validated['driver_id'])) {
            $validated['driver_id'] = $validated['employee_id'];
        }
        unset($validated['employee_id']);

        $req = !empty($validated['request_id']) ? RequestModel::findOrFail($validated['request_id']) : null;

        if ($req && ! in_array($req->status, ['approved', 'ready_for_delivery', 'in_delivery', 'delivered', 'collected'])) {
            return response()->json([
                'success' => false,
                'message' => 'الطلب غير جاهز للتسليم. يجب أن يكون معتمداً أولاً.',
            ], 422);
        }

        $validated['delivery_number'] = 'DEL-' . now()->format('YmdHis');
        $validated['status'] = $this->normalizeStatus($validated['status'] ?? 'pending');
        if (in_array($validated['status'], ['completed', 'failed', 'partially_delivered'], true)) {
            $validated['end_time'] = now();
        }
        $validated['expected_collection_amount'] = $validated['expected_collection_amount'] ?? $validated['collected_amount'] ?? null;
        $validated['collection_notify_employee_id'] = $validated['collection_notify_employee_id'] ?? $validated['notify_employee_id'] ?? null;
        $validated['delivery_notes'] = $validated['notes'] ?? null;
        $validated['delivery_items'] = !empty($validated['scheduled_date']) ? ['scheduled_date' => $validated['scheduled_date']] : null;
        unset($validated['collected_amount'], $validated['notify_employee_id'], $validated['scheduled_date'], $validated['notes']);

        $delivery = Delivery::create($validated);

        if ($req) {
            $req->update(['status' => $validated['status'] === 'completed' ? 'delivered' : 'in_delivery']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء عملية التسليم بنجاح',
            'data'    => $delivery->load(['request.customer', 'driver', 'salesRep', 'route']),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::with('request')->findOrFail($id);
        $validated = $request->validate([
            'request_id'     => 'nullable|exists:requests,id',
            'driver_id'      => 'nullable|exists:employees,id',
            'employee_id'    => 'nullable|exists:employees,id',
            'sales_rep_id'   => 'nullable|exists:employees,id',
            'route_id'       => 'nullable|exists:routes,id',
            'route_stop_id'  => 'nullable|exists:route_stops,id',
            'vehicle_number' => 'nullable|string|max:50',
            'expected_collection_amount' => 'nullable|numeric|min:0',
            'collected_amount' => 'nullable|numeric|min:0',
            'packages_count' => 'nullable|integer|min:0',
            'collection_notify_employee_id' => 'nullable|exists:employees,id',
            'notify_employee_id' => 'nullable|exists:employees,id',
            'status' => 'nullable|in:delivered,not_delivered,completed,failed,pending',
            'scheduled_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['employee_id']) && empty($validated['driver_id'])) {
            $validated['driver_id'] = $validated['employee_id'];
        }

        $payload = [
            'request_id' => $validated['request_id'] ?? null,
            'driver_id' => $validated['driver_id'] ?? null,
            'sales_rep_id' => $validated['sales_rep_id'] ?? null,
            'route_id' => $validated['route_id'] ?? null,
            'route_stop_id' => $validated['route_stop_id'] ?? null,
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'expected_collection_amount' => $validated['expected_collection_amount'] ?? $validated['collected_amount'] ?? null,
            'packages_count' => $validated['packages_count'] ?? $delivery->packages_count,
            'collection_notify_employee_id' => $validated['collection_notify_employee_id'] ?? $validated['notify_employee_id'] ?? null,
            'status' => $this->normalizeStatus($validated['status'] ?? $delivery->status),
            'delivery_notes' => $validated['notes'] ?? $delivery->delivery_notes,
            'delivery_items' => !empty($validated['scheduled_date']) ? ['scheduled_date' => $validated['scheduled_date']] : $delivery->delivery_items,
        ];

        if (in_array($payload['status'], ['completed', 'failed', 'partially_delivered'], true)) {
            $payload['end_time'] = $delivery->end_time ?? now();
        }

        $delivery->update($payload);

        if ($delivery->request) {
            $delivery->request->update([
                'status' => $payload['status'] === 'completed' ? 'delivered' : ($payload['status'] === 'failed' ? 'ready_for_delivery' : 'in_delivery'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث التسليمة بنجاح',
            'data' => $delivery->fresh(['request.customer', 'driver', 'salesRep', 'route']),
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Delivery::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التسليمة بنجاح',
        ]);
    }

    public function show($id): JsonResponse
    {
        $delivery = Delivery::with([
            'request.customer',
            'request.items.item',
            'driver',
            'salesRep',
            'route',
            'routeStop.customer',
            'checkpoints',
            'collections',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $delivery]);
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $delivery  = Delivery::with('request')->findOrFail($id);
        $validated = $request->validate([
            'status'           => 'required|in:pending,in_transit,completed,failed,partially_delivered',
            'delivery_notes'   => 'nullable|string',
            'start_latitude'   => 'nullable|numeric',
            'start_longitude'  => 'nullable|numeric',
            'end_latitude'     => 'nullable|numeric',
            'end_longitude'    => 'nullable|numeric',
        ]);

        $data = $validated;

        if ($validated['status'] === 'in_transit') {
            $data['start_time'] = now();
        }

        if (in_array($validated['status'], ['completed', 'failed', 'partially_delivered'])) {
            $data['end_time'] = now();
        }

        $delivery->update($data);

        // Sync request status
        $requestStatus = match ($validated['status']) {
            'completed'            => 'delivered',
            'partially_delivered'  => 'delivered',
            'in_transit'           => 'in_delivery',
            default                => $delivery->request->status,
        };

        if ($delivery->request) {
            $delivery->request->update(['status' => $requestStatus]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة التسليم بنجاح',
            'data'    => $delivery,
        ]);
    }

    public function completeWithCollection(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::with(['request.customer', 'routeStop', 'collections'])->findOrFail($id);
        $validated = $request->validate([
            'delivery_status' => 'required|in:delivered,not_delivered,completed,failed',
            'collected_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,bank_transfer,check,instapay,fawry',
            'collected_date' => 'nullable|date',
            'notify_employee_id' => 'nullable|exists:employees,id',
            'notes' => 'nullable|string',
            'check_number' => 'nullable|string|required_if:payment_method,check',
            'check_due_date' => 'nullable|date|required_if:payment_method,check',
        ]);

        return DB::transaction(function () use ($delivery, $validated) {
            $isDelivered = in_array($validated['delivery_status'], ['delivered', 'completed'], true);
            $deliveryStatus = $isDelivered ? 'completed' : 'failed';
            $collection = null;

            $delivery->update([
                'status' => $deliveryStatus,
                'end_time' => now(),
                'delivery_notes' => $validated['notes'] ?? $delivery->delivery_notes,
            ]);

            if ($delivery->routeStop) {
                $delivery->routeStop->update(['delivery_status' => $isDelivered ? 'delivered' : 'not_delivered']);
            }

            if ($delivery->request) {
                $delivery->request->update(['status' => $isDelivered ? 'delivered' : 'ready_for_delivery']);
            }

            $amount = $validated['collected_amount'] ?? $delivery->expected_collection_amount;
            if ($isDelivered && $amount !== null && (float) $amount > 0) {
                $collection = Collection::create([
                    'collection_number' => 'COL-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                    'delivery_id' => $delivery->id,
                    'driver_id' => $delivery->driver_id,
                    'total_amount' => $amount,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'collection_status' => 'pending',
                    'collected_date' => $validated['collected_date'] ?? today()->toDateString(),
                    'notes' => $validated['notes'] ?? 'تحصيل تلقائي من التسليم',
                    'check_number' => $validated['check_number'] ?? null,
                    'check_due_date' => $validated['check_due_date'] ?? null,
                ]);

                if ($delivery->request_id) {
                    CollectionDetail::create([
                        'collection_id' => $collection->id,
                        'request_id' => $delivery->request_id,
                        'amount' => $amount,
                        'notes' => 'تم إنشاؤه تلقائياً عند تسليم الطلب',
                    ]);
                }

                // Request stays delivered until the direct manager approves the collection
                if ($delivery->request) {
                    $delivery->request->update(['status' => 'delivered']);
                }

                app(CollectionCommissionService::class)
                    ->createFromCollection($collection->load('driver'));
            }

            $notifyEmployeeId = $validated['notify_employee_id'] ?? $delivery->collection_notify_employee_id;
            if ($notifyEmployeeId) {
                $this->notifyEmployee(
                    $notifyEmployeeId,
                    $isDelivered ? 'تم تسليم وتحصيل طلب' : 'تعذر تسليم طلب',
                    $isDelivered
                        ? 'تم إنشاء تحصيل تلقائي من التسليم رقم ' . $delivery->delivery_number
                        : 'تم تسجيل الطلب كغير مسلم للتسليم رقم ' . $delivery->delivery_number,
                    $collection ?: $delivery
                );
            }

            return response()->json([
                'success' => true,
                'message' => $isDelivered ? 'تم تسجيل التسليم والتحصيل التلقائي' : 'تم تسجيل عدم التسليم',
                'data' => [
                    'delivery' => $delivery->fresh(['request.customer', 'driver', 'salesRep', 'routeStop.customer']),
                    'collection' => $collection?->load(['delivery.request.customer', 'details']),
                ],
            ]);
        });
    }

    public function uploadProof(Request $request, $id): JsonResponse
    {
        $delivery  = Delivery::findOrFail($id);
        $validated = $request->validate([
            'delivery_photo' => 'nullable|image|max:5120',
            'signature'      => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        if ($request->hasFile('delivery_photo')) {
            $path = $request->file('delivery_photo')->store('deliveries/photos', 'public');
            $delivery->update(['delivery_photo' => $path]);
        }

        if ($request->filled('signature')) {
            $delivery->update(['signature_proof' => $validated['signature']]);
        }

        if ($request->filled('notes')) {
            $delivery->update(['delivery_notes' => $validated['notes']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفع إثبات التسليم بنجاح',
            'data'    => $delivery,
        ]);
    }

    public function addTracking(Request $request, $id): JsonResponse
    {
        Delivery::findOrFail($id);

        $validated = $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'speed'       => 'nullable|numeric',
            'direction'   => 'nullable|string',
            'captured_at' => 'nullable|date',
        ]);

        $tracking = VehicleTracking::create(array_merge(
            $validated,
            [
                'delivery_id' => $id,
                'captured_at' => $validated['captured_at'] ?? now(),
            ]
        ));

        return response()->json(['success' => true, 'data' => $tracking]);
    }

    public function tracking($id): JsonResponse
    {
        $points = VehicleTracking::where('delivery_id', $id)
            ->orderBy('captured_at')
            ->get(['latitude', 'longitude', 'speed', 'direction', 'captured_at']);

        return response()->json(['success' => true, 'data' => $points]);
    }

    public function driverDeliveries(Request $request): JsonResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب',
                'data' => [],
            ], 404);
        }

        $deliveries = Delivery::with(['request.customer', 'routeStop.customer', 'route'])
            ->where('driver_id', $employee->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date))
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $deliveries,
            'employee' => $employee->only(['id', 'name', 'employee_code']),
        ]);
    }

    private function notifyEmployee(int $employeeId, string $title, string $message, object $related): void
    {
        $employee = Employee::find($employeeId);
        if (!$employee || !$employee->user_id) {
            return;
        }

        Notification::create([
            'sender_id' => auth()->id(),
            'user_id' => $employee->user_id,
            'title' => $title,
            'message' => $message,
            'notification_type' => 'collection',
            'related_model' => get_class($related),
            'related_id' => $related->id,
        ]);
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'delivered' => 'completed',
            'not_delivered' => 'failed',
            default => $status,
        };
    }
}
