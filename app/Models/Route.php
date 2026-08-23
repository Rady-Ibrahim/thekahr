<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code', 'route_name', 'driver_id', 'sales_rep_id', 'vehicle_number',
        'start_point', 'end_point', 'distance_km',
        'estimated_time_minutes', 'waypoints', 'status',
        'odometer_start', 'odometer_end', 'odometer_start_photo', 'odometer_end_photo',
        'actual_distance_km', 'odometer_notes', 'odometer_verified_by', 'odometer_verified_at',
    ];

    protected $casts = [
        'waypoints'           => 'json',
        'odometer_start'      => 'decimal:2',
        'odometer_end'        => 'decimal:2',
        'actual_distance_km'  => 'decimal:2',
        'odometer_verified_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_rep_id');
    }

    public function odometerVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'odometer_verified_by');
    }
}
