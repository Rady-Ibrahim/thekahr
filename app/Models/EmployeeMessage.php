<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'group_id',
        'message',
        'message_type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'message_type' => 'string',
    ];

    /* ─── Relations ─── */

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'receiver_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'group_id');
    }

    /* ─── Scopes ─── */

    /**
     * Conversation between two employees (either direction).
     */
    public function scopeConversation($query, int $empA, int $empB)
    {
        return $query->where(function ($q) use ($empA, $empB) {
            $q->whereNull('group_id')
              ->where('sender_id', $empA)
              ->where('receiver_id', $empB);
        })->orWhere(function ($q) use ($empA, $empB) {
            $q->whereNull('group_id')
              ->where('sender_id', $empB)
              ->where('receiver_id', $empA);
        });
    }

    /**
     * Scope to only group messages.
     */
    public function scopeInGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }
}
