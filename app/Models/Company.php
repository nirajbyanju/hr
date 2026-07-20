<?php

namespace App\Models;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A tenant company. This IS stancl/tenancy's tenant model (table `companies`),
 * so creating one fires TenantCreated, which provisions its database.
 *
 * HasDomains is deliberately not used: tenants are identified by the domain
 * part of a user's login email, not by the HTTP host, so there is no subdomain
 * and no DNS setup.
 *
 * Lives in the central database (CentralConnection is inherited from BaseTenant
 * and must not be overridden — Company::find() has to work from inside
 * $company->run(), when the default connection is the tenant's).
 */
class Company extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $table = 'companies';

    protected $casts = [
        'starts_on' => 'date',
        'expires_on' => 'date',
        'stats_synced_at' => 'datetime',
    ];

    /**
     * Real database columns. Anything not listed here is silently swept into
     * the `data` JSON column by stancl's VirtualColumn — which would drop the
     * unique index on `domain` and break findForEmail() entirely.
     *
     * `tenancy_db_name` is deliberately absent: stancl expects to find it in
     * `data`.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'domain',
            'status',
            'starts_on',
            'expires_on',
            'users_count',
            'employees_count',
            'stats_synced_at',
        ];
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

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isPending() && ! $this->isExpired();
    }

    /**
     * Why this company cannot be accessed right now, or null when it is active.
     * Shared by the login controller and the tenancy middleware so both report
     * the same reason for the same state.
     */
    public function inactiveReason(): ?string
    {
        return match (true) {
            $this->isExpired() => __('This company account has expired. Please contact support.'),
            $this->isPending() => __('This company account is not active yet. Please contact support.'),
            $this->status === 'provisioning' => __('This company account is still being set up. Please try again shortly.'),
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
