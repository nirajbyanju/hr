<?php

namespace App\Modules\Holidays\Http\Requests;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
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
        /** @var Holiday $holiday */
        $holiday = $this->route('holiday');

        return [
            'title' => ['required', 'string', 'max:255'],
            'holiday_date' => [
                'required',
                'date',
                Rule::unique(Holiday::class, 'holiday_date')
                    ->ignore($holiday->id)
                    ->where(fn ($query) => $query->where('title', (string) $this->input('title'))),
            ],
            'holiday_type' => ['required', 'string', 'max:50'],
            'is_optional' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
