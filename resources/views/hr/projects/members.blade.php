@extends('layouts.backend')
@section('title', 'Project Members')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-people"></i> Project Members: {{ $project->name }}</h1>
        <a href="{{ route('projects.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <form method="POST" action="{{ route('projects.members.sync', $project) }}">
            @csrf
            <div id="project-members-wrapper" class="d-grid" class="gap-10">
                @php($rows = old('members', $project->members->map(fn($m) => ['employee_id' => $m->id, 'project_role' => (string)($m->pivot->project_role ?? 'member'), 'is_billable' => (int)($m->pivot->is_billable ?? 1), 'hourly_rate' => $m->pivot->hourly_rate])->values()->all()))
                @forelse($rows as $idx => $row)
                    <div class="row g-2 align-items-end project-member-row">
                        <div class="col-md-5"><label>{{ __('Employee') }}</label><select name="members[{{ $idx }}][employee_id]" class="form-control js-example-basic-single" required><option value="">{{ __('Select Employee') }}</option>@foreach($employees as $employee)@php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))@php($departmentName = $employee->department?->name ?? 'No Department')<option value="{{ $employee->id }}" {{ (int)($row['employee_id'] ?? 0)===(int)$employee->id ? 'selected':'' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>{{ __('Role') }}</label><select name="members[{{ $idx }}][project_role]" class="form-control" required>@foreach(['manager','lead','member','observer'] as $role)<option value="{{ $role }}" {{ ($row['project_role'] ?? 'member')===$role ? 'selected':'' }}>{{ __(ucfirst($role)) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label>{{ __('Billable') }}</label><select name="members[{{ $idx }}][is_billable]" class="form-control"><option value="1" {{ (string)($row['is_billable'] ?? '1')==='1' ? 'selected':'' }}>{{ __('Yes') }}</option><option value="0" {{ (string)($row['is_billable'] ?? '1')==='0' ? 'selected':'' }}>{{ __('No') }}</option></select></div>
                        <div class="col-md-2"><label>{{ __('Hourly Rate') }}</label><input type="number" min="0" step="0.01" name="members[{{ $idx }}][hourly_rate]" value="{{ $row['hourly_rate'] ?? '' }}" class="form-control"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-custom-default btn-sm remove-project-member"><i class="icon-trash"></i></button></div>
                    </div>
                @empty
                @endforelse
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" id="add-project-member" class="btn btn-custom-default"><i class="icon-plus"></i> {{ __('Add Member') }}</button>
                <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save Members') }}</button>
            </div>
        </form>
    </div></div></div></div>
</div>
@endsection

@push('scripts')
@php($projectEmployeeOptions = $employees->map(fn ($employee) => [
    'id' => $employee->id,
    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
    'code' => $employee->employee_code,
    'department' => $employee->department?->name ?? 'No Department',
])->values())
<script>
(function () {
    var wrapper = document.getElementById('project-members-wrapper');
    var addBtn = document.getElementById('add-project-member');
    if (!wrapper || !addBtn) return;
    var employees = @json($projectEmployeeOptions);

    function nextIndex() { return wrapper.querySelectorAll('.project-member-row').length; }
    function employeeOptions() {
        if (!Array.isArray(employees) || employees.length === 0) {
            return '<option value="">{{ __('Select Employee') }}</option>';
        }

        return '<option value="">{{ __('Select Employee') }}</option>' + employees.map(function (employee) {
            return '<option value="' + employee.id + '">' + employee.name + ' (' + employee.code + ') - ' + employee.department + '</option>';
        }).join('');
    }

    function setupSelect2(ctx) {
        if ($.fn.select2) {
            $(ctx).find('.js-example-basic-single').select2({ width: '100%' });
        }
    }

    addBtn.addEventListener('click', function () {
        var i = nextIndex();
        var row = document.createElement('div');
        row.className = 'row g-2 align-items-end project-member-row';
        row.innerHTML = `
            <div class="col-md-5"><label>{{ __('Employee') }}</label><select name="members[${i}][employee_id]" class="form-control js-example-basic-single" required>${employeeOptions()}</select></div>
            <div class="col-md-2"><label>{{ __('Role') }}</label><select name="members[${i}][project_role]" class="form-control" required><option value="manager">{{ __('Manager') }}</option><option value="lead">{{ __('Lead') }}</option><option value="member" selected>{{ __('Member') }}</option><option value="observer">{{ __('Observer') }}</option></select></div>
            <div class="col-md-2"><label>{{ __('Billable') }}</label><select name="members[${i}][is_billable]" class="form-control"><option value="1" selected>{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select></div>
            <div class="col-md-2"><label>{{ __('Hourly Rate') }}</label><input type="number" min="0" step="0.01" name="members[${i}][hourly_rate]" class="form-control"></div>
            <div class="col-md-1"><button type="button" class="btn btn-custom-default btn-sm remove-project-member"><i class="icon-trash"></i></button></div>
        `;
        wrapper.appendChild(row);
        setupSelect2(row);
    });

    wrapper.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-project-member');
        if (!btn) return;
        var row = btn.closest('.project-member-row');
        if (row) row.remove();
    });

    setupSelect2(document);
})();
</script>
@endpush
