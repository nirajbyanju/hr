<?php

namespace App\Modules\Tasks\Services;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class TaskAttachmentService
{
    public function __construct(private readonly TaskActivityLogger $activityLogger)
    {
    }

    public function upload(Task $task, UploadedFile $file, ?TaskComment $comment, User $actor): TaskAttachment
    {
        $uploadDir = public_path('assets/uploads/tasks/' . $task->id);
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        // Read every piece of metadata up front: relocating the file leaves the UploadedFile
        // still pointing at the vacated temp path, so any later stat call on it blows up.
        $extension = Str::lower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $size = $file->getSize();

        $filename = 'task_' . time() . '_' . Str::random(8) . '.' . $extension;
        $path = 'assets/uploads/tasks/' . $task->id . '/' . $filename;

        // Deliberately not UploadedFile::move(): it refuses the write whenever is_writable()
        // reports false, and that returns a false negative for OneDrive-synced directories
        // (writes into them actually succeed). rename() has no such gate.
        if (! File::move($file->getRealPath(), $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
            throw new RuntimeException('Unable to store the uploaded file at ' . $path . '.');
        }

        return DB::transaction(function () use ($task, $comment, $actor, $originalName, $mime, $size, $extension, $path): TaskAttachment {
            $attachment = $task->attachments()->create([
                'task_comment_id' => $comment?->id,
                'title' => $originalName,
                'file_path' => $path,
                'file_mime' => $mime,
                'file_extension' => $extension,
                'file_size' => $size,
                'uploaded_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $this->activityLogger->log(
                $task,
                'attachment_added',
                sprintf('%s uploaded "%s"', $actor->name, $attachment->title),
                $actor,
                $attachment,
            );

            return $attachment;
        });
    }

    public function delete(TaskAttachment $attachment, User $actor): void
    {
        DB::transaction(function () use ($attachment, $actor): void {
            $task = $attachment->task;

            if (str_starts_with((string) $attachment->file_path, 'assets/uploads/tasks/')) {
                File::delete(public_path($attachment->file_path));
            }

            $attachment->update(['deleted_by' => $actor->id]);
            $attachment->delete();

            $this->activityLogger->log(
                $task,
                'attachment_removed',
                sprintf('%s removed attachment "%s"', $actor->name, $attachment->title),
                $actor,
                $attachment,
            );
        });
    }
}
