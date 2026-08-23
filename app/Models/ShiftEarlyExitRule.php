<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftEarlyExitRule extends Model
{
    protected $fillable = [
        'shift_id', 'min_early_minutes', 'max_early_minutes',
        'deduction_type', 'deduction_value',
    ];

    protected $casts = [
        'min_early_minutes' => 'integer',
        'max_early_minutes' => 'integer',
        'deduction_value' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
