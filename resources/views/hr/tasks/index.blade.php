@extends('layouts.backend')
@section('title', 'Tasks')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-list"></i> {{ __('Tasks') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('tasks.kanban') }}" class="btn btn-custom-default"><i class="icon-grid"></i> {{ __('Kanban') }}</a>
            <a href="{{ route('tasks.my-dashboard') }}" class="btn btn-custom-default"><i class="icon-user"></i> {{ __('My Tasks') }}</a>
            @if(auth()->user()?->hasPermission('task.create'))
                <a href="{{ route('tasks.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Task') }}</a>
            @endif
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search #, title, description') }}"></div>
            <div class="col-md-2"><select name="project_id" class="form-control"><option value="0">{{ __('All Projects') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (int)$filters['project_id']===(int)$project->id?'selected':'' }}>{{ $project->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="assigned_to_employee_id" class="form-control"><option value="0">{{ __('All Assignees') }}</option>@foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))<option value="{{ $employee->id }}" {{ (int)$filters['assigned_to_employee_id']===(int)$employee->id?'selected':'' }}>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="category_id" class="form-control"><option value="0">{{ __('All Categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" {{ (int)$filters['category_id']===(int)$category->id?'selected':'' }}>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="priority_id" class="form-control"><option value="0">{{ __('All Priorities') }}</option>@foreach($priorities as $priority)<option value="{{ $priority->id }}" {{ (int)$filters['priority_id']===(int)$priority->id?'selected':'' }}>{{ $priority->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status_id" class="form-control"><option value="0">{{ __('All Status') }}</option>@foreach($statuses as $status)<option value="{{ $status->id }}" {{ (int)$filters['status_id']===(int)$status->id?'selected':'' }}>{{ $status->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="tag_id" class="form-control"><option value="0">{{ __('All Tags') }}</option>@foreach($tags as $tag)<option value="{{ $tag->id }}" {{ (int)$filters['tag_id']===(int)$tag->id?'selected':'' }}>{{ $tag->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="due_from" value="{{ $filters['due_from'] }}" class="form-control" placeholder="{{ __('Due from') }}"></div>
            <div class="col-md-2"><input type="date" name="due_to" value="{{ $filters['due_to'] }}" class="form-control" placeholder="{{ __('Due to') }}"></div>
            <div class="col-md-1"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-3 d-flex gap-2"><button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i> {{ __('Filter') }}</button><a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a></div>
        </form>

        <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>#</th><th>{{ __('Title') }}</th><th>{{ __('Project') }}</th><th>{{ __('Assignees') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Due Date') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->id }}</td>
                    <td>
                        <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                        @if($task->is_team_task)<span class="badge bg-secondary">{{ __('Team') }}</span>@endif
                        @if($task->tags->isNotEmpty())
                            @foreach($task->tags as $tag)<span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach
                        @endif
                    </td>
                    <td>{{ $task->project?->name ?? '-' }}</td>
                    <td>
                        @forelse($task->assignments as $assignment)
                            <span class="badge {{ $assignment->is_owner ? 'bg-dark' : 'bg-light text-dark border' }}">{{ trim(($assignment->employee?->first_name ?? '').' '.($assignment->employee?->last_name ?? '')) }}</span>
                        @empty
                            <span class="text-muted">{{ __('Unassigned') }}</span>
                        @endforelse
                    </td>
                    <td>@if($task->priority)<span class="badge" style="background-color: {{ $task->priority->color }}">{{ $task->priority->name }}</span>@endif</td>
                    <td>@if($task->status)<span class="badge" style="background-color: {{ $task->status->color }}">{{ $task->status->name }}</span>@endif</td>
                    <td>{{ $task->due_date ?? '-' }}</td>
                    <td>
                        <div class="progress" style="height: 6px; min-width: 60px;"><div class="progress-bar" role="progressbar" style="width: {{ (int)$task->progress_percent }}%"></div></div>
                        {{ (int)$task->progress_percent }}%
                    </td>
                    <td class="action-buttons">
                        <a href="{{ route('tasks.show', $task) }}" title="{{ __('View') }}"><i class="icon-eye"></i></a>
                        @if(auth()->user()?->hasPermission('task.update'))<a href="{{ route('tasks.edit', $task) }}" title="{{ __('Edit') }}"><i class="icon-pencil"></i></a>@endif
                        @if(auth()->user()?->hasPermission('task.delete'))
                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('{{ __('Delete this task?') }}');" class="d-inline">@csrf @method('DELETE')<button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">{{ __('No tasks found.') }}</td></tr>
            @endforelse
        </tbody></table></div>

        {{ $tasks->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection
