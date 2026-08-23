<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number', 'customer_id', 'customer_name', 'company_name', 'warehouse',
        'assigned_employee_id', 'items_count', 'orders_count', 'total_quantity', 'total_amount',
        'payment_type', 'status', 'created_by_id', 'prepared_by_id', 'reviewer_employee_id',
        'reviewed_by_id', 'approved_by_id',
        'prepared_at', 'reviewed_at', 'approved_at', 'estimated_delivery_date',
        'actual_delivery_date', 'started_at', 'ended_at',
        'notes', 'rejection_reason'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'estimated_delivery_date' => 'date',
        'actual_delivery_date' => 'datetime',
        'prepared_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'approvable_id')->where('approvable_type', self::class);
    }

    public function delivery(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by_id');
    }

    public function reviewerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_employee_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by_id');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function employee(): BelongsTo
    {
        return $this->assignedEmployee();
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at) return null;

        $end = $this->ended_at ?? now();
        $start = $this->started_at instanceof \Carbon\Carbon
            ? $this->started_at
            : carbon($this->started_at);
        $end = $end instanceof \Carbon\Carbon
            ? $end
            : carbon($end);

        $diff = $start->diff($end);
        $parts = [];
        if ($diff->h > 0) $parts[] = $diff->h . ' ساعة';
        if ($diff->i > 0) $parts[] = $diff->i . ' دقيقة';
        if ($diff->s > 0) $parts[] = $diff->s . ' ثانية';

        return implode(' ', $parts) ?: 'أقل من دقيقة';
    }

    public static function nextPrepaidNumber(): string
    {
        $lastNumber = self::withTrashed()
            ->where('request_number', 'like', 'Order-%')
            ->orderByRaw("CAST(SUBSTRING(request_number, 7) AS UNSIGNED) DESC")
            ->value(DB::raw("CAST(SUBSTRING(request_number, 7) AS UNSIGNED)"));

        $next = ($lastNumber ?? 0) + 1;

        return 'Order-' . str_pad($next, 2, '0', STR_PAD_LEFT);
    }
}
