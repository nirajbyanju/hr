<?php

namespace App\Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:3000'],
            'parent_comment_id' => ['nullable', 'integer', 'exists:task_comments,id'],
            'mention_employee_ids' => ['nullable', 'array'],
            'mention_employee_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }
}
