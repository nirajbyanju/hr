<?php

namespace App\Modules\Projects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncProjectMembersRequest extends FormRequest
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
            'members' => ['nullable', 'array'],
            'members.*.employee_id' => ['required', 'integer', 'exists:employees,id', 'distinct'],
            'members.*.project_role' => ['required', Rule::in(['manager', 'lead', 'member', 'observer'])],
            'members.*.is_billable' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
            'members.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
