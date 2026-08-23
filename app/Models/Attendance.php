<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'attendance_date', 'check_in_time', 'check_out_time',
        'check_in_latitude', 'check_in_longitude', 'check_out_latitude', 'check_out_longitude',
        'check_in_photo', 'check_out_photo', 'status', 'late_minutes', 'working_hours', 'notes',
        'shift_id', 'early_exit_minutes', 'actual_worked_hours',
        'applied_late_deduction_type', 'applied_early_deduction_type',
        'deduction_amount', 'payroll_pushed',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'actual_worked_hours' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'early_exit_minutes' => 'integer',
        'payroll_pushed' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
