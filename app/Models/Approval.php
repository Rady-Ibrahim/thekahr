<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'approvable_type', 'approvable_id', 'approval_level', 'approval_type', 'status',
        'approved_by_id', 'rejection_reason', 'notes', 'approved_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by_id');
    }
}
