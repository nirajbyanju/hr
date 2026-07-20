<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
            'last_login_at' => 'datetime',
        ];
    }
}
