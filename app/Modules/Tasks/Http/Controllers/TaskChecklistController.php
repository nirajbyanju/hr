<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Modules\Tasks\Http\Requests\StoreTaskChecklistItemRequest;
use App\Modules\Tasks\Http\Requests\StoreTaskChecklistRequest;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskChecklistService $taskChecklistService,
    ) {
    }

    public function store(StoreTaskChecklistRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $this->taskChecklistService->createChecklist($task, (string) $request->validated()['title'], $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Checklist added successfully.'));
    }

    public function destroy(Request $request, TaskChecklist $checklist): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($checklist->task, $request->user()), 403);

        $taskId = $checklist->task_id;
        $this->taskChecklistService->deleteChecklist($checklist, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Checklist removed successfully.'));
    }

    public function storeItem(StoreTaskChecklistItemRequest $request, TaskChecklist $checklist): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($checklist->task, $request->user()), 403);

        $this->taskChecklistService->addItem($checklist, (string) $request->validated()['title'], $request->user());

        return redirect()->route('tasks.show', $checklist->task_id)->with('success', __('Checklist item added successfully.'));
    }

    public function toggleItem(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $item->loadMissing('checklist.task');
        abort_if(! $this->taskRepository->canAccess($item->checklist->task, $request->user()), 403);

        $this->taskChecklistService->toggleItem($item, $request->user());

        return redirect()->route('tasks.show', $item->checklist->task_id)->with('success', __('Checklist item updated successfully.'));
    }

    public function destroyItem(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $item->loadMissing('checklist.task');
        abort_if(! $this->taskRepository->canAccess($item->checklist->task, $request->user()), 403);

        $taskId = $item->checklist->task_id;
        $this->taskChecklistService->deleteItem($item, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Checklist item removed successfully.'));
    }
}
