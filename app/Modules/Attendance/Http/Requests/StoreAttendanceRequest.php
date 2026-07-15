<?php

namespace App\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAttendanceRequest extends FormRequest
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
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'entry_type' => ['required', 'string', 'in:checkin,checkout'],
            'entry_time' => ['required', 'string', 'max:20'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance_date.before_or_equal' => 'Only today or previous dates are allowed for attendance.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $attendanceDate = (string) $this->input('attendance_date', '');
            $entryTime = trim((string) $this->input('entry_time', ''));

            if ($attendanceDate === '' || $entryTime === '') {
                return;
            }

            $formats = ['Y-m-d H:i', 'Y-m-d h:i A', 'Y-m-d h:i a'];
            foreach ($formats as $format) {
                try {
                    Carbon::createFromFormat($format, $attendanceDate . ' ' . $entryTime);
                    return;
                } catch (\Throwable) {
                    // Continue checking next accepted format.
                }
            }

            $validator->errors()->add('entry_time', 'Invalid time format. Use HH:mm or hh:mm AM/PM (example: 09:01 AM).');
        });
    }
}
