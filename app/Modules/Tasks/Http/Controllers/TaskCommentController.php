<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskComment;
use App\Modules\Tasks\Http\Requests\UpdateTaskCommentRequest;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskCommentService $taskCommentService,
    ) {
    }

    public function update(UpdateTaskCommentRequest $request, TaskComment $comment): RedirectResponse
    {
        $comment->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($comment->task, $request->user()), 403);

        $this->taskCommentService->update($comment, (string) $request->validated()['comment'], $request->user());

        return redirect()->route('tasks.show', $comment->task_id)->with('success', __('Comment updated successfully.'));
    }

    public function destroy(Request $request, TaskComment $comment): RedirectResponse
    {
        $comment->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($comment->task, $request->user()), 403);

        $taskId = $comment->task_id;
        $this->taskCommentService->delete($comment, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Comment removed successfully.'));
    }
}
