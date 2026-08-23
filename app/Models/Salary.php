<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'month', 'year', 'base_salary', 'gross_salary',
        'total_incentives', 'total_allowances', 'total_commissions',
        'total_deductions', 'total_advances', 'total_violations', 'net_salary',
        'total_points_credit', 'total_points_debit',
        'status', 'approved_by_id', 'payment_method', 'payment_date', 'approval_notes'
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_incentives' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_commissions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_advances' => 'decimal:2',
        'total_violations' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'total_points_credit' => 'decimal:2',
        'total_points_debit' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(SalaryComponentLog::class);
    }
}
