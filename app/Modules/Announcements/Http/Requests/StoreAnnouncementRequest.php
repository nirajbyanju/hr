<?php

namespace App\Modules\Announcements\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
            'announcement_type' => ['required', Rule::in(['notice', 'announcement'])],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience_type' => ['required', Rule::in(['all', 'employees'])],
            'audience_employee_ids' => [Rule::requiredIf($this->input('audience_type') === 'employees'), 'array'],
            'audience_employee_ids.*' => ['integer', Rule::exists(Employee::class, 'id')],
            'priority' => ['required', Rule::in(['normal', 'high'])],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'is_pinned' => ['required', 'boolean'],
        ];
    }
}
