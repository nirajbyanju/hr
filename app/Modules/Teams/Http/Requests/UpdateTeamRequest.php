<?php

namespace App\Modules\Teams\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
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
        /** @var Team $team */
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team->id)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('teams', 'code')->ignore($team->id)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lead_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', Rule::in(['0', '1', 0, 1, true, false])],
        ];
    }
}
