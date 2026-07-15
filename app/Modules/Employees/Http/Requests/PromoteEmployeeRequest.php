<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromoteEmployeeRequest extends FormRequest
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
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'revised_salary' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
