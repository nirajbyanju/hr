<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessEmployeeResignationFinalRequest extends FormRequest
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
            'action' => ['required', 'in:approve,reject'],
            'final_last_working_day' => ['nullable', 'date', 'required_if:action,approve'],
            'remarks' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ];
    }
}
