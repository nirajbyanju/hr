<?php

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeExitRecord;
use App\Models\EmployeeResignationRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\SalaryGrade;
use App\Models\SalaryRevisionHistory;
use App\Models\EmployeeStatusHistory;
use App\Models\User;
use App\Modules\Employees\Http\Requests\PromoteEmployeeRequest;
use App\Modules\Employees\Http\Requests\ProcessEmployeeResignationFinalRequest;
use App\Modules\Employees\Http\Requests\ProcessEmployeeResignationSupervisorRequest;
use App\Modules\Employees\Http\Requests\RejoinEmployeeRequest;
use App\Modules\Employees\Http\Requests\StoreEmployeeResignationRequest;
use App\Modules\Employees\Http\Requests\UpdateEmployeeStatusRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeResignationController extends Controller
{
    // Helper method to get IDs of employees who report directly to the given user
    public function applyIndex(Request $request): View
    {
        $user = $request->user();
        $employee = $request->user()?->employee;
        $visibleEmployeeIds = $this->visibleResignationEmployeeIds($user);

        $requests = EmployeeResignationRequest::query()
            ->with([
                'employee:id,employee_code,first_name,last_name,department_id',
                'supervisorEmployee:id,first_name,last_name,employee_code',
                'supervisorActionBy:id,name',
                'finalActionBy:id,name',
            ])
            ->whereIn('employee_id', $visibleEmployeeIds)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('hr.employees.resignations.apply', [
            'employee' => $employee,
            'requests' => $requests,
        ]);
    }

    // Helper method to get IDs of employees who report directly to the given user
    public function store(StoreEmployeeResignationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user?->employee;

        if (! $employee) {
        return redirect()->route('employee-resignations.index')->withErrors(['reason' => 'Your account is not linked to an employee profile.']);
        }

        if (! $employee->reports_to_id) {
        return redirect()->route('employee-resignations.index')->withErrors(['reason' => 'No supervisor is assigned to you. Resignation requires supervisor approval first.']);
        }

        $hasOpenRequest = EmployeeResignationRequest::query()
            ->where('employee_id', (int) $employee->id)
            ->whereIn('status', ['pending_supervisor', 'pending_final'])
            ->exists();

        if ($hasOpenRequest) {
            return redirect()->route('employee-resignations.index')->withErrors(['reason' => 'You already have an in-progress resignation request.']);
        }

        $validated = $request->validated();

        // Create the resignation request
        EmployeeResignationRequest::query()->create([
            'employee_id' => (int) $employee->id,
            'supervisor_employee_id' => (int) $employee->reports_to_id,
            'applied_by' => $user?->id,
            'notice_date' => $validated['notice_date'] ?? null,
            'requested_last_working_day' => (string) $validated['requested_last_working_day'],
            'reason' => (string) $validated['reason'],
            'handover_notes' => $validated['handover_notes'] ?? null,
            'status' => 'pending_supervisor',
        ]);

        return redirect()->route('employee-resignations.index')->with('success', __('Resignation request submitted. Waiting for supervisor approval.'));
    }

    public function supervisorApprovalsIndex(Request $request): View
    {
        $user = $request->user();
        $approvableEmployeeIds = $this->approvableResignationEmployeeIds($user);

        $filters = [
            'status' => (string) $request->input('status', 'pending_supervisor'),
            'employee_id' => (int) $request->input('employee_id', 0),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        if ($filters['employee_id'] > 0 && ! in_array($filters['employee_id'], $approvableEmployeeIds, true)) {
            $filters['employee_id'] = 0;
        }

        // Only fetch requests from employees inside the user's approval scope.
        $requests = EmployeeResignationRequest::query()
            ->with([
                'employee:id,employee_code,first_name,last_name,reports_to_id,employment_status',
                'supervisorEmployee:id,employee_code,first_name,last_name',
                'supervisorActionBy:id,name',
                'finalActionBy:id,name',
            ])
            ->whereIn('employee_id', $approvableEmployeeIds)
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['employee_id'] > 0, fn (Builder $q) => $q->where('employee_id', $filters['employee_id']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.employees.resignations.supervisor_approvals', [
            'requests' => $requests,
            'employees' => Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->whereIn('id', $approvableEmployeeIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    // Helper method to get IDs of employees who report directly to the given user
    public function processSupervisor(ProcessEmployeeResignationSupervisorRequest $request, EmployeeResignationRequest $resignationRequest): RedirectResponse
    {
        $user = $request->user();
        $approvableEmployeeIds = $this->approvableResignationEmployeeIds($user);

        if (! in_array((int) $resignationRequest->employee_id, $approvableEmployeeIds, true)) {
            return redirect()->route('employee-resignations.supervisor-approvals')->withErrors(['action' => 'You can only process requests inside your resignation approval scope.']);
        }

        if ($resignationRequest->status !== 'pending_supervisor') {
            return redirect()->route('employee-resignations.supervisor-approvals')->withErrors(['action' => 'Only pending supervisor requests can be processed here.']);
        }

        $validated = $request->validated();
        $action = (string) $validated['action'];

        DB::transaction(function () use ($resignationRequest, $user, $action, $validated): void {
            if ($action === 'approve') {
                $resignationRequest->update([
                    'status' => 'pending_final',
                    'supervisor_action_by' => $user?->id,
                    'supervisor_action_at' => now(),
                    'supervisor_remarks' => (string) ($validated['remarks'] ?? ''),
                ]);

                $employee = $resignationRequest->employee()->lockForUpdate()->first();
                if ($employee && $employee->employment_status !== 'on_notice') {
                    $previous = (string) $employee->employment_status;
                    $employee->update(['employment_status' => 'on_notice']);

                    EmployeeStatusHistory::query()->create([
                        'employee_id' => (int) $employee->id,
                        'status_from' => $previous,
                        'status_to' => 'on_notice',
                        'effective_date' => now()->toDateString(),
                        'reason' => 'Resignation in approval process',
                        'comments' => 'Supervisor approved resignation request #' . $resignationRequest->id,
                        'changed_by' => $user?->id,
                    ]);
                }

                return;
            }

            $resignationRequest->update([
                'status' => 'supervisor_rejected',
                'supervisor_action_by' => $user?->id,
                'supervisor_action_at' => now(),
                'supervisor_remarks' => (string) ($validated['remarks'] ?? ''),
            ]);
        });

        return redirect()->route('employee-resignations.supervisor-approvals')->with('success', __('Supervisor action completed.'));
    }

    public function finalApprovalsIndex(Request $request): View
    {
        $filters = [
            'status' => (string) $request->input('status', 'pending_final'),
            'employee_id' => (int) $request->input('employee_id', 0),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        $requests = EmployeeResignationRequest::query()
            ->with([
                'employee:id,employee_code,first_name,last_name,reports_to_id,employment_status',
                'supervisorEmployee:id,employee_code,first_name,last_name',
                'supervisorActionBy:id,name',
                'finalActionBy:id,name',
            ])
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['employee_id'] > 0, fn (Builder $q) => $q->where('employee_id', $filters['employee_id']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.employees.resignations.final_approvals', [
            'requests' => $requests,
            'employees' => Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function processFinal(ProcessEmployeeResignationFinalRequest $request, EmployeeResignationRequest $resignationRequest): RedirectResponse
    {
        if ($resignationRequest->status !== 'pending_final') {
            return redirect()->route('employee-resignations.final-approvals')->withErrors(['action' => 'Final approval is only allowed after supervisor approval.']);
        }

        $user = $request->user();
        $validated = $request->validated();
        $action = (string) $validated['action'];

        try {
            DB::transaction(function () use ($resignationRequest, $user, $validated, $action): void {
                if ($action === 'approve') {
                    $employee = $resignationRequest->employee()->lockForUpdate()->first();
                    if (! $employee) {
                        throw new \RuntimeException('Employee record not found for this resignation request.');
                    }

                    $finalDate = Carbon::parse((string) ($validated['final_last_working_day'] ?? $resignationRequest->requested_last_working_day))->toDateString();
                    $previousStatus = (string) $employee->employment_status;

                    $resignationRequest->update([
                        'status' => 'approved',
                        'final_action_by' => $user?->id,
                        'final_action_at' => now(),
                        'final_last_working_day' => $finalDate,
                        'final_remarks' => (string) ($validated['remarks'] ?? ''),
                    ]);

                    $employee->update([
                        'employment_status' => 'resigned',
                        'termination_date' => $finalDate,
                    ]);
                    $employee->user()?->update(['account_status' => 'inactive']);

                    EmployeeExitRecord::query()->create([
                        'employee_id' => (int) $employee->id,
                        'exit_type' => 'resignation',
                        'notice_date' => $resignationRequest->notice_date,
                        'last_working_day' => $finalDate,
                        'exit_date' => $finalDate,
                        'reason' => mb_substr((string) $resignationRequest->reason, 0, 255),
                        'remarks' => (string) ($resignationRequest->handover_notes ?? '') . "\nFinal remarks: " . (string) ($validated['remarks'] ?? ''),
                        'settlement_status' => 'pending',
                        'approved_by' => $user?->id,
                        'approved_at' => now(),
                    ]);

                    EmployeeStatusHistory::query()->create([
                        'employee_id' => (int) $employee->id,
                        'status_from' => $previousStatus,
                        'status_to' => 'resigned',
                        'effective_date' => $finalDate,
                        'reason' => 'Resignation approved',
                        'comments' => 'Final approval completed for request #' . $resignationRequest->id,
                        'changed_by' => $user?->id,
                    ]);

                    return;
                }

                $resignationRequest->update([
                    'status' => 'final_rejected',
                    'final_action_by' => $user?->id,
                    'final_action_at' => now(),
                    'final_remarks' => (string) ($validated['remarks'] ?? ''),
                ]);

                $employee = $resignationRequest->employee()->lockForUpdate()->first();
                if ($employee && $employee->employment_status === 'on_notice') {
                    $employee->update(['employment_status' => 'active']);

                    EmployeeStatusHistory::query()->create([
                        'employee_id' => (int) $employee->id,
                        'status_from' => 'on_notice',
                        'status_to' => 'active',
                        'effective_date' => now()->toDateString(),
                        'reason' => 'Final resignation rejected',
                        'comments' => 'Final rejection for request #' . $resignationRequest->id,
                        'changed_by' => $user?->id,
                    ]);
                }
            });
        } catch (\RuntimeException $exception) {
            return redirect()->route('employee-resignations.final-approvals')->withErrors(['action' => $exception->getMessage()]);
        }

        return redirect()->route('employee-resignations.final-approvals')->with('success', __('Final approval action completed.'));
    }

    public function statusIndex(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'employment_status' => (string) $request->input('employment_status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        $employees = Employee::query()
            ->with([
                'user:id,email',
                'manager:id,first_name,last_name',
                'department:id,name',
                'designation:id,name',
                'salaryGrade:id,grade_name',
            ])
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $search = $filters['q'];
                $q->where(function (Builder $inner) use ($search): void {
                    $inner->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('work_email', 'like', "%{$search}%")
                        ->orWhere('personal_email', 'like', "%{$search}%")
                        ->orWhere('blood_group', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['employment_status'] !== '', fn (Builder $q) => $q->where('employment_status', $filters['employment_status']))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.employees.statuses.index', [
            'employees' => $employees,
            'filters' => $filters,
            'statusOptions' => ['active', 'inactive', 'on_leave', 'on_notice', 'resigned', 'terminated'],
        ]);
    }

    public function updateStatus(UpdateEmployeeStatusRequest $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validated();
        $newStatus = (string) $validated['employment_status'];
        $effectiveDate = Carbon::parse((string) $validated['effective_date'])->toDateString();
        $oldStatus = (string) $employee->employment_status;

        DB::transaction(function () use ($request, $employee, $newStatus, $effectiveDate, $oldStatus, $validated): void {
            $employee->update([
                'employment_status' => $newStatus,
                'termination_date' => in_array($newStatus, ['resigned', 'terminated'], true) ? $effectiveDate : null,
            ]);
            if (in_array($newStatus, ['inactive', 'resigned', 'terminated'], true)) {
                $employee->user()?->update(['account_status' => 'inactive']);
            }
            if ($newStatus === 'active') {
                $employee->user()?->update(['account_status' => 'active']);
            }

            EmployeeStatusHistory::query()->create([
                'employee_id' => (int) $employee->id,
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'effective_date' => $effectiveDate,
                'reason' => $validated['reason'] ?? null,
                'comments' => $validated['comments'] ?? null,
                'changed_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('employee-statuses.index')->with('success', __('Employee status updated successfully.'));
    }

    public function statusActionPage(Employee $employee): View
    {
        return view('hr.employees.statuses.status_action', [
            'employee' => $employee->load(['department:id,name', 'designation:id,name', 'salaryGrade:id,grade_name']),
            'statusOptions' => ['active', 'inactive', 'on_leave', 'on_notice', 'resigned', 'terminated'],
        ]);
    }

    public function promotionPage(Employee $employee): View
    {
        return view('hr.employees.statuses.promotion', [
            'employee' => $employee->load(['department:id,name', 'designation:id,name', 'salaryGrade:id,grade_name']),
            'departments' => Department::query()->select(['id', 'name'])->orderBy('name')->get(),
            'designations' => Designation::query()->select(['id', 'name'])->orderBy('name')->get(),
            'salaryGrades' => SalaryGrade::query()->select(['id', 'grade_name'])->orderBy('grade_name')->get(),
        ]);
    }

    public function promote(PromoteEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        if (in_array((string) $employee->employment_status, ['resigned', 'terminated'], true)) {
            return redirect()->route('employee-statuses.index')->withErrors(['promotion' => 'Cannot promote resigned or terminated employee. Rejoin first.']);
        }

        $validated = $request->validated();
        $effectiveDate = Carbon::parse((string) $validated['effective_date'])->toDateString();

        $designationId = $validated['designation_id'] ?? $employee->designation_id;
        $salaryGradeId = $validated['salary_grade_id'] ?? $employee->salary_grade_id;
        $departmentId = $validated['department_id'] ?? $employee->department_id;
        $previousSalary = $employee->salaryGrade?->min_salary;
        $revisedSalaryInput = array_key_exists('revised_salary', $validated) && $validated['revised_salary'] !== null && $validated['revised_salary'] !== ''
            ? (float) $validated['revised_salary']
            : null;
        $revisedSalaryByGrade = SalaryGrade::query()->find($salaryGradeId)?->min_salary;

        $hasStructureChange =
            (int) $designationId === (int) $employee->designation_id
            ? false
            : true;
        $hasStructureChange = $hasStructureChange
            || ((int) $salaryGradeId !== (int) $employee->salary_grade_id)
            || ((int) $departmentId !== (int) $employee->department_id);

        $effectiveRevisedSalary = $revisedSalaryInput ?? ($revisedSalaryByGrade !== null ? (float) $revisedSalaryByGrade : null);
        $hasSalaryChange = $effectiveRevisedSalary !== null && (float) ($previousSalary ?? 0) !== (float) $effectiveRevisedSalary;

        if (! $hasStructureChange && ! $hasSalaryChange) {
            return redirect()->route('employee-statuses.promotion-page', $employee)->withErrors(['promotion' => 'No promotion change detected. Change designation/grade/department or set revised salary.']);
        }

        DB::transaction(function () use ($request, $employee, $validated, $designationId, $salaryGradeId, $departmentId, $effectiveDate): void {
            $oldDesignation = $employee->designation?->name ?? '-';
            $newDesignation = Designation::query()->find($designationId)?->name ?? '-';
            $oldSalaryGrade = $employee->salaryGrade?->grade_name ?? '-';
            $newSalaryGrade = SalaryGrade::query()->find($salaryGradeId)?->grade_name ?? '-';

            $previousSalary = $employee->salaryGrade?->min_salary;
            $revisedSalary = array_key_exists('revised_salary', $validated) && $validated['revised_salary'] !== null && $validated['revised_salary'] !== ''
                ? (float) $validated['revised_salary']
                : SalaryGrade::query()->find($salaryGradeId)?->min_salary;

            $employee->update([
                'designation_id' => $designationId,
                'salary_grade_id' => $salaryGradeId,
                'department_id' => $departmentId,
            ]);

            SalaryRevisionHistory::query()->create([
                'employee_id' => (int) $employee->id,
                'salary_template_id' => null,
                'previous_salary' => $previousSalary,
                'revised_salary' => $revisedSalary ?? $previousSalary ?? 0,
                'effective_from' => $effectiveDate,
                'reason' => (string) $validated['reason'],
                'comments' => $validated['comments'] ?? null,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            EmployeeStatusHistory::query()->create([
                'employee_id' => (int) $employee->id,
                'status_from' => (string) $employee->employment_status,
                'status_to' => (string) $employee->employment_status,
                'effective_date' => $effectiveDate,
                'reason' => 'Promotion',
                'comments' => 'Designation: ' . $oldDesignation . ' -> ' . $newDesignation . '; Salary Grade: ' . $oldSalaryGrade . ' -> ' . $newSalaryGrade . '; Salary: ' . (string) ($previousSalary ?? 0) . ' -> ' . (string) ($revisedSalary ?? $previousSalary ?? 0) . '. ' . (string) ($validated['comments'] ?? ''),
                'changed_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('employee-statuses.index')->with('success', __('Employee promotion updated successfully.'));
    }

    public function rejoinPage(Employee $employee): View
    {
        if (! in_array((string) $employee->employment_status, ['resigned', 'terminated', 'inactive'], true)) {
            abort(422, __('Employee is not in a rejoin-eligible status.'));
        }

        return view('hr.employees.statuses.rejoin', [
            'employee' => $employee->load(['department:id,name', 'designation:id,name', 'salaryGrade:id,grade_name']),
            'departments' => Department::query()->select(['id', 'name'])->orderBy('name')->get(),
            'designations' => Designation::query()->select(['id', 'name'])->orderBy('name')->get(),
            'salaryGrades' => SalaryGrade::query()->select(['id', 'grade_name'])->orderBy('grade_name')->get(),
            'managers' => Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->where('employment_status', 'active')
                ->where('id', '!=', (int) $employee->id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function rejoin(RejoinEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        if (! in_array((string) $employee->employment_status, ['resigned', 'terminated', 'inactive'], true)) {
            return redirect()->route('employee-statuses.index')->withErrors(['rejoin' => 'Employee is not in a rejoin-eligible status.']);
        }

        $validated = $request->validated();
        $rejoinDate = Carbon::parse((string) $validated['rejoin_date'])->toDateString();
        $oldStatus = (string) $employee->employment_status;

        DB::transaction(function () use ($request, $employee, $validated, $rejoinDate, $oldStatus): void {
            $employee->update([
                'employment_status' => 'active',
                'termination_date' => null,
                'designation_id' => $validated['designation_id'] ?? $employee->designation_id,
                'salary_grade_id' => $validated['salary_grade_id'] ?? $employee->salary_grade_id,
                'department_id' => $validated['department_id'] ?? $employee->department_id,
                'reports_to_id' => $validated['reports_to_id'] ?? $employee->reports_to_id,
            ]);
            $employee->user()?->update(['account_status' => 'active']);

            EmployeeStatusHistory::query()->create([
                'employee_id' => (int) $employee->id,
                'status_from' => $oldStatus,
                'status_to' => 'active',
                'effective_date' => $rejoinDate,
                'reason' => (string) $validated['reason'],
                'comments' => (string) ($validated['comments'] ?? ''),
                'changed_by' => $request->user()?->id,
            ]);
        });

        return redirect()->route('employee-statuses.index')->with('success', __('Employee rejoined and account reactivated successfully.'));
    }

    /**
     * @return array<int, int>
     */
    private function subordinateEmployeeIds(?User $user): array
    {
        $employee = $user?->employee;
        if (! $employee) {
            return [];
        }

        return $employee->subordinates()->pluck('id')->all();
    }

    /**
     * @return array<int, int>
     */
    private function visibleResignationEmployeeIds(?User $user): array
    {
        $employee = $user?->employee;
        if (! $employee) {
            return [];
        }

        $ids = collect([(int) $employee->id]);

        if ($user?->hasPermission('dashboard.view-department') && (int) $employee->department_id > 0) {
            $ids = $ids->merge(
                Employee::query()
                    ->where('department_id', (int) $employee->department_id)
                    ->pluck('id')
            );
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function approvableResignationEmployeeIds(?User $user): array
    {
        $employee = $user?->employee;
        if (! $employee) {
            return [];
        }

        if ($user?->hasPermission('dashboard.view-department') && (int) $employee->department_id > 0) {
            return Employee::query()
                ->where('department_id', (int) $employee->department_id)
                ->where('id', '!=', (int) $employee->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return $this->subordinateEmployeeIds($user);
    }
}
