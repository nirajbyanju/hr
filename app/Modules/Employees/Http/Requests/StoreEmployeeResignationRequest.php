<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeResignationRequest extends FormRequest
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
            'notice_date' => ['nullable', 'date'],
            'requested_last_working_day' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:3000'],
            'handover_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
