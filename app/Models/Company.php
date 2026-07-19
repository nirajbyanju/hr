<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'starts_on',
        'expires_on',
        'settings',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'expires_on' => 'date',
        'settings' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isPending() && ! $this->isExpired();
    }

    public function isPending(): bool
    {
        return $this->starts_on !== null && $this->starts_on->isAfter(today());
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isBefore(today());
    }
}
