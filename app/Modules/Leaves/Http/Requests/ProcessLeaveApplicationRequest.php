<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessLeaveApplicationRequest extends FormRequest
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
            'approval_remarks' => ['nullable', 'string', 'max:2000', 'required_if:action,reject'],
            // Whether this is actually required depends on runtime state (is HR bypassing a
            // pending supervisor sign-off?), which a static rule can't express — enforced in
            // LeaveApplicationController::process().
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
