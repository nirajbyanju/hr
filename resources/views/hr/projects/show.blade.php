@extends('layouts.backend')
@section('title', 'Project Details')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-briefcase"></i> Project: {{ $project->name }}</h1>
        <div class="d-flex gap-2">
            @if(auth()->user()?->hasPermission('project.manage-members'))<a href="{{ route('projects.members', $project) }}" class="btn btn-custom-default">{{ __('Members') }}</a>@endif
            <a href="{{ route('projects.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="row g-3">
        <div class="col-md-6"><div class="content_wrapper content-padded">
            <h5 class="table_banner_title mb-3">{{ __('Project Summary') }}</h5>
            <p><strong>{{ __('Code:') }}</strong> {{ $project->project_code }}</p>
            <p><strong>{{ __('Team:') }}</strong> {{ $project->team?->name ?? '-' }}</p>
            <p><strong>{{ __('Team Lead:') }}</strong> {{ trim(($project->team?->lead?->first_name ?? '').' '.($project->team?->lead?->last_name ?? '')) ?: '-' }}</p>
            <p><strong>{{ __('Manager:') }}</strong> {{ trim(($project->manager?->first_name ?? '').' '.($project->manager?->last_name ?? '')) ?: '-' }}</p>
            <p><strong>{{ __('Status:') }}</strong> {{ __(ucfirst(str_replace('_', ' ', $project->status))) }}</p>
            <p><strong>{{ __('Progress:') }}</strong> {{ (int) $project->progress_percent }}%</p>
            <p><strong>{{ __('Timeline:') }}</strong> {{ $project->start_date ?? '-' }} to {{ $project->deadline ?? '-' }}</p>
            <p><strong>{{ __('Description:') }}</strong><br>{{ $project->description ?: '-' }}</p>
        </div></div>
        <div class="col-md-6"><div class="content_wrapper content-padded">
            <h5 class="table_banner_title mb-3">{{ __('Assigned Members') }}</h5>
            @php
                $assignedRows = collect();

                if ($project->members->isNotEmpty()) {
                    $assignedRows = $project->members->map(fn($member) => [
                        'employee' => $member,
                        'role' => __(ucfirst($member->pivot->project_role ?? 'member')),
                    ]);
                }

                if ($assignedRows->isEmpty() && $project->team) {
                    if ($project->team->lead) {
                        $assignedRows->push([
                            'employee' => $project->team->lead,
                            'role' => 'Team Lead',
                        ]);
                    }

                    foreach ($project->team->members as $member) {
                        if ($assignedRows->contains(fn($row) => (int) $row['employee']->id === (int) $member->id)) {
                            continue;
                        }

                        $assignedRows->push([
                            'employee' => $member,
                            'role' => __('Team :role', ['role' => __(ucfirst($member->pivot->member_role ?? 'member'))]),
                        ]);
                    }
                }

                if ($assignedRows->isEmpty()) {
                    $assignedRows = $project->tasks
                        ->pluck('assignee')
                        ->filter()
                        ->unique('id')
                        ->values()
                        ->map(fn($member) => [
                            'employee' => $member,
                            'role' => 'Task Assignee',
                        ]);
                }
            @endphp
            <ul class="mb-0">
                @forelse($assignedRows as $row)
                    @php($member = $row['employee'])
                    <li>{{ trim(($member->first_name ?? '').' '.($member->last_name ?? '')) }} ({{ $member->employee_code }}) - {{ $row['role'] }}</li>
                @empty
                    <li>{{ __('No members assigned.') }}</li>
                @endforelse
            </ul>
        </div></div>
        <div class="col-md-12"><div class="content_wrapper content-padded">
            <h5 class="table_banner_title mb-3">{{ __('Project Tasks') }}</h5>
            <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>{{ __('Title') }}</th><th>{{ __('Assignee') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Due Date') }}</th><th>{{ __('Progress') }}</th></tr></thead><tbody>
                @forelse($project->tasks as $task)
                    <tr>
                        <td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                        <td>{{ trim(($task->assignee?->first_name ?? '').' '.($task->assignee?->last_name ?? '')) ?: '-' }}</td>
                        <td>{{ __(ucfirst($task->priority)) }}</td>
                        <td>{{ __(ucfirst(str_replace('_',' ', $task->status))) }}</td>
                        <td>{{ $task->due_date ?? '-' }}</td>
                        <td>{{ (int) $task->progress_percent }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">{{ __('No tasks added yet.') }}</td></tr>
                @endforelse
            </tbody></table></div>
        </div></div>
    </div></div></div>
</div>
@endsection
