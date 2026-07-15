<?php

namespace App\Modules\Leaves\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeaveBalance;
use App\Models\User;
use App\Modules\Leaves\Http\Requests\SyncLeaveBalancesRequest;
use App\Modules\Leaves\Http\Requests\UpdateLeaveBalanceRequest;
use App\Modules\Leaves\Repositories\LeaveRepository;
use App\Modules\Leaves\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function __construct(
        private readonly LeaveRepository $leaveRepository,
        private readonly LeaveService $leaveService
    ) {
    }
    // The index method displays a paginated list of leave balances based on the provided filters and the user's access level. It first checks the user's permissions to determine if they can manage balances and if they have all-access permissions. Depending on the user's access level, it scopes the employee IDs accordingly. The method then retrieves the filtered leave balances, along with related data such as employees, salary grades, and leave categories, and passes this data to the view for rendering. The view will display the leave balances in a table format, allowing users to see the relevant information based on their permissions and filters.
    public function index(Request $request): View
    {
        $user = $request->user();
        $canManageBalances = $user->hasAnyPermission(['leave.manage-balances', 'leave.manage-quotas']);
        $hasAllAccess = $this->hasAllAccess($user);
        $scopedEmployeeIds = $hasAllAccess ? null : $this->scopedEmployeeIds($user);

        // Validate and sanitize filters
        $filters = [
            'year' => (int) $request->input('year', (int) now()->year),
            'employee_id' => (int) $request->input('employee_id', 0),
            'salary_grade_id' => (int) $request->input('salary_grade_id', 0),
            'leave_category_id' => (int) $request->input('leave_category_id', 0),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        if ($scopedEmployeeIds !== null && $filters['employee_id'] > 0 && ! in_array($filters['employee_id'], $scopedEmployeeIds, true)) {
            $filters['employee_id'] = 0;
        }
        return view('hr.leaves.balances.index', [
            'balances' => $this->leaveRepository->paginateBalances($filters, $scopedEmployeeIds),
            'employees' => $this->leaveRepository->listEmployeesForScope($scopedEmployeeIds),
            'salaryGrades' => $this->leaveRepository->listActiveSalaryGrades(),
            'leaveCategories' => $this->leaveRepository->listActiveCategories(),
            'filters' => $filters,
            'canManageBalances' => $canManageBalances,
            'hasAllAccess' => $hasAllAccess,
            'isSelfView' => $scopedEmployeeIds !== null
                && count($scopedEmployeeIds) === 1
                && (int) ($user->employee?->id ?? 0) === (int) ($scopedEmployeeIds[0] ?? 0),
            'currentEmployeeId' => (int) ($user->employee?->id ?? 0),
        ]);
    }

    // The sync method handles the synchronization of leave balances for a specified year based on the provided filters. It first checks if the user has the necessary permissions to manage balances and if they have all-access permissions. If the user does not have the required permissions, it redirects back to the index route with an error message. If the user has the appropriate access, it validates the request data and calls the leave service to perform the synchronization of leave balances for the specified year, employee ID, and salary grade ID. After the synchronization process is complete, it redirects back to the index route with a success message indicating how many rows were processed during the synchronization.
    public function sync(SyncLeaveBalancesRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $this->hasAllAccess($user)) {
            return redirect()->route('leave-balances.index')->withErrors(['year' => 'You are not allowed to run balance sync.']);
        }

        $validated = $request->validated();
        $count = $this->leaveService->syncBalancesForYear((int) $validated['year'], [
            'employee_id' => (int) ($validated['employee_id'] ?? 0),
            'salary_grade_id' => (int) ($validated['salary_grade_id'] ?? 0),
        ]);

        return redirect()->route('leave-balances.index', ['year' => (int) $validated['year']])
            ->with('success', "Leave balances synced successfully. {$count} row(s) processed.");
    }

    // The edit method displays the form for editing a specific leave balance. It accepts an EmployeeLeaveBalance instance as a parameter, which is automatically resolved by Laravel's route model binding. The method loads the related employee, salary grade, leave category, and leave policy data to provide context for the balance being edited. It then returns the view for editing the leave balance, passing the loaded balance data to the view for rendering. The view will allow users to modify the leave balance details and submit the changes for updating.
    public function edit(EmployeeLeaveBalance $leaveBalance): View
    {
        abort_if(! $this->canAccessBalance(request()->user(), $leaveBalance), 403);

        return view('hr.leaves.balances.edit', [
            'leaveBalance' => $leaveBalance->load([
                'employee:id,first_name,last_name,employee_code',
                'employee.salaryGrade:id,grade_name,grade_code',
                'leaveCategory:id,name,code',
                'leavePolicy:id,effective_from_year,effective_to_year,days_allocated,is_earned_leave,earned_credit_frequency,earned_credit_days',
            ]),
        ]);
    }


    // The update method processes the form submission for updating a specific leave balance. It accepts an UpdateLeaveBalanceRequest instance, which contains the validated data from the form, and an EmployeeLeaveBalance instance that represents the balance to be updated. The method first checks if the user has the necessary permissions to manage balances and if they have all-access permissions. If the user does not have the required permissions, it redirects back to the index route with an error message. If the user has the appropriate access, it calls the leave service to update the balance with the validated data from the request. After successfully updating the balance, it redirects back to the index route for the year of the updated balance with a success message indicating that the leave balance was updated successfully.
    public function update(UpdateLeaveBalanceRequest $request, EmployeeLeaveBalance $leaveBalance): RedirectResponse
    {
        abort_if(! $this->hasAllAccess($request->user()), 403);

        $this->leaveService->updateBalance($leaveBalance, $request->validated());
        return redirect()->route('leave-balances.index', ['year' => $leaveBalance->year])
            ->with('success', __('Leave balance updated successfully.'));
    }

    /**
     * @return array<int, int>|null
     * The scopedEmployeeIds method retrieves a list of employee IDs that are within the scope of the user's access. It first checks if the user has an associated employee record. If not, it returns an empty array, indicating that there are no employees within the user's scope. If the user does have an employee record, it collects the user's own employee ID and the IDs of their subordinates (retrieved using the subordinates relationship) into a single array. The method then returns a unique array of these IDs, which can be used to scope queries and ensure that users only access data related to themselves and their direct subordinates, in accordance with their permissions and role hierarchy.
     */
    private function scopedEmployeeIds(User $user): ?array
    {
        $employee = $user->employee;
        if (! $employee) {
            return [];
        }

        $ids = [$employee->id];

        $subordinateIds = $employee->subordinates()->pluck('id')->all();
        return array_values(array_unique(array_merge($ids, $subordinateIds)));
    }

    private function hasAllAccess(User $user): bool
    {
        return $user->hasAnyPermission(['leave.manage-quotas', 'leave.manage-balances']);
    }

    private function canAccessBalance(User $user, EmployeeLeaveBalance $leaveBalance): bool
    {
        if ($this->hasAllAccess($user)) {
            return true;
        }

        return in_array((int) $leaveBalance->employee_id, $this->scopedEmployeeIds($user) ?? [], true);
    }
}
