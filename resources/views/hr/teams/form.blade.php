@extends('layouts.backend')
@section('title', $mode === 'create' ? __('Add Team') : __('Edit Team'))

@section('content')
<div class="wrapper-page">
    @php($selectedMemberIds = collect(old('member_ids', isset($team) ? $team->members->pluck('id')->all() : []))->map(fn($id) => (int) $id)->all())
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-people"></i> {{ $mode === 'create' ? __('Add Team') : __('Edit Team') }}</h1>
        <a href="{{ route('teams.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'create' ? route('teams.store') : route('teams.update', $team) }}" class="row g-2">
                        @csrf
                        @if($mode === 'edit') @method('PUT') @endif

                        <div class="col-md-4">
                            <label>{{ __('Name') }}</label>
                            <input type="text" name="name" value="{{ old('name', $team->name ?? '') }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('Code') }}</label>
                            <input type="text" name="code" value="{{ old('code', $team->code ?? '') }}" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('Primary Department') }}</label>
                            <select name="department_id" class="form-control">
                                <option value="">{{ __('No Primary Department') }}</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ (int) old('department_id', $team->department_id ?? 0) === (int) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>{{ __('Lead Employee') }}</label>
                            <select name="lead_employee_id" class="form-control js-example-basic-single">
                                <option value="">{{ __('Select Lead') }}</option>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    @php($departmentName = $employee->department?->name ?? 'No Department')
                                    <option value="{{ $employee->id }}" {{ (int) old('lead_employee_id', $team->lead_employee_id ?? 0) === (int) $employee->id ? 'selected' : '' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Status') }}</label>
                            <select name="is_active" class="form-control">
                                <option value="1" {{ (string) old('is_active', (isset($team) ? (int) $team->is_active : 1)) === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                <option value="0" {{ (string) old('is_active', (isset($team) ? (int) $team->is_active : 1)) === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label>{{ __('Team Members') }}</label>
                            <select name="member_ids[]" class="form-control js-example-basic-multiple" multiple>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    @php($departmentName = $employee->department?->name ?? 'No Department')
                                    <option value="{{ $employee->id }}" {{ in_array((int) $employee->id, $selectedMemberIds, true) ? 'selected' : '' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label>{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $team->description ?? '') }}</textarea>
                        </div>
                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save Team') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    if ($.fn.select2) {
        $('.js-example-basic-multiple').select2({
            width: '100%',
            placeholder: @json(__('Select employees from any department'))
        });
    }
})();
</script>
@endpush
