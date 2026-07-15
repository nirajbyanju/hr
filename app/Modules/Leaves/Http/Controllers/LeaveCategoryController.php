<?php

namespace App\Modules\Leaves\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LeaveCategory;
use App\Modules\Leaves\Http\Requests\StoreLeaveCategoryRequest;
use App\Modules\Leaves\Http\Requests\UpdateLeaveCategoryRequest;
use App\Modules\Leaves\Repositories\LeaveRepository;
use App\Modules\Leaves\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveCategoryController extends Controller
{
    public function __construct(
        private readonly LeaveRepository $leaveRepository,
        private readonly LeaveService $leaveService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.leaves.categories.index', [
            'categories' => $this->leaveRepository->paginateCategories($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.leaves.categories.form', ['mode' => 'create']);
    }

    // The store method processes the form submission for creating a new leave category. It accepts a StoreLeaveCategoryRequest instance, which contains the validated data from the form. The method calls the leave service to create a new category using the validated data. After successfully creating the category, it redirects back to the index route for leave categories with a success message indicating that the leave category was created successfully.
    public function store(StoreLeaveCategoryRequest $request): RedirectResponse
    {
        $this->leaveService->createCategory($request->validated());
        return redirect()->route('leave-categories.index')->with('success', __('Leave category created successfully.'));
    }

    public function edit(LeaveCategory $leaveCategory): View
    {
        return view('hr.leaves.categories.form', [
            'mode' => 'edit',
            'leaveCategory' => $leaveCategory,
        ]);
    }

    public function update(UpdateLeaveCategoryRequest $request, LeaveCategory $leaveCategory): RedirectResponse
    {
        $this->leaveService->updateCategory($leaveCategory, $request->validated());
        return redirect()->route('leave-categories.index')->with('success', __('Leave category updated successfully.'));
    }

    public function destroy(LeaveCategory $leaveCategory): RedirectResponse
    {
        $this->leaveService->deleteCategory($leaveCategory);
        return redirect()->route('leave-categories.index')->with('success', __('Leave category deleted successfully.'));
    }
}
