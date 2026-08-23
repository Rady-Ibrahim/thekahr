<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarViolation extends Model
{
    protected $fillable = [
        'employee_id', 'vehicle_number', 'violation_type', 'violation_date',
        'violation_code', 'fine_amount', 'status', 'reason'
    ];

    protected $casts = [
        'fine_amount' => 'decimal:2',
        'violation_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
