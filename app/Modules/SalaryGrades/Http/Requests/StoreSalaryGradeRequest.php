<?php

namespace App\Modules\SalaryGrades\Http\Requests;

use App\Models\SalaryGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryGradeRequest extends FormRequest
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
        $bandName = $this->input('band_name');

        return [
            'grade_name' => [
                'required',
                'string',
                'max:60',
                Rule::unique(SalaryGrade::class, 'grade_name')->where(fn ($query) => $query->where('band_name', $bandName)),
            ],
            'grade_code' => ['required', 'string', 'max:30', Rule::unique(SalaryGrade::class, 'grade_code')],
            'band_name' => ['nullable', 'string', 'max:60'],
            'min_salary' => ['nullable', 'numeric', 'min:0'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
