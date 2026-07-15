<?php

namespace App\Modules\Employees\Repositories;

use App\Models\EmployeeProfileUpdateRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EmployeeProfileUpdateRequestRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['approval_status'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return EmployeeProfileUpdateRequest::query()
            ->with([
                'employee:id,employee_code,first_name,last_name',
                'submittedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->when($status !== '', fn (Builder $query) => $query->where('approval_status', $status))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->whereHas('employee', function (Builder $employeeQuery) use ($q): void {
                    $employeeQuery
                        ->where('employee_code', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%");
                });
            })
            ->orderByRaw("CASE WHEN approval_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForReview(int $id): ?EmployeeProfileUpdateRequest
    {
        return EmployeeProfileUpdateRequest::query()
            ->with([
                'employee:id,employee_code,first_name,last_name,user_id,gender,date_of_birth,nid_number,passport_number,tax_id,phone,alternate_phone,marital_status,notes,avatar_path,work_email,department_id,designation_id,salary_grade_id,reports_to_id',
                'employee.user:id,email',
                'employee.department:id,name',
                'employee.designation:id,name',
                'employee.salaryGrade:id,grade_name,grade_code',
                'employee.manager:id,employee_code,first_name,last_name',
                'employee.addresses',
                'employee.bankAccounts',
                'employee.emergencyContacts',
                'employee.documents',
                'submittedBy:id,name,email',
                'reviewedBy:id,name,email',
                'resubmissionOf:id,approval_status,review_comments,created_at',
            ])
            ->find($id);
    }

    public function latestPendingForEmployee(int $employeeId): ?EmployeeProfileUpdateRequest
    {
        return EmployeeProfileUpdateRequest::query()
            ->where('employee_id', $employeeId)
            ->where('approval_status', 'pending')
            ->whereNull('reviewed_at')
            ->latest('id')
            ->first();
    }

    public function latestRejectedForEmployee(int $employeeId): ?EmployeeProfileUpdateRequest
    {
        return EmployeeProfileUpdateRequest::query()
            ->where('employee_id', $employeeId)
            ->where('approval_status', 'rejected')
            ->latest('id')
            ->first();
    }

    public function latestReviewedForEmployee(int $employeeId): ?EmployeeProfileUpdateRequest
    {
        return EmployeeProfileUpdateRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('approval_status', ['approved', 'rejected'])
            ->whereNotNull('reviewed_at')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function create(array $attributes): EmployeeProfileUpdateRequest
    {
        return EmployeeProfileUpdateRequest::query()->create($attributes);
    }

    public function update(EmployeeProfileUpdateRequest $request, array $attributes): void
    {
        $request->update($attributes);
    }
}
