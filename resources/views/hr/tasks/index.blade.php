@extends('layouts.backend')
@section('title', 'Tasks')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-list"></i> {{ __('Tasks') }}</h1>
        @if(auth()->user()?->hasPermission('task.create'))
            <a href="{{ route('tasks.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Task') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search title') }}"></div>
            <div class="col-md-2"><select name="project_id" class="form-control"><option value="0">{{ __('All Projects') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (int)$filters['project_id']===(int)$project->id?'selected':'' }}>{{ $project->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="assigned_to_employee_id" class="form-control"><option value="0">{{ __('All Assignees') }}</option>@foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))<option value="{{ $employee->id }}" {{ (int)$filters['assigned_to_employee_id']===(int)$employee->id?'selected':'' }}>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-control"><option value="">{{ __('All Status') }}</option>@foreach(['todo','in_progress','review','done','blocked','cancelled'] as $status)<option value="{{ $status }}" {{ $filters['status']===$status?'selected':'' }}>{{ __(ucfirst(str_replace('_',' ',$status))) }}</option>@endforeach</select></div>
            <div class="col-md-1"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i></button><a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a></div>
        </form>

        <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>{{ __('Title') }}</th><th>{{ __('Project') }}</th><th>{{ __('Assignee') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th><th>{{ __('Due Date') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
            @forelse($tasks as $task)
                @php($assignee = trim(($task->assignee?->first_name ?? '').' '.($task->assignee?->last_name ?? '')))
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->project?->name ?? '-' }}</td>
                    <td>{{ $assignee !== '' ? $assignee : '-' }}</td>
                    <td>{{ __(ucfirst($task->priority)) }}</td>
                    <td>{{ __(ucfirst(str_replace('_',' ', $task->status))) }}</td>
                    <td>{{ $task->due_date ?? '-' }}</td>
                    <td>{{ (int)$task->progress_percent }}%</td>
                    <td class="action-buttons">
                        <a href="{{ route('tasks.show', $task) }}" title="{{ __('View') }}"><i class="icon-eye"></i></a>
                        @if(auth()->user()?->hasPermission('task.update'))<a href="{{ route('tasks.edit', $task) }}" title="{{ __('Edit') }}"><i class="icon-pencil"></i></a>@endif
                        @if(auth()->user()?->hasPermission('task.delete'))
                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('{{ __('Delete this task?') }}');" class="d-inline">@csrf @method('DELETE')<button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">{{ __('No tasks found.') }}</td></tr>
            @endforelse
        </tbody></table></div>

        {{ $tasks->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection
