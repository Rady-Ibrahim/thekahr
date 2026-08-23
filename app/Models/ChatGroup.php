<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'created_by_id',
        'avatar',
        'description',
        'status',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class, 'group_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'chat_group_members', 'group_id', 'employee_id')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmployeeMessage::class, 'group_id');
    }

    public function readTrackers(): HasMany
    {
        return $this->hasMany(GroupMessageRead::class, 'group_id');
    }
}
