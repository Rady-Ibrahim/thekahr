<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\CustomerDailyExpectedAmount;
use App\Models\Delivery;
use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\Route as RouteModel;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController
{
    public function index(Request $request): JsonResponse
    {
        $query = RouteModel::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('route_name', 'like', "%$s%")
                    ->orWhere('route_code', 'like', "%$s%");
            });
        }

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('driver_id')) $query->where('driver_id', $request->driver_id);

        $routes = $query->with(['driver', 'salesRep'])
            ->withCount(['deliveries', 'stops'])
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $routes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_name'              => 'nullable|string|max:255',
            'driver_id'               => 'nullable|exists:employees,id',
            'sales_rep_id'            => 'nullable|exists:employees,id',
            'vehicle_number'          => 'nullable|string|max:50',
            'start_point'             => 'nullable|string|max:255',
            'end_point'               => 'nullable|string|max:255',
            'distance_km'             => 'nullable|numeric|min:0',
            'estimated_time_minutes'  => 'nullable|integer|min:0',
            'waypoints'               => 'nullable|array',
            'requests'                => 'nullable|array',
            'requests.*'              => 'exists:requests,id',
        ]);

        $validated['route_code'] = 'RT-' . now()->format('Ymd') . '-' . str_pad(RouteModel::whereDate('created_at', today())->count() + 1, 3, '0', STR_PAD_LEFT);

        $route = RouteModel::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء خط السير بنجاح',
            'data'    => $route,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $route = RouteModel::with([
            'driver',
            'salesRep',
            'stops.customer',
            'deliveries.request.customer',
            'deliveries.driver',
            'odometerVerifiedBy',
        ])->findOrFail($id);

        $this->attachDailyExpectedAmounts($route->stops);

        return response()->json(['success' => true, 'data' => $route]);
    }

    public function storeWithStops(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_name' => 'nullable|string|max:255',
            'driver_id' => 'nullable|exists:employees,id',
            'sales_rep_id' => 'nullable|exists:employees,id',
            'vehicle_number' => 'nullable|string|max:50',
            'start_point' => 'nullable|string|max:255',
            'end_point' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,archived',
            'stops' => 'required|array|min:1',
            'stops.*.customer_id' => 'required|exists:customers,id',
            'stops.*.request_ids' => 'nullable|array',
            'stops.*.request_ids.*' => 'exists:requests,id',
            'stops.*.boxes_count' => 'nullable|integer|min:0',
            'stops.*.cartons_count' => 'nullable|integer|min:0',
            'stops.*.bundles_count' => 'nullable|integer|min:0',
            'stops.*.packages_count' => 'nullable|integer|min:0',
            'stops.*.expected_amount' => 'nullable|numeric|min:0',
            'stops.*.goods_notes' => 'nullable|string',
            'stops.*.delivery_status' => 'nullable|in:pending,delivered,not_delivered',
            'stops.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $creatorEmployee = Employee::where('user_id', auth()->id())->first();
            $driverId = $validated['driver_id'] ?? $creatorEmployee?->id;

            $route = RouteModel::create([
                'route_code' => 'RT-' . now()->format('Ymd') . '-' . str_pad(RouteModel::whereDate('created_at', today())->count() + 1, 3, '0', STR_PAD_LEFT),
                'route_name' => $validated['route_name'] ?? null,
                'driver_id' => $driverId,
                'sales_rep_id' => $validated['sales_rep_id'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'start_point' => $validated['start_point'] ?? null,
                'end_point' => $validated['end_point'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            foreach ($validated['stops'] as $index => $stop) {
                $expectedAmount = array_key_exists('expected_amount', $stop) ? $stop['expected_amount'] : null;

                if (is_null($expectedAmount)) {
                    $daily = CustomerDailyExpectedAmount::where('customer_id', $stop['customer_id'])
                        ->where('date', today())
                        ->first();
                    $expectedAmount = $daily?->amount;
                }

                $boxes = $stop['boxes_count'] ?? 0;
                $cartons = $stop['cartons_count'] ?? 0;
                $bundles = $stop['bundles_count'] ?? 0;

                RouteStop::create([
                    'route_id' => $route->id,
                    'customer_id' => $stop['customer_id'],
                    'stop_order' => $index + 1,
                    'request_ids' => $stop['request_ids'] ?? [],
                    'boxes_count' => $boxes,
                    'cartons_count' => $cartons,
                    'bundles_count' => $bundles,
                    'packages_count' => $boxes + $cartons + $bundles,
                    'expected_amount' => $expectedAmount,
                    'goods_notes' => $stop['goods_notes'] ?? null,
                    'delivery_status' => $stop['delivery_status'] ?? 'pending',
                    'notes' => $stop['notes'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء خط السير بالعملاء بالترتيب',
                'data' => $route->load(['driver', 'salesRep', 'stops.customer']),
            ], 201);
        });
    }

    public function stops($id): JsonResponse
    {
        $route = RouteModel::with(['stops.customer'])->findOrFail($id);
        $this->attachDailyExpectedAmounts($route->stops);

        return response()->json([
            'success' => true,
            'data' => $route->stops,
        ]);
    }

    public function updateWithStops(Request $request, $id): JsonResponse
    {
        $route = RouteModel::findOrFail($id);
        $validated = $request->validate([
            'route_name' => 'nullable|string|max:255',
            'driver_id' => 'nullable|exists:employees,id',
            'sales_rep_id' => 'nullable|exists:employees,id',
            'vehicle_number' => 'nullable|string|max:50',
            'start_point' => 'nullable|string|max:255',
            'end_point' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,archived',
            'stops' => 'required|array|min:1',
            'stops.*.customer_id' => 'required|exists:customers,id',
            'stops.*.request_ids' => 'nullable|array',
            'stops.*.request_ids.*' => 'exists:requests,id',
            'stops.*.boxes_count' => 'nullable|integer|min:0',
            'stops.*.cartons_count' => 'nullable|integer|min:0',
            'stops.*.bundles_count' => 'nullable|integer|min:0',
            'stops.*.packages_count' => 'nullable|integer|min:0',
            'stops.*.expected_amount' => 'nullable|numeric|min:0',
            'stops.*.goods_notes' => 'nullable|string',
            'stops.*.delivery_status' => 'nullable|in:pending,delivered,not_delivered',
            'stops.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($route, $validated) {
            $route->update([
                'route_name' => $validated['route_name'] ?? null,
                'driver_id' => $validated['driver_id'] ?? null,
                'sales_rep_id' => $validated['sales_rep_id'] ?? null,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'start_point' => $validated['start_point'] ?? null,
                'end_point' => $validated['end_point'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $route->stops()->delete();
            foreach ($validated['stops'] as $index => $stop) {
                $expectedAmount = array_key_exists('expected_amount', $stop) ? $stop['expected_amount'] : null;

                if (is_null($expectedAmount)) {
                    $daily = CustomerDailyExpectedAmount::where('customer_id', $stop['customer_id'])
                        ->where('date', today())
                        ->first();
                    $expectedAmount = $daily?->amount;
                }

                $boxes = $stop['boxes_count'] ?? 0;
                $cartons = $stop['cartons_count'] ?? 0;
                $bundles = $stop['bundles_count'] ?? 0;

                RouteStop::create([
                    'route_id' => $route->id,
                    'customer_id' => $stop['customer_id'],
                    'stop_order' => $index + 1,
                    'request_ids' => $stop['request_ids'] ?? [],
                    'boxes_count' => $boxes,
                    'cartons_count' => $cartons,
                    'bundles_count' => $bundles,
                    'packages_count' => $boxes + $cartons + $bundles,
                    'expected_amount' => $expectedAmount,
                    'goods_notes' => $stop['goods_notes'] ?? null,
                    'delivery_status' => $stop['delivery_status'] ?? 'pending',
                    'notes' => $stop['notes'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث خط السير والعملاء بالترتيب',
                'data' => $route->fresh(['driver', 'salesRep', 'stops.customer']),
            ]);
        });
    }

    public function dispatch(Request $request, $id): JsonResponse
    {
        $route = RouteModel::with('stops')->findOrFail($id);

        if ($route->deliveries()->whereIn('status', ['pending', 'in_transit'])->exists()) {
            return response()->json(['success' => false, 'message' => 'لا يمكن ترحيل خط السير مرة أخرى، يوجد تسليمات معلقة أو في الطريق'], 422);
        }

        $validated = $request->validate([
            'driver_id' => 'nullable|exists:employees,id',
            'sales_rep_id' => 'nullable|exists:employees,id',
            'vehicle_number' => 'nullable|string|max:50',
            'notify_employee_id' => 'nullable|exists:employees,id',
        ]);

        $driverId = $validated['driver_id'] ?? $route->driver_id;
        if (!$driverId) {
            return response()->json(['success' => false, 'message' => 'يجب تحديد السائق قبل ترحيل خط السير'], 422);
        }

        $deliveries = DB::transaction(function () use ($route, $validated, $driverId) {
                $created = new EloquentCollection();

            foreach ($route->stops as $stop) {
                $requestIds = $stop->request_ids ?: [];
                if (empty($requestIds)) {
                    $requestIds = RequestModel::where('customer_id', $stop->customer_id)
                        ->whereIn('status', ['approved', 'ready_for_delivery'])
                        ->limit(3)
                        ->pluck('id')
                        ->all();
                }

                foreach ($requestIds as $requestId) {
                    $req = RequestModel::find($requestId);
                    if (!$req) continue;

                    $delivery = Delivery::create([
                        'delivery_number' => 'DEL-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                        'request_id' => $req->id,
                        'route_id' => $route->id,
                        'route_stop_id' => $stop->id,
                        'driver_id' => $driverId,
                        'sales_rep_id' => $validated['sales_rep_id'] ?? $route->sales_rep_id,
                        'vehicle_number' => $validated['vehicle_number'] ?? $route->vehicle_number,
                        'expected_collection_amount' => $stop->expected_amount,
                        'packages_count' => $stop->packages_count,
                        'collection_notify_employee_id' => $validated['notify_employee_id'] ?? null,
                        'delivery_items' => $stop->goods_notes ? ['goods_notes' => $stop->goods_notes] : null,
                        'status' => 'pending',
                    ]);

                    $req->update(['status' => 'in_delivery']);
                    $created->push($delivery);
                }
            }

            return $created;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم ترحيل خط السير إلى التسليمات',
            'data' => $deliveries->load(['request.customer', 'driver', 'salesRep', 'routeStop.customer']),
        ], 201);
    }

    public function createDelivery(Request $request, $id): JsonResponse
    {
        $employee = Employee::where('user_id', auth()->id())->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
        }

        $route = RouteModel::findOrFail($id);

        $isAssignedDriver = $route->driver_id === $employee->id
            || $route->sales_rep_id === $employee->id
            || Delivery::where('route_id', $route->id)
                ->where('driver_id', $employee->id)
                ->whereIn('status', ['pending', 'in_transit'])
                ->exists();

        if (!$isAssignedDriver) {
            return response()->json(['success' => false, 'message' => 'خط السير هذا غير مخصص لك'], 403);
        }

        $validated = $request->validate([
            'route_stop_id'                => 'required|exists:route_stops,id',
            'request_id'                   => 'nullable|exists:requests,id',
            'customer_id'                  => 'nullable|exists:customers,id',
            'packages_count'               => 'nullable|integer|min:0',
            'expected_collection_amount'   => 'nullable|numeric|min:0',
            'goods_notes'                  => 'nullable|string',
        ]);

        $stop = RouteStop::where('id', $validated['route_stop_id'])
            ->where('route_id', $route->id)
            ->first();

        if (!$stop) {
            return response()->json(['success' => false, 'message' => 'المحطة المحددة غير موجودة في هذا الخط'], 422);
        }

        $existing = Delivery::where('route_stop_id', $stop->id)
            ->where('status', 'completed')
            ->exists();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'تم تسليم هذه المحطة مسبقاً'], 422);
        }

        $req = !empty($validated['request_id']) ? RequestModel::find($validated['request_id']) : null;

        $delivery = DB::transaction(function () use ($route, $stop, $employee, $validated, $req) {
            $delivery = Delivery::create([
                'delivery_number'              => 'DEL-' . $route->id . '-' . $stop->id . '-' . now()->format('YmdHis'),
                'request_id'                   => $validated['request_id'] ?? null,
                'route_id'                     => $route->id,
                'route_stop_id'                => $stop->id,
                'driver_id'                    => $employee->id,
                'packages_count'               => $validated['packages_count'] ?? $stop->packages_count,
                'expected_collection_amount'   => $validated['expected_collection_amount'] ?? $stop->expected_amount,
                'delivery_items'               => !empty($validated['goods_notes']) ? ['goods_notes' => $validated['goods_notes']] : null,
                'status'                       => 'completed',
                'end_time'                     => now(),
            ]);

            $stop->update(['delivery_status' => 'delivered']);

            if ($req && in_array($req->status, ['approved', 'ready_for_delivery', 'in_delivery'])) {
                $req->update(['status' => 'delivered']);
            }

            return $delivery;
        });

        $route->loadCount(['deliveries', 'stops']);
        $summary = [
            'total_deliveries'              => $route->deliveries_count,
            'completed_deliveries'          => $route->deliveries()->where('status', 'completed')->count(),
            'total_expected_collection'     => (float) $route->deliveries()->sum('expected_collection_amount'),
            'total_packages_delivered'      => (int) $route->deliveries()->sum('packages_count'),
            'total_stops'                   => $route->stops_count,
            'completed_stops'               => $route->stops()->where('delivery_status', 'delivered')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'تم تسليم المحطة بنجاح',
            'data'    => $delivery->load(['request.customer', 'driver', 'routeStop.customer']),
            'route_summary' => $summary,
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $route     = RouteModel::findOrFail($id);
        $validated = $request->validate([
            'route_name'             => 'nullable|string|max:255',
            'driver_id'              => 'nullable|exists:employees,id',
            'sales_rep_id'           => 'nullable|exists:employees,id',
            'vehicle_number'         => 'nullable|string|max:50',
            'start_point'            => 'nullable|string|max:255',
            'end_point'              => 'nullable|string|max:255',
            'distance_km'            => 'nullable|numeric|min:0',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'waypoints'              => 'nullable|array',
            'status'                 => 'sometimes|in:active,inactive,archived',
        ]);

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث خط السير بنجاح',
            'data'    => $route,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        RouteModel::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف خط السير بنجاح']);
    }

    public function daily(Request $request): JsonResponse
    {
        $date   = $request->get('date', today()->toDateString());
        $routes = RouteModel::whereDate('created_at', $date)
            ->with(['deliveries.request.customer', 'deliveries.driver'])
            ->get();

        $summary = [
            'total_routes'    => $routes->count(),
            'total_deliveries'=> $routes->sum(fn($r) => $r->deliveries->count()),
            'completed'       => $routes->sum(fn($r) => $r->deliveries->where('status', 'completed')->count()),
            'pending'         => $routes->sum(fn($r) => $r->deliveries->where('status', 'pending')->count()),
        ];

        return response()->json(['success' => true, 'data' => $routes, 'summary' => $summary]);
    }

    public function recordOdometerStart(Request $request, $id): JsonResponse
    {
        $route = RouteModel::findOrFail($id);

        if ($route->odometer_start !== null) {
            return response()->json([
                'success' => false,
                'message' => 'تم تسجيل قراءة البداية مسبقاً'
            ], 422);
        }

        $validated = $request->validate([
            'odometer_start' => 'required|numeric|min:0|max:9999999.99',
            'photo'          => 'nullable|image|max:5120',
            'notes'          => 'nullable|string',
        ]);

        $data = ['odometer_start' => $validated['odometer_start']];

        if ($request->hasFile('photo')) {
            $data['odometer_start_photo'] = $request->file('photo')
                ->store('routes/odometer', 'public');
        }

        if ($request->filled('notes')) {
            $data['odometer_notes'] = $validated['notes'];
        }

        $route->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل قراءة عداد البداية',
            'data'    => $route->fresh(),
        ]);
    }

    public function recordOdometerEnd(Request $request, $id): JsonResponse
    {
        $route = RouteModel::findOrFail($id);

        if ($route->odometer_start === null) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل قراءة البداية أولاً'
            ], 422);
        }

        if ($route->odometer_end !== null) {
            return response()->json([
                'success' => false,
                'message' => 'تم تسجيل قراءة النهاية مسبقاً'
            ], 422);
        }

        $validated = $request->validate([
            'odometer_end' => 'required|numeric|min:0|max:9999999.99',
            'photo'        => 'nullable|image|max:5120',
            'notes'        => 'nullable|string',
        ]);

        if ($validated['odometer_end'] <= $route->odometer_start) {
            return response()->json([
                'success' => false,
                'message' => 'قراءة النهاية يجب أن تكون أكبر من قراءة البداية'
            ], 422);
        }

        $data = [
            'odometer_end' => $validated['odometer_end'],
            'actual_distance_km' => round($validated['odometer_end'] - $route->odometer_start, 2),
        ];

        if ($request->hasFile('photo')) {
            $data['odometer_end_photo'] = $request->file('photo')
                ->store('routes/odometer', 'public');
        }

        if ($request->filled('notes')) {
            $data['odometer_notes'] = isset($data['odometer_notes'])
                ? $data['odometer_notes'] . "\n" . $validated['notes']
                : $validated['notes'];
        }

        $route->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل قراءة عداد النهاية',
            'data'    => $route->fresh(),
        ]);
    }

    public function verifyOdometer(Request $request, $id): JsonResponse
    {
        $route = RouteModel::findOrFail($id);

        if ($route->odometer_start === null || $route->odometer_end === null) {
            return response()->json([
                'success' => false,
                'message' => 'يجب إكمال قراءات البداية والنهاية أولاً'
            ], 422);
        }

        if ($route->odometer_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'تم اعتماد القراءات مسبقاً'
            ], 422);
        }

        $employee = Employee::where('user_id', auth()->id())->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'لا يوجد ملف موظف مرتبط'], 404);
        }

        $route->update([
            'odometer_verified_by' => $employee->id,
            'odometer_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد قراءات العداد',
            'data'    => $route->fresh()->load('odometerVerifiedBy'),
        ]);
    }

    private function attachDailyExpectedAmounts($stops): void
    {
        if ($stops->isEmpty()) return;

        $customerIds = $stops->pluck('customer_id')->unique();
        $dailyAmounts = CustomerDailyExpectedAmount::whereIn('customer_id', $customerIds)
            ->where('date', today())
            ->get()
            ->keyBy('customer_id');

        foreach ($stops as $stop) {
            $daily = $dailyAmounts->get($stop->customer_id);
            $stop->setAttribute('daily_expected_amount', $daily ? (float) $daily->amount : null);
            $stop->setAttribute('daily_expected_amount_id', $daily?->id);
        }
    }
}
