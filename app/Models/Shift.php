<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'name', 'start_time', 'end_time', 'grace_period_minutes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grace_period_minutes' => 'integer',
    ];

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
