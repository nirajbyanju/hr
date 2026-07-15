<?php

namespace App\Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done', 'blocked', 'cancelled'])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
