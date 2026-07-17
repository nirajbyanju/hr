<?php

namespace App\Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAttachmentRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,gif,webp,zip,mp4,webm,mov',
            ],
            'task_comment_id' => ['nullable', 'integer', 'exists:task_comments,id'],
        ];
    }
}
