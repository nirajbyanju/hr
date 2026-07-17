<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the "never delete" human-readable activity timeline. Kept separate from
 * TaskStatusHistory, which is the narrow, strongly-typed workflow ledger used for
 * reporting (aging, average completion time) rather than display.
 */
class TaskActivityLogger
{
    /** @param array<string, mixed> $meta */
    public function log(Task $task, string $event, string $description, ?User $causer = null, ?Model $subject = null, array $meta = []): TaskActivityLog
    {
        return TaskActivityLog::query()->create([
            'task_id' => $task->id,
            'causer_user_id' => $causer?->id,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta === [] ? null : $meta,
            'occurred_at' => now(),
        ]);
    }
}
