<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_period_minutes', 'is_active',
        'required_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grace_period_minutes' => 'integer',
        'required_hours' => 'decimal:2',
    ];

    /**
     * Hours an employee on this shift is required to fulfil in a day.
     * Derived from the shift's own start/end window (overnight aware) and
     * falls back to the configured default for open-ended shifts.
     */
    public function requiredHours(): float
    {
        if ($this->required_hours !== null && (float) $this->required_hours > 0) {
            return round((float) $this->required_hours, 2);
        }

        $fromMinutes = $this->minutesOfDay($this->start_time);
        $toMinutes   = $this->minutesOfDay($this->end_time);

        if ($fromMinutes === null || $toMinutes === null) {
            return (float) config('hr.working_hours.daily_hours', 8);
        }

        $length = $toMinutes - $fromMinutes;
        if ($length <= 0) {
            $length += 24 * 60; // overnight shift
        }

        return round($length / 60, 2);
    }

    private function minutesOfDay(?string $time): ?int
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    public function lateRules(): HasMany
    {
        return $this->hasMany(ShiftLateRule::class);
    }

    public function earlyExitRules(): HasMany
    {
        return $this->hasMany(ShiftEarlyExitRule::class);
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
