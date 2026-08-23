<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NearExpirySale extends Model
{
    protected $fillable = [
        'near_expiry_item_id', 'employee_id', 'branch',
        'invoice_number', 'invoice_date', 'quantity_sold',
        'unit_price', 'unit_incentive', 'total_incentive',
        'month', 'year', 'status', 'approved_by', 'incentive_id', 'created_by',
    ];

    protected $appends = ['status_label'];

    protected $casts = [
        'invoice_date'    => 'date',
        'unit_price'      => 'decimal:2',
        'unit_incentive'  => 'decimal:2',
        'total_incentive' => 'decimal:2',
        'quantity_sold'   => 'integer',
        'month'           => 'integer',
        'year'            => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(NearExpiryItem::class, 'near_expiry_item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function incentive(): BelongsTo
    {
        return $this->belongsTo(Incentive::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'معتمد',
            'rejected' => 'مرفوض',
            default    => 'معلق',
        };
    }
}
