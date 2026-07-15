<?php

namespace App\Modules\Projects\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'member_ids' => $this->input('member_ids', []),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'project_code' => ['required', 'string', 'max:50', Rule::unique('projects', 'project_code')->ignore($project->id)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'manager_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'on_hold', 'completed', 'cancelled'])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
