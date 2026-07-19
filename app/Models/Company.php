<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
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

    /**
     * The tenant that owns an email address, matched on the part after the "@".
     * Returns null when no company is registered for that domain.
     */
    public static function findForEmail(?string $email): ?self
    {
        $domain = static::domainFromEmail($email);

        if ($domain === null) {
            return null;
        }

        return static::query()->where('domain', $domain)->first();
    }

    /** Normalised domain part of an email address, or null if there isn't one. */
    public static function domainFromEmail(?string $email): ?string
    {
        $position = strrpos((string) $email, '@');

        if ($position === false) {
            return null;
        }

        $domain = Str::lower(trim(substr((string) $email, $position + 1)));

        return $domain === '' ? null : $domain;
    }

    /**
     * The fallback company. It cannot be renamed, suspended or deleted, since
     * doing so would lock everyone out of a single-company install.
     */
    public function isDefault(): bool
    {
        return $this->slug === config('tenancy.default_slug', 'default');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isPending() && ! $this->isExpired();
    }

    /**
     * Why this company cannot be accessed right now, or null when it is active.
     * Shared by the login controller and IdentifyTenant so both report the same
     * reason for the same state.
     */
    public function inactiveReason(): ?string
    {
        return match (true) {
            $this->isExpired() => __('This company account has expired. Please contact support.'),
            $this->isPending() => __('This company account is not active yet. Please contact support.'),
            $this->status !== 'active' => __('This company account is suspended. Please contact support.'),
            default => null,
        };
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
