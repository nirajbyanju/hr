<?php

namespace App\Modules\SalaryGrades\Http\Requests;

use App\Models\SalaryGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryGradeRequest extends FormRequest
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
        /** @var SalaryGrade $salaryGrade */
        $salaryGrade = $this->route('salaryGrade');
        $bandName = $this->input('band_name');

        return [
            'grade_name' => [
                'required',
                'string',
                'max:60',
                Rule::unique(SalaryGrade::class, 'grade_name')
                    ->ignore($salaryGrade->id)
                    ->where(fn ($query) => $query->where('band_name', $bandName)),
            ],
            'grade_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique(SalaryGrade::class, 'grade_code')->ignore($salaryGrade->id),
            ],
            'band_name' => ['nullable', 'string', 'max:60'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
