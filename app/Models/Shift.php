<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A work shift: a start/end window with an optional break and grace period.
 */
class Shift extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'break_duration_minutes' => 'integer',
            'grace_period_minutes' => 'integer',
            'is_night_shift' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Shift span in minutes, wrapping past midnight for a night shift. */
    public function spanMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // crosses midnight
        }

        return (int) $start->diffInMinutes($end);
    }

    /** Paid working time = shift span minus the unpaid break, in hours. */
    public function workingHours(): float
    {
        return round(max(0, $this->spanMinutes() - $this->break_duration_minutes) / 60, 1);
    }

    /** "HH:MM - HH:MM" for display. */
    public function hoursLabel(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') . ' - ' . Carbon::parse($this->end_time)->format('H:i');
    }

    /** @param Builder<Shift> $query */
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

    /** @param Builder<Shift> $query */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, ['active', 'inactive'], true)
            ? $query->where('status', $status)
            : $query;
    }
}
