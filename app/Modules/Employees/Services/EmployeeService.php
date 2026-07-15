<?php

namespace App\Modules\Employees\Services;

use App\Models\Employee;
use App\Models\SystemSetting;
use App\Modules\Employees\Repositories\EmployeeRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly EmployeeAssetService $employeeAssetService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createEmployee(array $payload, ?UploadedFile $avatarFile = null): Employee
    {
        $newAvatarPath = $this->employeeAssetService->storeAvatar($avatarFile);

        try {
            return DB::transaction(function () use ($payload, $newAvatarPath): Employee {
                $attributes = $this->mapPayloadToAttributes($payload);
                $attributes['employee_code'] = $payload['employee_code'] ?: $this->generateEmployeeCode();
                $attributes['avatar_path'] = $newAvatarPath;

                return $this->employeeRepository->create($attributes);
            });
        } catch (\Throwable $exception) {
            if ($newAvatarPath !== null) {
                $this->employeeAssetService->deleteAvatar($newAvatarPath);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateEmployee(Employee $employee, array $payload, ?UploadedFile $avatarFile = null): Employee
    {
        $newAvatarPath = $this->employeeAssetService->storeAvatar($avatarFile);
        $oldAvatarPath = $employee->avatar_path;

        try {
            $updatedEmployee = DB::transaction(function () use ($employee, $payload, $newAvatarPath): Employee {
                $attributes = $this->mapPayloadToAttributes($payload);
                $attributes['employee_code'] = $payload['employee_code'] ?: $employee->employee_code;

                if ($newAvatarPath !== null) {
                    $attributes['avatar_path'] = $newAvatarPath;
                } elseif (! empty($payload['remove_avatar'])) {
                    $attributes['avatar_path'] = null;
                }

                $this->employeeRepository->update($employee, $attributes);

                return $employee->fresh() ?? $employee;
            });

            if ($newAvatarPath !== null || ! empty($payload['remove_avatar'])) {
                $this->employeeAssetService->deleteAvatar($oldAvatarPath);
            }

            return $updatedEmployee;
        } catch (\Throwable $exception) {
            if ($newAvatarPath !== null) {
                $this->employeeAssetService->deleteAvatar($newAvatarPath);
            }

            throw $exception;
        }
    }

    public function deleteEmployee(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $this->employeeRepository->delete($employee);
        });

        $this->employeeAssetService->deleteAvatar($employee->avatar_path);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mapPayloadToAttributes(array $payload): array
    {
        return [
            'user_id' => $payload['user_id'] ?? null,
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'] ?? null,
            'gender' => $payload['gender'] ?? null,
            'date_of_birth' => $payload['date_of_birth'] ?? null,
            'blood_group' => $payload['blood_group'] ?? null,
            'nid_number' => $payload['nid_number'] ?? null,
            'passport_number' => $payload['passport_number'] ?? null,
            'tax_id' => $payload['tax_id'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'alternate_phone' => $payload['alternate_phone'] ?? null,
            'work_email' => $payload['work_email'] ?? null,
            'personal_email' => $payload['personal_email'] ?? null,
            'marital_status' => $payload['marital_status'] ?? null,
            'date_of_joining' => $payload['date_of_joining'],
            'probation_end_date' => $payload['probation_end_date'] ?? null,
            'termination_date' => $payload['termination_date'] ?? null,
            'employment_type' => $payload['employment_type'],
            'employment_status' => $payload['employment_status'],
            'department_id' => $payload['department_id'] ?? null,
            'designation_id' => $payload['designation_id'] ?? null,
            'salary_grade_id' => $payload['salary_grade_id'] ?? null,
            'reports_to_id' => $payload['reports_to_id'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function generateEmployeeCode(): string
    {
        $prefix = SystemSetting::getValue('employee_code_prefix') ?: 'EMP';
        $prefix = strtoupper(trim($prefix));
        $next = $this->employeeRepository->nextSequenceNumber();

        $attempt = 0;
        do {
            $candidate = sprintf('%s-%05d', $prefix, $next + $attempt);
            $attempt++;
        } while ($this->employeeRepository->existsByCode($candidate) && $attempt < 1000);

        return $candidate;
    }
}
