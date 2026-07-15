<?php

namespace App\Modules\Leaves\Http\Requests;

use App\Models\LeavePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeavePolicyRequest extends FormRequest
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
            'leave_category_id' => ['required', 'integer', 'exists:leave_categories,id'],
            'salary_grade_id' => ['required', 'integer', 'exists:salary_grades,id'],
            'effective_from_year' => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique(LeavePolicy::class, 'effective_from_year')->where(
                    fn ($query) => $query
                        ->where('leave_category_id', (int) $this->input('leave_category_id'))
                        ->where('salary_grade_id', (int) $this->input('salary_grade_id'))
                ),
            ],
            'effective_to_year' => ['nullable', 'integer', 'min:2000', 'max:2100', 'gte:effective_from_year'],
            'days_allocated' => ['required', 'numeric', 'min:0', 'max:366'],
            'is_prorated' => ['required', 'boolean'],
            'carry_forward_mode' => ['required', 'in:none,limited,full'],
            'carry_forward_limit' => ['nullable', 'numeric', 'min:0', 'max:366', 'required_if:carry_forward_mode,limited'],
            'is_earned_leave' => ['required', 'boolean'],
            'earned_credit_frequency' => ['nullable', 'in:monthly,yearly', 'required_if:is_earned_leave,1'],
            'earned_credit_days' => ['nullable', 'numeric', 'min:0', 'max:31', 'required_if:is_earned_leave,1'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
