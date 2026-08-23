<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                    ->orWhere('warehouse_code', 'like', "%$s%");
            });
        }

        if ($request->filled('status')) $query->where('status', $request->status);

        $warehouses = $query->withCount('items')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $warehouses]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:warehouses,name',
            'address'   => 'nullable|string',
            'phone'     => 'nullable|string|max:20',
            'city'      => 'nullable|string',
            'region'    => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'capacity'  => 'nullable|integer|min:0',
            'notes'     => 'nullable|string',
        ]);

        $validated['warehouse_code'] = 'WH-' . str_pad(Warehouse::count() + 1, 4, '0', STR_PAD_LEFT);

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المخزن بنجاح',
            'data'    => $warehouse,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $warehouse = Warehouse::with(['items', 'manager'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $warehouse]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($id);
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255|unique:warehouses,name,' . $id,
            'address'   => 'nullable|string',
            'phone'     => 'nullable|string|max:20',
            'city'      => 'nullable|string',
            'region'    => 'nullable|string',
            'capacity'  => 'nullable|integer|min:0',
            'notes'     => 'nullable|string',
            'status'    => 'sometimes|in:active,inactive',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المخزن بنجاح',
            'data'    => $warehouse,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Warehouse::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف المخزن بنجاح']);
    }

    public function items($id, Request $request): JsonResponse
    {
        $items = Item::where('warehouse_id', $id)
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $items]);
    }
}
