<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array{label: string, badge_class: string, description: string}
     */
    public function accessScopeMeta(): array
    {
        return [
            'label' => $this->access_scope_label ?: 'General',
            'badge_class' => $this->access_scope_badge_class ?: 'bg-secondary',
            'description' => $this->access_scope_description ?: '',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_roles')
            ->withPivot(['granted_by', 'granted_at'])
            ->withTimestamps();
    }
}
