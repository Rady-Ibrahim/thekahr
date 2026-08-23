<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Traits\RestrictToSubordinates;
use App\Http\Requests\StoreNearExpirySaleRequest;
use App\Models\Employee;
use App\Models\NearExpiryItem;
use App\Models\NearExpirySale;
use App\Services\NearExpirySaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NearExpirySaleController
{
    use RestrictToSubordinates;

    public function __construct(private NearExpirySaleService $saleService) {}

    private function canManage(): bool
    {
        return $this->isAdminUser() || Auth::user()?->hasPermission('manage_near_expiry');
    }

    /**
     * GET /api/near-expiry-sales
     * سجل المبيعات (الموظف يري مبيعاته فقط - المدير يري فريقه).
     */
    public function index(Request $request): JsonResponse
    {
        $query = NearExpirySale::with([
            'item:id,name,image,expiry_date,branch',
            'employee:id,name,employee_code,position,department',
            'approver:id,name',
        ]);

        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('item_id'))     $query->where('near_expiry_item_id', $request->item_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('month'))       $query->where('month', $request->month);
        if ($request->filled('year'))        $query->where('year', $request->year);
        if ($request->filled('branch'))      $query->where('branch', $request->branch);

        $this->scopeSubordinates($query);

        $sales = $query->orderByDesc('id')->paginate($request->get('per_page', 15));

        $summaryQuery = NearExpirySale::query();
        if ($request->filled('month')) $summaryQuery->where('month', $request->month);
        if ($request->filled('year'))  $summaryQuery->where('year', $request->year);

        $approvedBase = (clone $summaryQuery)->where('status', 'approved');
        $pendingBase  = (clone $summaryQuery)->where('status', 'pending');

        $summary = [
            'approved_count'      => (clone $approvedBase)->count(),
            'approved_quantity'   => (int) (clone $approvedBase)->sum('quantity_sold'),
            'approved_incentives' => (float) $approvedBase->sum('total_incentive'),
            'pending_count'       => $pendingBase->count(),
        ];

        return response()->json([
            'success' => true,
            'can_manage' => $this->canManage(),
            'data'    => $sales,
            'summary' => $summary,
        ]);
    }

    /**
     * POST /api/near-expiry-sales
     * تسجيل بيع: الموظف يسجل لنفسه، والمدير/الإدارة يمكنه التسجيل لأي موظف ضمن نطاقه.
     */
    public function store(StoreNearExpirySaleRequest $request): JsonResponse
    {
        $me = $this->getCurrentEmployee();
        if (!$me) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب',
            ], 404);
        }

        $employeeId = $me->id;

        if ($request->filled('employee_id') && (int) $request->employee_id !== $me->id) {
            $this->validateSubordinate((int) $request->employee_id);
            $employeeId = (int) $request->employee_id;
        }

        $item = NearExpiryItem::findOrFail($request->near_expiry_item_id);

        $sale = $this->saleService->logSale(
            $item,
            $employeeId,
            $request->safe()->except(['near_expiry_item_id', 'employee_id']),
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل البيع بنجاح وبانتظار الاعتماد',
            'data'    => $sale->load(['item:id,name', 'employee:id,name']),
        ], 201);
    }

    /**
     * POST /api/near-expiry-sales/{id}/approve
     * اعتماد البيع وإنشاء الحافز تلقائياً.
     */
    public function approve($id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك باعتماد المبيعات'], 403);
        }

        $sale = NearExpirySale::findOrFail($id);
        $this->validateSubordinate($sale->employee_id);

        $approver = $this->getCurrentEmployee();
        if (!$approver) {
            return response()->json(['success' => false, 'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
        }

        $sale = $this->saleService->approveSale($sale, $approver->id);

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد البيع وإضافة الحافز لراتب الشهر تلقائياً',
            'data'    => $sale->load(['item:id,name', 'employee:id,name', 'incentive']),
        ]);
    }

    /**
     * POST /api/near-expiry-sales/{id}/reject
     * رفض البيع: إرجاع المخزون وحذف الحافز إن وجد.
     */
    public function reject($id): JsonResponse
    {
        if (!$this->canManage()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك برفض المبيعات'], 403);
        }

        $sale = NearExpirySale::findOrFail($id);
        $this->validateSubordinate($sale->employee_id);

        $approver = $this->getCurrentEmployee();
        if (!$approver) {
            return response()->json(['success' => false, 'message' => 'لا يوجد ملف موظف مرتبط بهذا الحساب'], 404);
        }

        $sale = $this->saleService->rejectSale($sale, $approver->id);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض البيع وإرجاع الكمية للمخزون',
            'data'    => $sale->load(['item:id,name', 'employee:id,name']),
        ]);
    }

    /**
     * DELETE /api/near-expiry-sales/{id}
     * حذف سجل معلق أو مرفوض فقط (المالك أو الإدارة).
     */
    public function destroy($id): JsonResponse
    {
        $sale = NearExpirySale::findOrFail($id);

        $me = $this->getCurrentEmployee();
        $isOwner = $me && $sale->employee_id === $me->id;

        if (!$isOwner && !$this->canManage()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بحذف هذا السجل'], 403);
        }

        if ($sale->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف بيع معتمد - استخدم الرفض قبل الاعتماد',
            ], 422);
        }

        if ($sale->status === 'pending') {
            NearExpiryItem::whereKey($sale->near_expiry_item_id)
                ->increment('stock_quantity', $sale->quantity_sold);
        }

        $sale->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف سجل البيع بنجاح']);
    }

    /**
     * GET /api/near-expiry-sales/leaderboard?month=&year=&limit=
     * ترتيب أفضل مندوبي مبيعات المنتجات قاربة الانتهاء.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year', now()->year);
        $limit = min(100, max(1, (int) $request->get('limit', 10)));

        $rows = NearExpirySale::query()
            ->selectRaw('employee_id,
                SUM(quantity_sold) as total_quantity,
                SUM(total_incentive) as total_incentive,
                COUNT(*) as sales_count')
            ->where('status', 'approved')
            ->where('month', $month)
            ->where('year', $year)
            ->groupBy('employee_id')
            ->orderByDesc('total_incentive')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        $leaders = $rows->values()->map(function ($row, $index) {
            $employee = Employee::withTrashed()
                ->where('id', $row->employee_id)
                ->first(['id', 'name', 'employee_code', 'position', 'department']);

            return [
                'rank'             => $index + 1,
                'employee_id'      => $row->employee_id,
                'employee_name'    => $employee?->name ?? 'موظف محذوف',
                'employee_code'    => $employee?->employee_code ?? '-',
                'position'         => $employee?->position,
                'department'       => $employee?->department,
                'sales_count'      => (int) $row->sales_count,
                'total_quantity'   => (int) $row->total_quantity,
                'total_incentive'  => (float) $row->total_incentive,
            ];
        });

        $myRank = null;
        $me = $this->getCurrentEmployee();
        if ($me) {
            $position = $leaders->search(fn($l) => $l['employee_id'] === $me->id);
            if ($position !== false) {
                $myRank = $leaders[$position];
            } else {
                $mine = NearExpirySale::where('employee_id', $me->id)
                    ->where('status', 'approved')
                    ->where('month', $month)->where('year', $year)
                    ->selectRaw('COALESCE(SUM(quantity_sold),0) as q, COALESCE(SUM(total_incentive),0) as t, COUNT(*) as c')
                    ->first();
                $myRank = [
                    'rank'            => null,
                    'employee_id'     => $me->id,
                    'employee_name'   => $me->name,
                    'employee_code'   => $me->employee_code,
                    'position'        => $me->position,
                    'department'      => $me->department,
                    'sales_count'     => (int) $mine->c,
                    'total_quantity'  => (int) $mine->q,
                    'total_incentive' => (float) $mine->t,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'month'   => $month,
            'year'    => $year,
            'data'    => $leaders,
            'my_rank' => $myRank,
        ]);
    }
}
