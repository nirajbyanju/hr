<?php

namespace App\Modules\Projects\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\Projects\Http\Requests\StoreProjectRequest;
use App\Modules\Projects\Http\Requests\SyncProjectMembersRequest;
use App\Modules\Projects\Http\Requests\UpdateProjectRequest;
use App\Modules\Projects\Repositories\ProjectRepository;
use App\Modules\Projects\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectService $projectService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'team_id' => (int) $request->input('team_id', 0),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.projects.index', [
            'projects' => $this->projectRepository->paginate($filters, $request->user()),
            'teams' => $this->projectRepository->listTeams(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.projects.form', [
            'mode' => 'create',
            'teams' => $this->projectRepository->listTeams(),
            'employees' => $this->projectRepository->listActiveEmployees(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->projectService->createProject($request->validated());

        return redirect()->route('projects.index')->with('success', __('Project created successfully.'));
    }

    public function show(Project $project): View
    {
        abort_if(! $this->projectRepository->canAccess($project, request()->user()), 403);

        return view('hr.projects.show', [
            'project' => $this->projectRepository->withMembersAndTasks($project),
        ]);
    }

    public function edit(Project $project): View
    {
        abort_if(! $this->projectRepository->canAccess($project, request()->user()), 403);

        return view('hr.projects.form', [
            'mode' => 'edit',
            'project' => $this->projectRepository->withMembersAndTasks($project),
            'teams' => $this->projectRepository->listTeams(),
            'employees' => $this->projectRepository->listActiveEmployees(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        abort_if(! $this->projectRepository->canAccess($project, $request->user()), 403);

        $this->projectService->updateProject($project, $request->validated());

        return redirect()->route('projects.index')->with('success', __('Project updated successfully.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        abort_if(! $this->projectRepository->canAccess($project, request()->user()), 403);

        $this->projectService->deleteProject($project);

        return redirect()->route('projects.index')->with('success', __('Project deleted successfully.'));
    }

    public function members(Project $project): View
    {
        abort_if(! $this->projectRepository->canAccess($project, request()->user()), 403);

        return view('hr.projects.members', [
            'project' => $this->projectRepository->withMembersAndTasks($project),
            'employees' => $this->projectRepository->listActiveEmployees(),
        ]);
    }

    public function syncMembers(SyncProjectMembersRequest $request, Project $project): RedirectResponse
    {
        abort_if(! $this->projectRepository->canAccess($project, $request->user()), 403);

        $this->projectService->syncMembers($project, $request->validated()['members'] ?? []);

        return redirect()->route('projects.members', $project)->with('success', __('Project members updated successfully.'));
    }
}
