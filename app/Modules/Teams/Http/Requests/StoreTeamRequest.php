<?php

namespace App\Modules\Teams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'code' => ['nullable', 'string', 'max:30', 'unique:teams,code'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lead_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
        ];
    }
}
