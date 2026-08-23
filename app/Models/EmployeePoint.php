<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'type',
        'points',
        'point_price',
        'total_amount',
        'reason',
        'month',
        'year',
        'created_by_id',
    ];

    protected $casts = [
        'points'       => 'decimal:2',
        'point_price'  => 'decimal:2',
        'total_amount' => 'decimal:2',
        'month'        => 'integer',
        'year'         => 'integer',
    ];

    /* ─── Relations ─── */

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /* ─── Helpers ─── */

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'credit' ? 'له (+)' : 'عليه (-)';
    }
}
