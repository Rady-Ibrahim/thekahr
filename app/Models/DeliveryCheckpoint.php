<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryCheckpoint extends Model
{
    protected $fillable = [
        'delivery_id', 'checkpoint_order', 'location_name', 'latitude', 'longitude',
        'expected_time', 'actual_time', 'notes'
    ];

    protected $casts = [
        'expected_time' => 'datetime',
        'actual_time' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
