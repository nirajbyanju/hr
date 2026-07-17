<?php

namespace App\Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionTaskAssignmentRequest extends FormRequest
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
            'action' => ['required', Rule::in([
                'accept', 'reject', 'start', 'hold', 'resume', 'submit_review',
                'review_approve', 'review_reject', 'complete', 'close', 'reopen',
            ])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
