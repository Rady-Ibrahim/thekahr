<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use App\Http\Requests\StoreNearExpiryItemRequest;
use App\Http\Requests\UpdateNearExpiryItemRequest;
use App\Models\NearExpiryItem;
use App\Models\NearExpirySale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NearExpiryItemController
{
    use RestrictToSubordinates;

    private function canManage(): bool
    {
        return $this->isAdminUser() || Auth::user()?->hasPermission('manage_near_expiry');
    }

    /**
     * GET /api/near-expiry-items
     * كتالوج الأصناف قاربة الانتهاء.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NearExpiryItem::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%");
            });
        }

        if ($request->filled('branch')) {
            $query->where('branch', $request->branch);
        }

        if ($request->filled('expiry_status')) {
            match ($request->expiry_status) {
                'expired'  => $query->where('expiry_date', '<', now()->toDateString()),
                'critical' => $query->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]),
                'soon'     => $query->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()]),
                'ok'       => $query->where('expiry_date', '>', now()->addDays(30)->toDateString()),
                default    => null,
            };
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        $items = $query->orderBy('expiry_date')->orderByDesc('id')
            ->paginate($request->get('per_page', 24));

        $summary = [
            'total_items'   => NearExpiryItem::count(),
            'expiring_soon' => NearExpiryItem::whereBetween('expiry_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
            'expired'       => NearExpiryItem::where('expiry_date', '<', now()->toDateString())->count(),
            'out_of_stock'  => NearExpiryItem::where('stock_quantity', 0)->count(),
        ];

        return response()->json([
            'success' => true,
            'can_manage' => $this->canManage(),
            'data'    => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * POST /api/near-expiry-items
     * أي موظف مسجل يمكنه إضافة/ترميز صنف جديد.
     */
    public function store(StoreNearExpiryItemRequest $request): JsonResponse
    {
        $data = $this->extractItemData($request);
        $data['created_by'] = Auth::id();

        $item = NearExpiryItem::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصنف بنجاح',
            'data'    => $item,
        ], 201);
    }

    /**
     * PUT /api/near-expiry-items/{id}
     * التعديل للمدراء/الإدارة فقط.
     */
    public function update(UpdateNearExpiryItemRequest $request, $id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بتعديل الأصناف'], 403);
        }

        $item = NearExpiryItem::findOrFail($id);
        $data = $this->extractItemData($request);

        if ($request->boolean('remove_image') && $item->image) {
            Storage::disk('public')->delete($item->image);
            $data['image'] = null;
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصنف بنجاح',
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/near-expiry-items/{id}
     */
    public function destroy($id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بحذف الأصناف'], 403);
        }

        $item = NearExpiryItem::findOrFail($id);

        if (NearExpirySale::where('near_expiry_item_id', $item->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف صنف عليه عمليات بيع - يمكنك تصفير المخزون بدلاً من الحذف',
            ], 422);
        }

        if ($item->image) {
            Storage::disk('public')->delete($item->image);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الصنف بنجاح']);
    }

    private function extractItemData(Request $request): array
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('near-expiry', 'public');
            $data['image'] = $path;
        }

        return $data;
    }
}
