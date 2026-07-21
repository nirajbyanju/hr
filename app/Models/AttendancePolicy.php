<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A named attendance policy: grace periods and overtime rate.
 */
class AttendancePolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'late_arrival_grace_minutes' => 'integer',
            'early_departure_grace_minutes' => 'integer',
            'overtime_rate_per_hour' => 'decimal:2',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @param Builder<AttendancePolicy> $query */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $like = '%' . $term . '%';
            $inner->where('name', 'like', $like)->orWhere('description', 'like', $like);
        });
    }

    /** @param Builder<AttendancePolicy> $query */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, ['active', 'inactive'], true)
            ? $query->where('status', $status)
            : $query;
    }
}
