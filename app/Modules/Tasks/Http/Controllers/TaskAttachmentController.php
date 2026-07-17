<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Modules\Tasks\Http\Requests\StoreTaskAttachmentRequest;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Modules\Tasks\Services\TaskAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAttachmentController extends Controller
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly TaskAttachmentService $taskAttachmentService,
    ) {
    }

    public function store(StoreTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        abort_if(! $this->taskRepository->canAccess($task, $request->user()), 403);

        $comment = null;
        if ($request->filled('task_comment_id')) {
            $comment = TaskComment::query()->where('task_id', $task->id)->find($request->integer('task_comment_id'));
        }

        $this->taskAttachmentService->upload($task, $request->file('file'), $comment, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Attachment uploaded successfully.'));
    }

    public function download(Request $request, TaskAttachment $attachment): BinaryFileResponse
    {
        $attachment->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($attachment->task, $request->user()), 403);
        abort_unless(file_exists(public_path($attachment->file_path)), 404);

        return response()->download(public_path($attachment->file_path), $attachment->title ?? basename($attachment->file_path));
    }

    public function preview(Request $request, TaskAttachment $attachment): Response|BinaryFileResponse
    {
        $attachment->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($attachment->task, $request->user()), 403);
        abort_unless($attachment->isPreviewable() && file_exists(public_path($attachment->file_path)), 404);

        return response()->file(public_path($attachment->file_path));
    }

    public function destroy(Request $request, TaskAttachment $attachment): RedirectResponse
    {
        $attachment->loadMissing('task');
        abort_if(! $this->taskRepository->canAccess($attachment->task, $request->user()), 403);
        abort_unless($request->user()?->hasPermission('task_attachment.delete') || $request->user()?->hasAnyPermission(['task.delete', 'task.assign-team']), 403);

        $taskId = $attachment->task_id;
        $this->taskAttachmentService->delete($attachment, $request->user());

        return redirect()->route('tasks.show', $taskId)->with('success', __('Attachment removed successfully.'));
    }
}
