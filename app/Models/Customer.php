<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_code', 'name', 'company_name', 'phone', 'phone_alternative',
        'email', 'city', 'region', 'address', 'latitude', 'longitude', 'notes', 'status'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'customer_employee')->withTimestamps();
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }
}
