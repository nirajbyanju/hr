<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    private const PREVIEWABLE_IMAGE = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    private const PREVIEWABLE_VIDEO = ['mp4', 'webm', 'ogg'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPreviewableImage(): bool
    {
        return in_array(strtolower((string) $this->file_extension), self::PREVIEWABLE_IMAGE, true);
    }

    public function isPreviewableVideo(): bool
    {
        return in_array(strtolower((string) $this->file_extension), self::PREVIEWABLE_VIDEO, true);
    }

    public function isPreviewablePdf(): bool
    {
        return strtolower((string) $this->file_extension) === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isPreviewableImage() || $this->isPreviewableVideo() || $this->isPreviewablePdf();
    }

    public function humanFileSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1) . ' ' . $units[$power];
    }
}
