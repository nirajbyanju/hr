<?php

namespace App\Modules\Employees\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessEmployeeResignationSupervisorRequest extends FormRequest
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
            'remarks' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
        ];
    }
}
