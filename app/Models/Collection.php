<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'collection_number', 'delivery_id', 'customer_id', 'driver_id', 'total_amount', 'payment_method',
        'collection_status', 'collected_date', 'deposited_date', 'notes', 'check_number',
        'check_due_date'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'collected_date' => 'date',
        'deposited_date' => 'date',
        'check_due_date' => 'date',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(CollectionDetail::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    /**
     * Direct manager of the driver/representative may approve.
     * HR / super_admin may also approve (dashboard).
     */
    public function canBeApprovedBy(?Employee $employee, $user = null): bool
    {
        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'hr_manager'])) {
            return true;
        }

        if (!$employee || !$this->driver_id) {
            return false;
        }

        $driver = $this->relationLoaded('driver')
            ? $this->driver
            : $this->driver()->first();

        return $driver && (int) $driver->reporting_manager_id === (int) $employee->id;
    }
}
