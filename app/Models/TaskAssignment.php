<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class, 'status_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TaskStatusHistory::class);
    }

    /**
     * The most recent status transition for this assignment — its `changed_at` is the moment
     * the assignment entered its current status, i.e. the start of the "time in stage" clock.
     */
    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(TaskStatusHistory::class, 'task_assignment_id')->latestOfMany('changed_at');
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(TaskTransferRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class);
    }
}
