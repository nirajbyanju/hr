<?php

namespace App\Modules\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestTaskTransferRequest extends FormRequest
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
            'task_assignment_id' => ['required', 'integer', 'exists:task_assignments,id'],
            'to_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
