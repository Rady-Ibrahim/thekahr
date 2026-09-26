<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'attendance_id', 'log_date',
        'check_in_time', 'check_out_time',
        'check_in_latitude', 'check_in_longitude', 'check_out_latitude', 'check_out_longitude',
        'check_in_photo', 'check_out_photo',
        'duration_minutes', 'source', 'notes',
    ];

    protected $casts = [
        'log_date' => 'date:Y-m-d',
        'duration_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function isOpen(): bool
    {
        return $this->check_out_time === null;
    }

    public function checkInAt(): Carbon
    {
        return Carbon::parse($this->log_date->toDateString() . ' ' . $this->check_in_time);
    }

    public function checkOutAt(): Carbon
    {
        $out = Carbon::parse($this->log_date->toDateString() . ' ' . $this->check_out_time);
        if ($out->lessThan($this->checkInAt())) {
            $out->addDay();
        }

        return $out;
    }

    /**
     * This session's own duration in minutes, computed from its check-in /
     * check-out pair. Returns 0 for a missing check-in or an open session, so
     * a day total can never be inflated by an unfinished punch.
     */
    public function durationMinutes(): int
    {
        if ($this->isOpen() || $this->check_in_time === null) {
            return 0;
        }

        return (int) $this->checkOutAt()->diffInMinutes($this->checkInAt());
    }
}
