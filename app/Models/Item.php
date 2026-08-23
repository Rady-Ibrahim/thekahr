<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_code', 'name', 'description', 'category', 'unit', 'price',
        'quantity', 'warehouse_id', 'notes', 'status',
        'company_name', 'barcode', 'batch_number', 'expiry_date', 'is_available',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'expiry_date'  => 'date:Y-m-d',
        'is_available' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }
}
