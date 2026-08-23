<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController
{
    public function index(Request $request): JsonResponse
    {
        $query = Item::with('warehouse');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                    ->orWhere('item_code', 'like', "%$s%")
                    ->orWhere('category', 'like', "%$s%")
                    ->orWhere('company_name', 'like', "%$s%")
                    ->orWhere('barcode', 'like', "%$s%")
                    ->orWhere('batch_number', 'like', "%$s%");
            });
        }

        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->warehouse_id);
        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('category'))     $query->where('category', $request->category);
        if ($request->has('is_available') && in_array($request->is_available, ['0', '1'], true)) {
            $query->where('is_available', (bool) $request->is_available);
        }

        $items = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'category'     => 'nullable|string',
            'unit'         => 'nullable|string|max:50',
            'price'        => 'required|numeric|min:0',
            'quantity'     => 'nullable|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes'        => 'nullable|string',
            'company_name' => 'nullable|string|max:255',
            'barcode'      => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date'  => 'nullable|date',
            'is_available' => 'sometimes|boolean',
        ]);

        $validated['item_code'] = 'ITM-' . str_pad(Item::count() + 1, 5, '0', STR_PAD_LEFT);
        $validated['unit']      = $validated['unit'] ?: 'piece';

        $item = Item::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الصنف بنجاح',
            'data'    => $item,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $item = Item::with('warehouse')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $item      = Item::findOrFail($id);
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'category'     => 'nullable|string',
            'unit'         => 'sometimes|string|max:50',
            'price'        => 'sometimes|numeric|min:0',
            'quantity'     => 'nullable|integer|min:0',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes'        => 'nullable|string',
            'status'       => 'sometimes|in:active,inactive',
            'company_name' => 'nullable|string|max:255',
            'barcode'      => 'nullable|string|max:100',
            'batch_number' => 'nullable|string|max:100',
            'expiry_date'  => 'nullable|date',
            'is_available' => 'sometimes|boolean',
        ]);

        if (array_key_exists('unit', $validated)) {
            $validated['unit'] = $validated['unit'] ?: 'piece';
        }

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصنف بنجاح',
            'data'    => $item,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Item::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'تم حذف الصنف بنجاح']);
    }

    public function categories(): JsonResponse
    {
        $categories = Item::select('category')->distinct()->whereNotNull('category')->pluck('category');
        return response()->json(['success' => true, 'data' => $categories]);
    }
}
