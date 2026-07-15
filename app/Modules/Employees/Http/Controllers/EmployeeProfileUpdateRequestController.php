<?php

namespace App\Modules\Employees\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeProfileUpdateRequest;
use App\Modules\Employees\Http\Requests\ProcessEmployeeProfileUpdateRequest;
use App\Modules\Employees\Http\Requests\StoreEmployeeProfileUpdateRequest;
use App\Modules\Employees\Repositories\EmployeeProfileUpdateRequestRepository;
use App\Modules\Employees\Services\EmployeeProfileUpdateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EmployeeProfileUpdateRequestController extends Controller
{
    public function __construct(
        private readonly EmployeeProfileUpdateRequestRepository $requestRepository,
        private readonly EmployeeProfileUpdateRequestService $requestService
    ) {
    }

    public function create(Request $request): View
    {
        $employee = $request->user()?->employee;
        abort_if(! $employee, 403, 'Employee profile is not linked with this user.');
        $employee->load([
            'user:id,email',
            'department:id,name',
            'designation:id,name',
            'salaryGrade:id,grade_name,grade_code',
            'manager:id,employee_code,first_name,last_name',
            'addresses',
            'bankAccounts',
            'emergencyContacts',
            'documents',
        ]);

        $lastRejected = $this->requestRepository->latestRejectedForEmployee((int) $employee->id);
        $lastPending = $this->requestRepository->latestPendingForEmployee((int) $employee->id);

        return view('hr.employees.profile_updates.create', [
            'employee' => $employee,
            'lastRejected' => $lastRejected,
            'lastPending' => $lastPending,
        ]);
    }

    public function store(StoreEmployeeProfileUpdateRequest $request): RedirectResponse
    {
        $employee = $request->user()?->employee;
        abort_if(! $employee, 403, 'Employee profile is not linked with this user.');

        try {
            $this->requestService->submit(
                $employee,
                (int) $request->user()->id,
                $request->validated(),
                $request->file('avatar')
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('employees.profile-updates.create')
            ->with('success', __('Profile update request submitted. HR will review it.'));
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'approval_status' => (string) $request->input('approval_status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.employees.profile_updates.index', [
            'requests' => $this->requestRepository->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function show(EmployeeProfileUpdateRequest $profileUpdateRequest): View
    {
        $request = $this->requestRepository->findForReview((int) $profileUpdateRequest->id);
        abort_if(! $request, 404);

        return view('hr.employees.profile_updates.show', [
            'requestItem' => $request,
        ]);
    }

    public function process(
        ProcessEmployeeProfileUpdateRequest $request,
        EmployeeProfileUpdateRequest $profileUpdateRequest
    ): RedirectResponse {
        try {
            $this->requestService->process(
                $profileUpdateRequest,
                $request->validated(),
                (int) $request->user()->id
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('employees.profile-updates.index')
            ->with('success', __('Profile update request processed successfully.'));
    }
}
