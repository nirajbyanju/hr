@extends('layouts.backend')
@section('title', $mode === 'create' ? __('Add Task') : __('Edit Task'))

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-list"></i> {{ $mode === 'create' ? __('Add Task') : __('Edit Task') }}</h1>
        <a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <form method="POST" action="{{ $mode === 'create' ? route('tasks.store') : route('tasks.update', $task) }}" class="row g-2">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="col-md-6"><label>{{ __('Title') }}</label><input type="text" name="title" value="{{ old('title', $task->title ?? '') }}" class="form-control" required></div>
            <div class="col-md-3"><label>{{ __('Project') }}</label><select name="project_id" class="form-control js-example-basic-single" required><option value="">{{ __('Select Project') }}</option>@foreach($projects as $project)<option value="{{ $project->id }}" {{ (int)old('project_id', $task->project_id ?? 0)===(int)$project->id?'selected':'' }}>{{ $project->name }} ({{ $project->project_code }})</option>@endforeach</select></div>
            <div class="col-md-3"><label>{{ __('Category') }}</label><select name="category_id" class="form-control js-example-basic-single"><option value="">{{ __('Select Category') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" {{ (int)old('category_id', $task->category_id ?? 0)===(int)$category->id?'selected':'' }}>{{ $category->name }}</option>@endforeach</select></div>

            <div class="col-md-3"><label>{{ __('Priority') }}</label><select name="priority_id" class="form-control" required>@foreach($priorities as $priority)<option value="{{ $priority->id }}" {{ (int)old('priority_id', $task->priority_id ?? 0)===(int)$priority->id?'selected':'' }}>{{ $priority->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label>{{ __('Visibility') }}</label><select name="visibility" class="form-control">@foreach(['public'=>__('Public'),'private'=>__('Private')] as $value => $label)<option value="{{ $value }}" {{ old('visibility', $task->visibility ?? 'public')===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><label>{{ __('Parent Task') }}</label><select name="parent_task_id" class="form-control js-example-basic-single"><option value="">{{ __('None') }}</option>@foreach($parentOptions as $parent)<option value="{{ $parent->id }}" {{ (int)old('parent_task_id', $task->parent_task_id ?? 0)===(int)$parent->id?'selected':'' }}>#{{ $parent->id }} {{ $parent->title }}</option>@endforeach</select></div>
            <div class="col-md-3"><label>{{ __('Tags') }}</label>
                <select name="tag_ids[]" class="form-control js-example-basic-multiple" multiple>
                    @php($selectedTags = old('tag_ids', isset($task) ? $task->tags->pluck('id')->all() : []))
                    @foreach($tags as $tag)<option value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTags) ? 'selected' : '' }}>{{ $tag->name }}</option>@endforeach
                </select>
            </div>

            @if($mode === 'create')
                <div class="col-md-6"><label>{{ __('Assign To (individual or team)') }}</label>
                    <select name="employee_ids[]" id="task-employee-ids" class="form-control js-example-basic-multiple" multiple>
                        @foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))<option value="{{ $employee->id }}" {{ in_array($employee->id, old('employee_ids', [])) ? 'selected' : '' }}>{{ $name }} ({{ $employee->employee_code }})</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label>{{ __('Task Owner (only they can mark Completed)') }}</label>
                    <select name="owner_employee_id" id="task-owner-id" class="form-control js-example-basic-single">
                        <option value="">{{ __('First assignee') }}</option>
                        @foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))<option value="{{ $employee->id }}" {{ (int)old('owner_employee_id', 0)===(int)$employee->id?'selected':'' }}>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-3"><label>{{ __('Start Date') }}</label><input type="text" name="start_date" value="{{ old('start_date', $task->start_date ?? '') }}" class="form-control task-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-3"><label>{{ __('Due Date') }}</label><input type="text" name="due_date" value="{{ old('due_date', $task->due_date ?? '') }}" class="form-control task-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-3"><label>{{ __('Estimated Hours') }}</label><input type="number" min="0" step="0.01" name="estimated_hours" value="{{ old('estimated_hours', $task->estimated_hours ?? '') }}" class="form-control"></div>
            <div class="col-md-3"><label>{{ __('Actual Hours') }}</label><input type="number" min="0" step="0.01" name="actual_hours" value="{{ old('actual_hours', $task->actual_hours ?? '') }}" class="form-control"></div>

            <div class="col-md-12"><label>{{ __('Description') }}</label><textarea name="description" class="form-control" rows="5">{{ old('description', $task->description ?? '') }}</textarea></div>
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
    if ($.fn.select2) {
        $('.js-example-basic-single, .js-example-basic-multiple').select2();
    }
    $('#task-employee-ids').on('change', function () {
        var selected = $(this).val() || [];
        $('#task-owner-id option').each(function () {
            $(this).toggle(selected.indexOf($(this).val()) !== -1);
        });
    }).trigger('change');
})();
</script>
@endpush
