@extends('layouts.backend')
@section('title', $mode === 'create' ? __('Add Task') : __('Edit Task'))

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-list"></i> {{ $mode === 'create' ? __('Add Task') : __('Edit Task') }}</h1>
        <a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        <form method="POST" action="{{ $mode === 'create' ? route('tasks.store') : route('tasks.update', $task) }}" class="row g-2">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="col-md-5"><label>{{ __('Title') }}</label><input type="text" name="title" value="{{ old('title', $task->title ?? '') }}" class="form-control" required></div>
            <div class="col-md-3"><label>{{ __('Project') }}</label><select name="project_id" class="form-control" required><option value="">{{ __('Select Project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (int)old('project_id', $task->project_id ?? 0)===(int)$project->id?'selected':'' }}>{{ $project->name }} ({{ $project->project_code }})</option>@endforeach</select></div>
            <div class="col-md-2"><label>{{ __('Priority') }}</label><select name="priority" class="form-control" required>@foreach(['low','medium','high','urgent'] as $priority)<option value="{{ $priority }}" {{ old('priority', $task->priority ?? 'medium')===$priority?'selected':'' }}>{{ __(ucfirst($priority)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label>{{ __('Status') }}</label><select name="status" class="form-control" required>@foreach(['todo','in_progress','review','done','blocked','cancelled'] as $status)<option value="{{ $status }}" {{ old('status', $task->status ?? 'todo')===$status?'selected':'' }}>{{ __(ucfirst(str_replace('_',' ', $status))) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label>{{ __('Assignee') }}</label><select name="assigned_to_employee_id" class="form-control js-example-basic-single"><option value="">{{ __('Select Assignee') }}</option>@foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))<option value="{{ $employee->id }}" {{ (int)old('assigned_to_employee_id', $task->assigned_to_employee_id ?? 0)===(int)$employee->id?'selected':'' }}>{{ $name }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
            <div class="col-md-2"><label>{{ __('Start Date') }}</label><input type="text" name="start_date" value="{{ old('start_date', $task->start_date ?? '') }}" class="form-control task-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-2"><label>{{ __('Due Date') }}</label><input type="text" name="due_date" value="{{ old('due_date', $task->due_date ?? '') }}" class="form-control task-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-2"><label>{{ __('Progress (%)') }}</label><input type="number" min="0" max="100" name="progress_percent" value="{{ old('progress_percent', $task->progress_percent ?? 0) }}" class="form-control"></div>
            <div class="col-md-1"><label>{{ __('Est.') }}</label><input type="number" min="0" step="0.01" name="estimated_hours" value="{{ old('estimated_hours', $task->estimated_hours ?? '') }}" class="form-control"></div>
            <div class="col-md-1"><label>{{ __('Actual') }}</label><input type="number" min="0" step="0.01" name="actual_hours" value="{{ old('actual_hours', $task->actual_hours ?? '') }}" class="form-control"></div>
            <div class="col-md-12"><label>{{ __('Description') }}</label><textarea name="description" class="form-control" rows="4">{{ old('description', $task->description ?? '') }}</textarea></div>
            <div class="col-md-12 mt-2"><button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save Task') }}</button></div>
        </form>
    </div></div></div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    if ($.fn.datepicker) {
        $('.task-date-picker').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });
    }
})();
</script>
@endpush
