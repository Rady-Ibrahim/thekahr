<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    public const HOURS_FULFILLED = 'fulfilled';
    public const HOURS_SHORTFALL = 'shortfall';
    public const HOURS_OVERTIME = 'overtime';

    protected $fillable = [
        'employee_id', 'attendance_date', 'check_in_time', 'check_out_time',
        'check_in_latitude', 'check_in_longitude', 'check_out_latitude', 'check_out_longitude',
        'check_in_photo', 'check_out_photo', 'status', 'late_minutes', 'working_hours', 'notes',
        'shift_id', 'early_exit_minutes', 'actual_worked_hours',
        'applied_late_deduction_type', 'applied_early_deduction_type',
        'deduction_amount', 'payroll_pushed',
        'total_worked_minutes', 'total_worked_hours', 'required_hours', 'hours_status',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'actual_worked_hours' => 'decimal:2',
        'total_worked_hours' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'early_exit_minutes' => 'integer',
        'total_worked_minutes' => 'integer',
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

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class)->orderBy('check_in_time');
    }
}
