<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncLeaveBalancesRequest extends FormRequest
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
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $employeeId = (int) $this->input('employee_id', 0);
        $salaryGradeId = (int) $this->input('salary_grade_id', 0);

        $this->merge([
            'employee_id' => $employeeId > 0 ? $employeeId : null,
            'salary_grade_id' => $salaryGradeId > 0 ? $salaryGradeId : null,
        ]);
    }
}
