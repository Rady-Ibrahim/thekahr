<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Commission;
use App\Models\Employee;
use Carbon\Carbon;

class CollectionCommissionService
{
    public function createFromCollection(Collection $collection): ?Commission
    {
        if (!$collection->driver_id || (float) $collection->total_amount <= 0) {
            return null;
        }

        $existing = Commission::where('collection_id', $collection->id)->first();
        if ($existing) {
            return $existing;
        }

        $driver = $collection->relationLoaded('driver')
            ? $collection->driver
            : Employee::find($collection->driver_id);

        if (!$driver) {
            return null;
        }

        $rate = (float) ($driver->collection_commission_rate ?? 0);
        if ($rate <= 0) {
            return null;
        }

        $collectedDate = $collection->collected_date
            ? Carbon::parse($collection->collected_date)
            : now();

        $amount = round(((float) $collection->total_amount * $rate) / 100, 2);
        if ($amount <= 0) {
            return null;
        }

        return Commission::create([
            'employee_id' => $driver->id,
            'collection_id' => $collection->id,
            'month' => (int) $collectedDate->month,
            'year' => (int) $collectedDate->year,
            'amount' => $amount,
            'commission_rate' => $rate,
            'total_sales' => $collection->total_amount,
            'description' => 'عمولة تحصيل ' . ($collection->collection_number ?? '#' . $collection->id),
            'source' => 'collection',
            'status' => 'pending',
        ]);
    }

    public function syncOnCollectionRejected(Collection $collection): void
    {
        Commission::where('collection_id', $collection->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }
}
