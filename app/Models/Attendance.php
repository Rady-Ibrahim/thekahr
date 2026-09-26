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
        'overtime_minutes', 'overtime_hours',
        'penalty_overridden',
    ];

    protected $casts = [
        'attendance_date' => 'date:Y-m-d',
        'actual_worked_hours' => 'decimal:2',
        'total_worked_hours' => 'decimal:2',
        'required_hours' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'early_exit_minutes' => 'integer',
        'total_worked_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'payroll_pushed' => 'boolean',
        'penalty_overridden' => 'boolean',
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

    /**
     * Sessions belonging to a single calendar day. Always scope a day's
     * aggregation through here (or AttendanceHoursService::dayLogs()) so
     * sessions from other days can never leak into the total.
     */
    public function logsForDate(?string $date = null): HasMany
    {
        $date = $date ?? $this->attendance_date?->toDateString();

        return $this->logs()->whereDate('log_date', $date);
    }

    /**
     * Worked hours for this day. 0.00 whenever there is no real check-in /
     * check-out pair on the record - never a hardcoded fallback.
     */
    public function workedHours(): float
    {
        return (float) ($this->total_worked_hours ?? 0);
    }

    public function overtimeMinutes(): int
    {
        return (int) ($this->overtime_minutes ?? 0);
    }

    public function overtimeHours(): float
    {
        return round((float) ($this->overtime_hours ?? 0), 2);
    }
}
