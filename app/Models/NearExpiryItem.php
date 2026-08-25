<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NearExpiryItem extends Model
{
    protected $fillable = [
        'name', 'image', 'expiry_date', 'branch',
        'unit_price', 'incentive_amount', 'stock_quantity', 'created_by',
    ];

    protected $appends = ['image_url', 'days_to_expiry', 'expiry_status'];

    protected $casts = [
        'expiry_date'      => 'date',
        'unit_price'       => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'stock_quantity'   => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(NearExpirySale::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->expiry_date->copy()->startOfDay(), false);
    }

    /**
     * expired = انتهى | critical = خلال أسبوع | soon = خلال 30 يوم | ok
     */
    public function getExpiryStatusAttribute(): string
    {
        $days = $this->days_to_expiry;

        if ($days === null) {
            return 'unknown';
        }

        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 7) {
            return 'critical';
        }
        if ($days <= 30) {
            return 'soon';
        }

        return 'ok';
    }
}
