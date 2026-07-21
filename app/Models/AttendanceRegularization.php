<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to correct an attendance record's clock-in / clock-out times,
 * with a pending → approved/rejected review workflow.
 */
class AttendanceRegularization extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'original_check_in_at' => 'datetime',
            'original_check_out_at' => 'datetime',
            'requested_check_in_at' => 'datetime',
            'requested_check_out_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }

    /** @param Builder<AttendanceRegularization> $query */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, ['pending', 'approved', 'rejected'], true)
            ? $query->where('status', $status)
            : $query;
    }

    /**
     * Free-text match on the employee name/code or the reason.
     *
     * @param Builder<AttendanceRegularization> $query
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term): void {
            $like = '%' . $term . '%';
            $inner->where('reason', 'like', $like)
                ->orWhereHas('employee', function (Builder $emp) use ($like): void {
                    $emp->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('employee_code', 'like', $like);
                });
        });
    }
}
