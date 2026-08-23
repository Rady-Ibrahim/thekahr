<?php

namespace App\Models;

use App\Enums\EmployeeSubRoleEnum;
use App\Enums\EmployeeTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_code', 'name', 'email', 'phone', 'phone_alternative',
        'national_id', 'date_of_birth', 'joining_date', 'position', 'department',
        'employee_type', 'sub_role', 'salary_type', 'base_salary', 'collection_commission_rate',
        'status', 'car_license', 'car_number',
        'gps_device_id', 'reporting_manager_id', 'notes'
    ];

    protected $appends = ['employee_type_label', 'is_manager'];

    protected $casts = [
        'joining_date' => 'date',
        'date_of_birth' => 'date',
        'base_salary' => 'decimal:2',
        'collection_commission_rate' => 'decimal:2',
        'employee_type' => EmployeeTypeEnum::class,
        'sub_role' => EmployeeSubRoleEnum::class,
    ];

    protected static function booted(): void
    {
        // Soft delete keeps the row; free unique columns so phone/email/code can be reused.
        static::softDeleted(function (Employee $employee) {
            $employee->releaseUniqueConstraints();
        });
    }

    public function releaseUniqueConstraints(): void
    {
        $originalPhone = $this->getRawOriginal('phone') ?? $this->phone ?? '';
        $originalCode = $this->getRawOriginal('employee_code') ?? $this->employee_code ?? '';

        static::withTrashed()
            ->whereKey($this->id)
            ->update([
                'phone' => mb_substr('del' . $this->id . '_' . $originalPhone, 0, 255),
                'email' => null,
                'employee_code' => mb_substr('DEL' . $this->id . '_' . $originalCode, 0, 255),
                'national_id' => null,
                'updated_at' => now(),
            ]);
    }

    public function getEmployeeTypeLabelAttribute(): string
    {
        $type = $this->employee_type instanceof EmployeeTypeEnum
            ? $this->employee_type
            : EmployeeTypeEnum::tryFrom((string) $this->employee_type);

        return $type?->label() ?? EmployeeTypeEnum::EMPLOYEE->label();
    }

    public function getIsManagerAttribute(): bool
    {
        return $this->employee_type === EmployeeTypeEnum::MANAGER;
    }

    public function isDriverRepresentative(): bool
    {
        return $this->employee_type === EmployeeTypeEnum::DRIVER_REPRESENTATIVE;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function incentives(): HasMany
    {
        return $this->hasMany(Incentive::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(Allowance::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    public function tabPermissions(): HasMany
    {
        return $this->hasMany(EmployeeTabPermission::class)->orderBy('sort_order');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(EmployeeMessage::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(EmployeeMessage::class, 'receiver_id');
    }

    public function chatGroups(): BelongsToMany
    {
        return $this->belongsToMany(ChatGroup::class, 'chat_group_members', 'employee_id', 'group_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function points(): HasMany
    {
        return $this->hasMany(EmployeePoint::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function currentShift(): ?Shift
    {
        $assignment = EmployeeShift::where('employee_id', $this->id)
            ->active(now())
            ->first();

        return $assignment?->shift;
    }
}
