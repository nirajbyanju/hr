<?php

namespace App\Modules\Leaves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveBalanceRequest extends FormRequest
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
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:9999'],
            'allocated' => ['required', 'numeric', 'min:0', 'max:9999'],
            'carried_forward' => ['required', 'numeric', 'min:0', 'max:9999'],
            'earned_credited' => ['required', 'numeric', 'min:0', 'max:9999'],
            'availed' => ['required', 'numeric', 'min:0', 'max:9999'],
            'adjustments' => ['required', 'numeric', 'min:-9999', 'max:9999'],
        ];
    }
}
