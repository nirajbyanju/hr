<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveApplicationRequest extends FormRequest
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
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_half_day' => ['required', 'boolean'],
            'half_day_session' => ['nullable', 'in:first_half,second_half', 'required_if:is_half_day,1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'half_day_session.required_if' => 'Please select half-day session.',
        ];
    }
}
