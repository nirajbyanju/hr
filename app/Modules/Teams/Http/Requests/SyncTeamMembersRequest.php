<?php

namespace App\Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTeamMembersRequest extends FormRequest
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
            'members.*.member_role' => ['required', Rule::in(['lead', 'member', 'observer'])],
            'members.*.joined_on' => ['nullable', 'date'],
            'members.*.left_on' => ['nullable', 'date', 'after_or_equal:members.*.joined_on'],
            'members.*.is_active' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
        ];
    }
}
