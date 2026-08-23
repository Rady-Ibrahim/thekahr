<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteStop extends Model
{
    protected $fillable = [
        'route_id', 'customer_id', 'stop_order', 'request_ids', 'packages_count',
        'boxes_count', 'cartons_count', 'bundles_count',
        'expected_amount', 'goods_notes', 'delivery_status', 'notes',
    ];

    protected $casts = [
        'request_ids' => 'array',
        'expected_amount' => 'decimal:2',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
