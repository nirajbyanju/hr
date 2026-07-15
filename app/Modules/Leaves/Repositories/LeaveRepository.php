<?php

namespace App\Modules\Leaves\Repositories;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveCategory;
use App\Models\LeavePolicy;
use App\Models\SalaryGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LeaveRepository
{
    /**
     * @param array<string, mixed> $filters
     * paginate the leave categories based on optional search query, status filter, and pagination settings. The method allows filtering categories by name, code, or description using a search query, and also by their active/inactive status. The results are paginated according to the specified number of items per page, with a default of 20 and a maximum of 100. The paginated result includes counts of related policies and applications for each category.
     */
    public function paginateCategories(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return LeaveCategory::query()
            ->withCount(['policies', 'applications'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
                ->orderBy('name')
                ->paginate($perPage)
                ->withQueryString();
    }

    /**
     * @param array<string, mixed> $attributes
     * create a new leave category with the provided attributes. The method accepts an array of attributes that define the properties of the leave category, such as its name, code, whether it's paid, if it requires attachments, maximum consecutive days allowed, description, and active status. It then creates a new record in the database using these attributes and returns the created LeaveCategory instance.
     */
    public function createCategory(array $attributes): LeaveCategory
    {
        return LeaveCategory::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * update an existing leave category with the provided attributes. The method takes a LeaveCategory instance that represents the category to be updated and an array of attributes containing the new values for the category's properties. It updates the category in the database with these new attributes. The method does not return any value, as it directly modifies the provided LeaveCategory instance.
     */
    public function updateCategory(LeaveCategory $leaveCategory, array $attributes): void
    {
        $leaveCategory->update($attributes);
    }

    // delete a leave category from the database. The method accepts a LeaveCategory instance that represents the category to be deleted. It calls the delete method on this instance, which removes the corresponding record from the database. The method does not return any value, as it performs a direct deletion operation on the provided LeaveCategory instance.
    public function deleteCategory(LeaveCategory $leaveCategory): void
    {
        $leaveCategory->delete();
    }

    /**
     * @return Collection<int, LeaveCategory>
     * retrieve a collection of active leave categories from the database. The method queries the LeaveCategory model to find all categories where the 'is_active' attribute is set to true. The results are ordered alphabetically by the 'name' attribute before being returned as a collection of LeaveCategory instances. This collection can be used to populate dropdowns or lists where only active leave categories should be displayed.
     */
    public function listActiveCategories(): Collection
    {
        return LeaveCategory::query()
            ->where('is_active', true)
                ->orderBy('name')
                    ->get();
    }

    /**
     * @return Collection<int, SalaryGrade>
     * retrieve a collection of active salary grades from the database. The method queries the SalaryGrade model to find all salary grades where the 'is_active' attribute is set to true. The results are ordered alphabetically by the 'grade_name' attribute before being returned as a collection of SalaryGrade instances. This collection can be used to populate dropdowns or lists where only active salary grades should be displayed, such as when assigning leave policies to specific salary grades.
     */
    public function listActiveSalaryGrades(): Collection
    {
        return SalaryGrade::query()
            ->where('is_active', true)
                ->orderBy('grade_name')
                ->get();
    }

    /**
     * @param array<string, mixed> $filters
     * paginate the leave policies based on optional filters such as effective year, salary grade, leave category, and status. The method allows filtering policies by their effective year, associated salary grade, linked leave category, and whether they are active or inactive. The results are paginated according to the specified number of items per page, with a default of 20 and a maximum of 100. Each policy in the paginated result includes related information about its leave category and salary grade for easier reference.
     */
    public function paginatePolicies(array $filters): LengthAwarePaginator
    {
        $year = (int) ($filters['year'] ?? 0);
        $salaryGradeId = (int) ($filters['salary_grade_id'] ?? 0);
        $leaveCategoryId = (int) ($filters['leave_category_id'] ?? 0);
        $status = (string) ($filters['status'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return LeavePolicy::query()
            ->with(['leaveCategory:id,name,code', 'salaryGrade:id,grade_name,grade_code'])
            ->when($year > 0, function ($query) use ($year): void {
                $query->where('effective_from_year', '<=', $year)
                    ->where(function ($inner) use ($year): void {
                    $inner->whereNull('effective_to_year')
                    ->orWhere('effective_to_year', '>=', $year);
                });
            })
            ->when($salaryGradeId > 0, fn ($query) => $query->where('salary_grade_id', $salaryGradeId))
                ->when($leaveCategoryId > 0, fn ($query) => $query->where('leave_category_id', $leaveCategoryId))
                ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('effective_from_year')
                ->orderBy('salary_grade_id')
                ->orderBy('leave_category_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $attributes
     * create a new leave policy with the provided attributes. The method accepts an array of attributes that define the properties of the leave policy, such as the associated salary grade, linked leave category, effective year range, allocated days, and active status. It then creates a new record in the database using these attributes and returns the created LeavePolicy instance. This allows for easy management of leave policies based on different salary grades and leave categories, as well as their effective periods.
     */
    public function createPolicy(array $attributes): LeavePolicy
    {
        return LeavePolicy::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * update an existing leave policy with the provided attributes. The method takes a LeavePolicy instance that represents the policy to be updated and an array of attributes containing the new values for the policy's properties. It updates the policy in the database with these new attributes. The method does not return any value, as it directly modifies the provided LeavePolicy instance.
     */
    public function updatePolicy(LeavePolicy $leavePolicy, array $attributes): void
    {
        $leavePolicy->update($attributes);
    }

    public function deletePolicy(LeavePolicy $leavePolicy): void
    {
        $leavePolicy->delete();
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, int>|null $scopedEmployeeIds
     * paginate the employee leave balances based on optional filters such as year, employee, salary grade, and leave category, with an additional option to scope the results to specific employee IDs. The method allows filtering balances by the year they apply to, the associated employee, the employee's salary grade, and the linked leave category. If a list of scoped employee IDs is provided, the results will be limited to those employees. The results are paginated according to the specified number of items per page, with a default of 20 and a maximum of 100. Each balance in the paginated result includes related information about the employee, their salary grade, the leave category, and any applicable leave policy for easier reference.
     */
    public function paginateBalances(array $filters, ?array $scopedEmployeeIds = null): LengthAwarePaginator
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $salaryGradeId = (int) ($filters['salary_grade_id'] ?? 0);
        $leaveCategoryId = (int) ($filters['leave_category_id'] ?? 0);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return EmployeeLeaveBalance::query()
            ->select('employee_leave_balances.*')
            ->join('employees', 'employees.id', '=', 'employee_leave_balances.employee_id')
            ->leftJoin('salary_grades', 'salary_grades.id', '=', 'employees.salary_grade_id')
            ->join('leave_categories', 'leave_categories.id', '=', 'employee_leave_balances.leave_category_id')
            ->with([
                'employee:id,first_name,last_name,employee_code,salary_grade_id',
                'employee.salaryGrade:id,grade_name,grade_code',
                'leaveCategory:id,name,code',
                'leavePolicy:id,leave_category_id,salary_grade_id,effective_from_year,effective_to_year',
            ])
            ->where('employee_leave_balances.year', $year)
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_leave_balances.employee_id', $scopedEmployeeIds))
            ->when($employeeId > 0, fn ($query) => $query->where('employee_leave_balances.employee_id', $employeeId))
            ->when($salaryGradeId > 0, fn ($query) => $query->where('employees.salary_grade_id', $salaryGradeId))
            ->when($leaveCategoryId > 0, fn ($query) => $query->where('employee_leave_balances.leave_category_id', $leaveCategoryId))
            ->orderByDesc('employee_leave_balances.year')
            ->orderBy('employees.first_name')
            ->orderBy('leave_categories.name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<int, int>|null $scopedEmployeeIds
     * @return Collection<int, Employee>
     * retrieve a collection of employees based on an optional list of scoped employee IDs. The method queries the Employee model to find employees whose IDs are in the provided list of scoped employee IDs, if such a list is given. If no list is provided, it retrieves all employees. The results include only the employee's ID, employee code, first name, last name, and salary grade ID for efficiency. The employees are ordered alphabetically by their first and last names before being returned as a collection of Employee instances. This collection can be used to populate dropdowns or lists where employees need to be selected, potentially filtered by specific scopes defined by the provided employee IDs.
     */
    public function listEmployeesForScope(?array $scopedEmployeeIds = null): Collection
    {
        return Employee::query()
            ->select(['id', 'employee_code', 'first_name', 'last_name', 'salary_grade_id'])
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('id', $scopedEmployeeIds))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * @return Collection<int, LeavePolicy>
     * retrieve a collection of active leave policies for a given salary grade and year. The method queries the LeavePolicy model to find policies that are associated with the specified salary grade ID and are active. It also checks that the policies are effective for the given year by ensuring that the effective_from_year is less than or equal to the year and that the effective_to_year is either null or greater than or equal to the year. The results are ordered by the leave_category_id attribute before being returned as a collection of LeavePolicy instances. This collection can be used to determine which leave policies apply to employees of a certain salary grade for a specific year, which is essential for calculating leave balances and entitlements.
     */
    public function listPoliciesForSalaryGradeAndYear(int $salaryGradeId, int $year): Collection
    {
        return LeavePolicy::query()
            ->where('salary_grade_id', $salaryGradeId)
            ->where('is_active', true)
            ->where('effective_from_year', '<=', $year)
            ->where(function ($query) use ($year): void {
                $query->whereNull('effective_to_year')
                    ->orWhere('effective_to_year', '>=', $year);
            })
            ->orderBy('leave_category_id')
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     * retrieve a collection of employees who are eligible for leave balance synchronization based on the specified year and optional filters for employee ID and salary grade ID. The method queries the Employee model to find active employees who have a non-null salary grade ID and whose date of joining is on or before December 31st of the specified year. It allows further filtering by a specific employee ID or salary grade ID if provided in the filters. The results are ordered alphabetically by the employee's first name before being returned as a collection of Employee instances. This collection can be used to identify which employees need their leave balances synchronized for a given year, ensuring that only relevant employees are included in the synchronization process.
     */
    public function listEmployeesForBalanceSync(int $year, array $filters): Collection
    {
        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $salaryGradeId = (int) ($filters['salary_grade_id'] ?? 0);

        return Employee::query()
            ->where('employment_status', 'active')
            ->whereNotNull('salary_grade_id')
            ->when($employeeId > 0, fn ($query) => $query->where('id', $employeeId))
            ->when($salaryGradeId > 0, fn ($query) => $query->where('salary_grade_id', $salaryGradeId))
            ->whereDate('date_of_joining', '<=', "{$year}-12-31")
            ->get();
    }
    // find the leave balance for a specific employee, leave category, and year. The method queries the EmployeeLeaveBalance model to find a record that matches the given employee ID, leave category ID, and year. If such a record exists, it returns an instance of EmployeeLeaveBalance representing the balance for that employee and leave category in the specified year. If no matching record is found, it returns null. This method is useful for retrieving an employee's leave balance for a particular category and year, which can be used in various operations such as displaying balances or performing calculations for leave applications.
    public function findBalanceForYear(int $employeeId, int $leaveCategoryId, int $year): ?EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_category_id', $leaveCategoryId)
            ->where('year', $year)
            ->first();
    }

    /**
     * @param array<string, mixed> $attributes
     * create or update the leave balance for a specific employee, leave category, and year. The method uses the updateOrCreate function to either find an existing EmployeeLeaveBalance record that matches the given employee ID, leave category ID, and year, or create a new one if it doesn't exist. The $attributes array contains the values to be set on the balance record, such as opening balance, allocated days, carried forward balance, earned/credited days, availed days, adjustments, and closing balance. The method returns the EmployeeLeaveBalance instance that was created or updated, allowing for further operations or reference to the updated balance.
     */
    public function upsertBalance(int $employeeId, int $leaveCategoryId, int $year, array $attributes): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_category_id' => $leaveCategoryId,
                'year' => $year,
            ],
            $attributes
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * update the leave balance with new attributes. The method takes an existing EmployeeLeaveBalance instance that represents the balance to be updated and an array of attributes containing the new values for the balance's properties. It updates the balance in the database with these new attributes. The method does not return any value, as it directly modifies the provided EmployeeLeaveBalance instance.
     */
    public function updateBalance(EmployeeLeaveBalance $balance, array $attributes): void
    {
    $balance->update($attributes);
    }
}
