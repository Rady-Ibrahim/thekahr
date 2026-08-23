<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftLateRule extends Model
{
    protected $fillable = [
        'shift_id', 'min_delay_minutes', 'max_delay_minutes',
        'deduction_type', 'deduction_value',
    ];

    protected $casts = [
        'min_delay_minutes' => 'integer',
        'max_delay_minutes' => 'integer',
        'deduction_value' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
