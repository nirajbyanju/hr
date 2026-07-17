<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskChecklistService
{
    public function __construct(
        private readonly TaskWorkflowService $workflowService,
        private readonly TaskActivityLogger $activityLogger,
    ) {
    }

    public function createChecklist(Task $task, string $title, User $actor): TaskChecklist
    {
        return DB::transaction(function () use ($task, $title, $actor): TaskChecklist {
            $sortOrder = ((int) $task->checklists()->max('sort_order')) + 1;

            return $task->checklists()->create([
                'title' => $title !== '' ? $title : 'Checklist',
                'sort_order' => $sortOrder,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function deleteChecklist(TaskChecklist $checklist, User $actor): void
    {
        DB::transaction(function () use ($checklist, $actor): void {
            $task = $checklist->task;
            $checklist->items()->update(['deleted_by' => $actor->id]);
            $checklist->items()->delete();
            $checklist->update(['deleted_by' => $actor->id]);
            $checklist->delete();

            $this->workflowService->recomputeTaskProgress($task);
        });
    }

    public function addItem(TaskChecklist $checklist, string $title, User $actor): TaskChecklistItem
    {
        return DB::transaction(function () use ($checklist, $title, $actor): TaskChecklistItem {
            $sortOrder = ((int) $checklist->items()->max('sort_order')) + 1;

            $item = $checklist->items()->create([
                'title' => $title,
                'sort_order' => $sortOrder,
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->log(
                $checklist->task,
                'checklist_item_added',
                sprintf('%s added checklist item "%s"', $actor->name, $title),
                $actor,
                $item,
            );

            $this->workflowService->recomputeTaskProgress($checklist->task);

            return $item;
        });
    }

    public function toggleItem(TaskChecklistItem $item, User $actor): TaskChecklistItem
    {
        return DB::transaction(function () use ($item, $actor): TaskChecklistItem {
            $checked = ! $item->is_checked;

            $item->update([
                'is_checked' => $checked,
                'checked_by' => $checked ? $actor->id : null,
                'checked_at' => $checked ? now() : null,
                'updated_by' => $actor->id,
            ]);

            $task = $item->checklist->task;

            $this->activityLogger->log(
                $task,
                'checklist_item_checked',
                sprintf('%s marked checklist item "%s" as %s', $actor->name, $item->title, $checked ? 'done' : 'not done'),
                $actor,
                $item,
            );

            $this->workflowService->recomputeTaskProgress($task);

            return $item->fresh() ?? $item;
        });
    }

    public function deleteItem(TaskChecklistItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $task = $item->checklist->task;
            $item->update(['deleted_by' => $actor->id]);
            $item->delete();

            $this->workflowService->recomputeTaskProgress($task);
        });
    }
}
