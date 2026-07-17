<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskCommentMention extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $guarded = [];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'task_comment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'mentioned_employee_id');
    }
}
