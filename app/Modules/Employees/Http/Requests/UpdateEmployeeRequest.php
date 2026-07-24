<?php

namespace App\Modules\Employees\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique(Employee::class, 'user_id')->ignore($employee->id)],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'employee_code')->ignore($employee->id)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'nid_number' => ['nullable', 'string', 'max:64', Rule::unique(Employee::class, 'nid_number')->ignore($employee->id)],
            'passport_number' => ['nullable', 'string', 'max:64', Rule::unique(Employee::class, 'passport_number')->ignore($employee->id)],
            'tax_id' => ['nullable', 'string', 'max:64', Rule::unique(Employee::class, 'tax_id')->ignore($employee->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique(Employee::class, 'work_email')->ignore($employee->id)],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'date_of_joining' => ['required', 'date'],

            
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:date_of_joining'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'on_leave', 'on_notice', 'resigned', 'terminated'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
             'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'salary_grade_id' => ['nullable', 'integer', 'exists:salary_grades,id'],
            // Drive this employee's attendance off their own schedule; left
            // blank they fall back to the company-wide work window in Settings.
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'attendance_policy_id' => ['nullable', 'integer', 'exists:attendance_policies,id'],
            'reports_to_id' => ['nullable', 'integer', 'exists:employees,id', Rule::notIn([$employee->id])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
