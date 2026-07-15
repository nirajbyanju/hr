<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeStatusRequest extends FormRequest
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
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'on_leave', 'on_notice', 'resigned', 'terminated'])],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
