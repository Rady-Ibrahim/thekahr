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
}
