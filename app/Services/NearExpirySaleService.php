<?php

namespace App\Services;

use App\Models\Incentive;
use App\Models\NearExpiryItem;
use App\Models\NearExpirySale;
use App\Models\Salary;
use Illuminate\Support\Facades\DB;

class NearExpirySaleService
{
    /**
     * تسجيل عملية بيع: خصم من المخزون وإنشاء سجل معلق.
     */
    public function logSale(NearExpiryItem $item, int $employeeId, array $data, ?int $createdByUserId): NearExpirySale
    {
        return DB::transaction(function () use ($item, $employeeId, $data, $createdByUserId) {
            $item = NearExpiryItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($data['quantity_sold'] > $item->stock_quantity) {
                abort(422, "الكمية المتاحة من الصنف {$item->name} هي {$item->stock_quantity} فقط");
            }

            $invoiceDate = \Carbon\Carbon::parse($data['invoice_date']);

            $sale = NearExpirySale::create([
                'near_expiry_item_id' => $item->id,
                'employee_id'         => $employeeId,
                'branch'              => $data['branch'] ?? $item->branch,
                'invoice_number'      => $data['invoice_number'] ?? null,
                'invoice_date'        => $invoiceDate->toDateString(),
                'quantity_sold'       => $data['quantity_sold'],
                'unit_price'          => $item->unit_price,
                'unit_incentive'      => $item->incentive_amount,
                'total_incentive'     => round($data['quantity_sold'] * $item->incentive_amount, 2),
                'month'               => $invoiceDate->month,
                'year'                => $invoiceDate->year,
                'status'              => 'pending',
                'created_by'          => $createdByUserId,
            ]);

            $item->decrement('stock_quantity', $data['quantity_sold']);

            return $sale;
        });
    }

    /**
     * اعتماد البيع: إنشاء حافز معتمد يُحسب تلقائياً داخل الراتب الشهري
     * (SalaryCalculationService يجمع الحوافز المعتمدة لنفس الشهر/السنة).
     */
    public function approveSale(NearExpirySale $sale, int $approverEmployeeId): NearExpirySale
    {
        return DB::transaction(function () use ($sale, $approverEmployeeId) {
            $sale = NearExpirySale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'approved') {
                abort(422, 'تم اعتماد هذا البيع مسبقاً');
            }

            if ($sale->status === 'rejected') {
                abort(422, 'لا يمكن اعتماد بيع مرفوض - أعد تسجيله من جديد');
            }

            $item = $sale->item;

            $incentive = Incentive::create([
                'employee_id'    => $sale->employee_id,
                'month'          => $sale->month,
                'year'           => $sale->year,
                'amount'         => $sale->total_incentive,
                'incentive_type' => 'حوافز بيع منتج قارب للانتهاء',
                'reason'         => sprintf(
                    'بيع %d وحدة من "%s" - فاتورة %s بتاريخ %s',
                    $sale->quantity_sold,
                    $item?->name ?? '-',
                    $sale->invoice_number ?: 'بدون رقم',
                    $sale->invoice_date->format('Y-m-d')
                ),
                'status'         => 'approved',
                'approved_by_id' => $approverEmployeeId,
            ]);

            $sale->update([
                'status'       => 'approved',
                'approved_by'  => $approverEmployeeId,
                'incentive_id' => $incentive->id,
            ]);

            return $sale;
        });
    }

    /**
     * رفض البيع: إرجاع الكمية للمخزون وحذف الحافز المرتبط
     * (يُمنع الرفض إذا كان راتب نفس الشهر معتمداً أو مدفوعاً).
     */
    public function rejectSale(NearExpirySale $sale, int $approverEmployeeId): NearExpirySale
    {
        return DB::transaction(function () use ($sale, $approverEmployeeId) {
            $sale = NearExpirySale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== 'pending') {
                abort(422, 'يمكن رفض البيع المعلق فقط');
            }

            $salaryLocked = Salary::where('employee_id', $sale->employee_id)
                ->where('month', $sale->month)
                ->where('year', $sale->year)
                ->whereIn('status', ['approved', 'paid'])
                ->exists();

            if ($salaryLocked) {
                abort(422, 'لا يمكن رفض البيع لأن راتب شهر الفاتورة تم اعتماده أو صرفه');
            }

            if ($sale->incentive_id) {
                Incentive::where('id', $sale->incentive_id)->delete();
            }

            $sale->update([
                'status'       => 'rejected',
                'approved_by'  => $approverEmployeeId,
                'incentive_id' => null,
            ]);

            NearExpiryItem::whereKey($sale->near_expiry_item_id)->increment('stock_quantity', $sale->quantity_sold);

            return $sale;
        });
    }
}
