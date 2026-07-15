<?php

namespace App\Modules\Leaves\Http\Requests;

use App\Models\LeaveCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', Rule::unique(LeaveCategory::class, 'name')],
            'code' => ['required', 'string', 'max:30', Rule::unique(LeaveCategory::class, 'code')],
            'is_paid' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
