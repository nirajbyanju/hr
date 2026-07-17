<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TaskActivityLog extends Model
{
    use HasFactory;

    protected $table = 'task_activity_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
