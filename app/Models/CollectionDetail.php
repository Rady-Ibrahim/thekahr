<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionDetail extends Model
{
    protected $fillable = ['collection_id', 'request_id', 'amount', 'reference_number', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
