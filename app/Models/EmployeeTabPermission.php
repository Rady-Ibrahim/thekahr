<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTabPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'tab_name',
        'tab_key',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
