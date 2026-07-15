@extends('layouts.backend')
@section('title', $mode === 'create' ? __('Add Project') : __('Edit Project'))

@section('content')
<div class="wrapper-page">
    @php($selectedMemberIds = collect(old('member_ids', isset($project) ? $project->members->pluck('id')->all() : []))->map(fn($id) => (int) $id)->all())
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-briefcase"></i> {{ $mode === 'create' ? __('Add Project') : __('Edit Project') }}</h1>
        <a href="{{ route('projects.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        <form method="POST" action="{{ $mode === 'create' ? route('projects.store') : route('projects.update', $project) }}" class="row g-2">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="col-md-4"><label>{{ __('Name') }}</label><input type="text" name="name" value="{{ old('name', $project->name ?? '') }}" class="form-control" required></div>
            <div class="col-md-3"><label>{{ __('Project Code') }}</label><input type="text" name="project_code" value="{{ old('project_code', $project->project_code ?? '') }}" class="form-control" required></div>
            <div class="col-md-3"><label>{{ __('Team') }}</label><select name="team_id" class="form-control"><option value="">{{ __('Select Team') }}</option>@foreach($teams as $team)<option value="{{ $team->id }}" {{ (int)old('team_id', $project->team_id ?? 0)===(int)$team->id ? 'selected':'' }}>{{ $team->name }} ({{ $team->code }})</option>@endforeach</select></div>
            <div class="col-md-2"><label>{{ __('Status') }}</label><select name="status" class="form-control" required>@foreach(['draft','active','on_hold','completed','cancelled'] as $status)<option value="{{ $status }}" {{ old('status', $project->status ?? 'draft')===$status ? 'selected':'' }}>{{ __(ucfirst(str_replace('_',' ',$status))) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label>{{ __('Manager') }}</label><select name="manager_employee_id" class="form-control js-example-basic-single"><option value="">{{ __('Select Manager') }}</option>@foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))@php($departmentName = $employee->department?->name ?? 'No Department')<option value="{{ $employee->id }}" {{ (int)old('manager_employee_id', $project->manager_employee_id ?? 0)===(int)$employee->id ? 'selected':'' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>@endforeach</select></div>
            <div class="col-md-2"><label>{{ __('Start Date') }}</label><input type="text" name="start_date" value="{{ old('start_date', $project->start_date ?? '') }}" class="form-control project-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-2"><label>{{ __('Deadline') }}</label><input type="text" name="deadline" value="{{ old('deadline', $project->deadline ?? '') }}" class="form-control project-date-picker" placeholder="{{ __('YYYY-MM-DD') }}"></div>
            <div class="col-md-2"><label>{{ __('Budget') }}</label><input type="number" step="0.01" min="0" name="budget" value="{{ old('budget', $project->budget ?? '') }}" class="form-control"></div>
            <div class="col-md-2"><label>{{ __('Progress (%)') }}</label><input type="number" min="0" max="100" name="progress_percent" value="{{ old('progress_percent', $project->progress_percent ?? 0) }}" class="form-control"></div>
            <div class="col-md-12">
                <label>{{ __('Assigned Members') }}</label>
                <select name="member_ids[]" class="form-control js-example-basic-multiple" multiple>
                    @foreach($employees as $employee)
                        @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                        @php($departmentName = $employee->department?->name ?? 'No Department')
                        <option value="{{ $employee->id }}" {{ in_array((int) $employee->id, $selectedMemberIds, true) ? 'selected' : '' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12"><label>{{ __('Description') }}</label><textarea name="description" class="form-control" rows="3">{{ old('description', $project->description ?? '') }}</textarea></div>
            <div class="col-md-12 mt-2"><button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save Project') }}</button></div>
        </form>
    </div></div></div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    if ($.fn.datepicker) {
        $('.project-date-picker').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true });
    }
    if ($.fn.select2) {
        $('.js-example-basic-multiple').select2({
            width: '100%',
            placeholder: @json(__('Select project members from any department'))
        });
    }
})();
</script>
@endpush
