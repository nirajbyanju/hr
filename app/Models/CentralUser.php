<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A platform (landlord) administrator, authenticated by the `central` guard.
 *
 * CentralConnection pins this model to the central database. Without it, any
 * request that has already initialized tenancy would look for `central_users`
 * in the tenant database and fail with "table not found".
 */
class CentralUser extends Authenticatable
{
    use CentralConnection;
    use Notifiable;

    protected $table = 'central_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'expires_on',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'expires_on' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /** An account past its end date, if it has one. */
    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isBefore(today());
    }

    /** Whether this administrator may use the console right now. */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /**
     * Why this administrator cannot sign in, or null when they can.
     *
     * Shared by the login controller and the session middleware so both give
     * the same reason for the same state — and so an account disabled or
     * expired mid-session is turned away with the same wording it would get at
     * the login form.
     */
    public function inactiveReason(): ?string
    {
        return match (true) {
            $this->isExpired() => __('This platform administrator account has expired.'),
            ! $this->is_active => __('This platform administrator account is disabled.'),
            default => null,
        };
    }

    /** One-word lifecycle state, for badges and filters. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isExpired() => 'Expired',
            ! $this->is_active => 'Disabled',
            default => 'Active',
        };
    }

    /** Days until the account expires; negative once past, null with no expiry. */
    public function daysUntilExpiry(): ?int
    {
        return $this->expires_on === null ? null : today()->diffInDays($this->expires_on, false);
    }

    /** Up to two initials for the list and detail avatars. */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $letters = array_map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)), array_filter($words));

        return implode('', array_slice($letters, 0, 2)) ?: Str::upper(Str::substr((string) $this->email, 0, 1));
    }

    /** Free-text match across the fields shown in the list. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%' . $term . '%')
                ->orWhere('email', 'like', '%' . $term . '%');
        });
    }

    /**
     * Narrow the list to one lifecycle state. Expiry is a date comparison
     * rather than a stored flag, so "active" has to exclude expired rows
     * explicitly — an account can be is_active AND past its end date.
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'active' => $query->where('is_active', true)
                ->where(fn (Builder $q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today())),
            'disabled' => $query->where('is_active', false),
            'expired' => $query->whereNotNull('expires_on')->where('expires_on', '<', today()),
            'expiring' => $query->whereNotNull('expires_on')
                ->whereBetween('expires_on', [today(), today()->addDays(30)]),
            default => $query,
        };
    }
}
