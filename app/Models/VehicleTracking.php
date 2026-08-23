<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTracking extends Model
{
    protected $fillable = ['delivery_id', 'latitude', 'longitude', 'speed', 'direction', 'captured_at'];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}
