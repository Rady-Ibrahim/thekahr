<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'delivery_number', 'request_id', 'route_id', 'route_stop_id', 'driver_id',
        'sales_rep_id', 'vehicle_number', 'expected_collection_amount', 'packages_count',
        'collection_notify_employee_id',
        'status', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude',
        'start_time', 'end_time', 'delivery_notes', 'delivery_photo', 'signature_proof',
        'delivery_items'
    ];

    protected $casts = [
        'delivery_items' => 'json',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sales_rep_id');
    }

    public function collectionNotifyEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'collection_notify_employee_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(DeliveryCheckpoint::class);
    }

    public function tracking(): HasMany
    {
        return $this->hasMany(VehicleTracking::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
