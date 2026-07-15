<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejoinEmployeeRequest extends FormRequest
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
            'rejoin_date' => ['required', 'date'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'reports_to_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'max:255'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
