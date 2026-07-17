<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use App\Modules\Tasks\Http\Requests\StoreTaskCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskCategoryController extends Controller
{
    public function index(): View
    {
        return view('hr.tasks.categories.index', [
            'categories' => TaskCategory::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('hr.tasks.categories.form', ['mode' => 'create']);
    }

    public function store(StoreTaskCategoryRequest $request): RedirectResponse
    {
        TaskCategory::query()->create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('task-categories.index')->with('success', __('Task category created successfully.'));
    }

    public function edit(TaskCategory $category): View
    {
        return view('hr.tasks.categories.form', ['mode' => 'edit', 'category' => $category]);
    }

    public function update(StoreTaskCategoryRequest $request, TaskCategory $category): RedirectResponse
    {
        $category->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('task-categories.index')->with('success', __('Task category updated successfully.'));
    }

    public function destroy(TaskCategory $category): RedirectResponse
    {
        $category->update(['deleted_by' => request()->user()->id]);
        $category->delete();

        return redirect()->route('task-categories.index')->with('success', __('Task category deleted successfully.'));
    }
}
