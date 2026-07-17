<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskCommentService
{
    public function __construct(private readonly TaskActivityLogger $activityLogger)
    {
    }

    /** @param array<int, int> $mentionEmployeeIds */
    public function create(Task $task, ?int $employeeId, string $comment, ?int $parentCommentId, array $mentionEmployeeIds, User $actor): TaskComment
    {
        return DB::transaction(function () use ($task, $employeeId, $comment, $parentCommentId, $mentionEmployeeIds, $actor): TaskComment {
            $taskComment = $task->comments()->create([
                'parent_comment_id' => $parentCommentId,
                'employee_id' => $employeeId,
                'comment' => $comment,
                'created_by' => $actor->id,
            ]);

            foreach (array_unique($mentionEmployeeIds) as $mentionedEmployeeId) {
                $taskComment->mentions()->create(['mentioned_employee_id' => $mentionedEmployeeId]);
            }

            $this->activityLogger->log(
                $task,
                'comment_added',
                sprintf('%s commented on the task', $actor->name),
                $actor,
                $taskComment,
            );

            return $taskComment;
        });
    }

    public function update(TaskComment $comment, string $newComment, User $actor): TaskComment
    {
        $this->guardOwnership($comment, $actor);

        return DB::transaction(function () use ($comment, $newComment, $actor): TaskComment {
            $comment->update([
                'comment' => $newComment,
                'edited_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $comment->fresh() ?? $comment;
        });
    }

    public function delete(TaskComment $comment, User $actor): void
    {
        $this->guardOwnership($comment, $actor);

        DB::transaction(function () use ($comment, $actor): void {
            $comment->update(['deleted_by' => $actor->id]);
            $comment->delete();
        });
    }

    private function guardOwnership(TaskComment $comment, User $actor): void
    {
        $isOwnComment = (int) ($actor->employee?->id ?? 0) === (int) $comment->employee_id;
        $isAdminTier = $actor->hasAnyPermission(['task.delete', 'task.assign-team']);

        if (! $isOwnComment && ! $isAdminTier) {
            throw ValidationException::withMessages(['comment' => 'You can only edit or delete your own comments.']);
        }
    }
}
