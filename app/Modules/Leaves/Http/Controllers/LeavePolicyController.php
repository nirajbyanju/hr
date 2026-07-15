<?php

namespace App\Modules\Leaves\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use App\Modules\Leaves\Http\Requests\StoreLeavePolicyRequest;
use App\Modules\Leaves\Http\Requests\UpdateLeavePolicyRequest;
use App\Modules\Leaves\Repositories\LeaveRepository;
use App\Modules\Leaves\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeavePolicyController extends Controller
{
    public function __construct(
        private readonly LeaveRepository $leaveRepository,
        private readonly LeaveService $leaveService
    ) {
    }

    public function index(Request $request): View
    {
        //return $request->all();
        $currentYear = (int) now()->year;
        //date('Y');
        //return $currentYear;
        $filters = [
            'year' => (int) $request->input('year', $currentYear),
            'salary_grade_id' => (int) $request->input('salary_grade_id', 0),
            'leave_category_id' => (int) $request->input('leave_category_id', 0),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.leaves.policies.index', [
            'policies' => $this->leaveRepository->paginatePolicies($filters),
            'salaryGrades' => $this->leaveRepository->listActiveSalaryGrades(),
            'leaveCategories' => $this->leaveRepository->listActiveCategories(),
            'filters' => $filters,
            'currentYear' => $currentYear,
        ]);
    }

    public function create(): View
    {
        return view('hr.leaves.policies.form', [
            'mode' => 'create',
            'salaryGrades' => $this->leaveRepository->listActiveSalaryGrades(),
            'leaveCategories' => $this->leaveRepository->listActiveCategories(),
        ]);
    }

    public function store(StoreLeavePolicyRequest $request): RedirectResponse
    {
        //return $request->all();
        $this->leaveService->createPolicy($request->validated());

        return redirect()->route('leave-policies.index')->with('success', __('Leave policy created successfully.'));
    }

    public function edit(LeavePolicy $leavePolicy): View
    {
        return view('hr.leaves.policies.form', [
            'mode' => 'edit',
            'leavePolicy' => $leavePolicy,
            'salaryGrades' => $this->leaveRepository->listActiveSalaryGrades(),
            'leaveCategories' => $this->leaveRepository->listActiveCategories(),
        ]);
    }

    public function update(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy): RedirectResponse
    {
        //return $request->all();
        $this->leaveService->updatePolicy($leavePolicy, $request->validated());
        return redirect()->route('leave-policies.index')->with('success', __('Leave policy updated successfully.'));
    }

    public function destroy(LeavePolicy $leavePolicy): RedirectResponse
    {
        $this->leaveService->deletePolicy($leavePolicy);

        return redirect()->route('leave-policies.index')->with('success', __('Leave policy deleted successfully.'));
    }
}
