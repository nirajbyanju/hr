<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskTag;
use App\Modules\Tasks\Http\Requests\StoreTaskTagRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskTagController extends Controller
{
    public function index(): View
    {
        return view('hr.tasks.tags.index', [
            'tags' => TaskTag::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('hr.tasks.tags.form', ['mode' => 'create']);
    }

    public function store(StoreTaskTagRequest $request): RedirectResponse
    {
        TaskTag::query()->create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('task-tags.index')->with('success', __('Task tag created successfully.'));
    }

    public function edit(TaskTag $tag): View
    {
        return view('hr.tasks.tags.form', ['mode' => 'edit', 'tag' => $tag]);
    }

    public function update(StoreTaskTagRequest $request, TaskTag $tag): RedirectResponse
    {
        $tag->update($request->validated() + ['updated_by' => $request->user()->id]);

        return redirect()->route('task-tags.index')->with('success', __('Task tag updated successfully.'));
    }

    public function destroy(TaskTag $tag): RedirectResponse
    {
        $tag->update(['deleted_by' => request()->user()->id]);
        $tag->delete();

        return redirect()->route('task-tags.index')->with('success', __('Task tag deleted successfully.'));
    }
}
