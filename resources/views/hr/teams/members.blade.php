@extends('layouts.backend')
@section('title', 'Team Members')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-people"></i> Team Members: {{ $team->name }}</h1>
        <a href="{{ route('teams.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <form method="POST" action="{{ route('teams.members.sync', $team) }}">
            @csrf
            <div id="team-members-wrapper" class="d-grid" class="gap-10">
                @php($rows = old('members', $team->members->map(fn($m) => ['employee_id' => $m->id, 'member_role' => (string)($m->pivot->member_role ?? 'member'), 'joined_on' => $m->pivot->joined_on, 'left_on' => $m->pivot->left_on, 'is_active' => (int)($m->pivot->is_active ?? 1)])->values()->all()))
                @forelse($rows as $idx => $row)
                    <div class="row g-2 align-items-end team-member-row">
                        <div class="col-md-4">
                            <label>{{ __('Employee') }}</label>
                            <select name="members[{{ $idx }}][employee_id]" class="form-control js-example-basic-single" required>
                                <option value="">{{ __('Select Employee') }}</option>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    @php($departmentName = $employee->department?->name ?? 'No Department')
                                    <option value="{{ $employee->id }}" {{ (int) ($row['employee_id'] ?? 0) === (int) $employee->id ? 'selected' : '' }}>{{ $name }} ({{ $employee->employee_code }}) - {{ $departmentName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>{{ __('Role') }}</label>
                            <select name="members[{{ $idx }}][member_role]" class="form-control" required>
                                @foreach(['lead','member','observer'] as $role)
                                    <option value="{{ $role }}" {{ ($row['member_role'] ?? 'member') === $role ? 'selected' : '' }}>{{ __(ucfirst($role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><x-date-field :name="'members['.$idx.'][joined_on]'" :label="__('Joined')" :value="$row['joined_on'] ?? ''" wrapper-class="" /></div>
                        <div class="col-md-2"><x-date-field :name="'members['.$idx.'][left_on]'" :label="__('Left')" :value="$row['left_on'] ?? ''" wrapper-class="" /></div>
                        <div class="col-md-1"><label>{{ __('Active') }}</label><select name="members[{{ $idx }}][is_active]" class="form-control"><option value="1" {{ (string)($row['is_active'] ?? '1') === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option><option value="0" {{ (string)($row['is_active'] ?? '1') === '0' ? 'selected' : '' }}>{{ __('No') }}</option></select></div>
                        <div class="col-md-1"><button type="button" class="btn btn-custom-default btn-sm remove-team-member"><i class="icon-trash"></i></button></div>
                    </div>
                @empty
                @endforelse
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="button" id="add-team-member" class="btn btn-custom-default"><i class="icon-plus"></i> {{ __('Add Member') }}</button>
                <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save Members') }}</button>
            </div>
        </form>
    </div></div></div></div>
</div>
@endsection

@push('scripts')
@php($memberEmployeeOptions = $employees->map(fn ($employee) => [
    'id' => $employee->id,
    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
    'code' => $employee->employee_code,
    'department' => $employee->department?->name ?? 'No Department',
])->values())
{{-- Markup for a new row's date fields, rendered once by <x-date-field> so the
     JS-built rows below cannot drift from the component (or lose BS support). --}}
<template id="team-member-date-templates">
    <span data-field="joined_on"><x-date-field name="members[__INDEX__][joined_on]" :label="__('Joined')" wrapper-class="" /></span>
    <span data-field="left_on"><x-date-field name="members[__INDEX__][left_on]" :label="__('Left')" wrapper-class="" /></span>
</template>
<script>
(function () {
    var wrapper = document.getElementById('team-members-wrapper');
    var addBtn = document.getElementById('add-team-member');
    if (!wrapper || !addBtn) return;
    var employees = @json($memberEmployeeOptions);

    var dateTemplates = document.getElementById('team-member-date-templates');

    /** A row's date field, stamped from the component-rendered template. */
    function dateFieldHtml(field, index) {
        var node = dateTemplates.content.querySelector('[data-field="' + field + '"]');

        return node.innerHTML
            .replace(/__INDEX__/g, index)
            // ids are unique per render, so a clone has to be re-uniqued
            .replace(/id="([^"]+)"/g, 'id="$1-' + index + '"')
            .replace(/for="([^"]+)"/g, 'for="$1-' + index + '"');
    }

    function setupSelect2(ctx) {
        if ($.fn.select2) {
            $(ctx).find('.js-example-basic-single').select2({ width: '100%' });
        }
    }

    function nextIndex() {
        return wrapper.querySelectorAll('.team-member-row').length;
    }

    function employeeOptions() {
        if (!Array.isArray(employees) || employees.length === 0) {
            return '<option value="">{{ __('Select Employee') }}</option>';
        }

        return '<option value="">{{ __('Select Employee') }}</option>' + employees.map(function (employee) {
            return '<option value="' + employee.id + '">' + employee.name + ' (' + employee.code + ') - ' + employee.department + '</option>';
        }).join('');
    }

    addBtn.addEventListener('click', function () {
        var i = nextIndex();
        var row = document.createElement('div');
        row.className = 'row g-2 align-items-end team-member-row';
        row.innerHTML = `
            <div class="col-md-4"><label>{{ __('Employee') }}</label><select name="members[${i}][employee_id]" class="form-control js-example-basic-single" required>${employeeOptions()}</select></div>
            <div class="col-md-2"><label>{{ __('Role') }}</label><select name="members[${i}][member_role]" class="form-control" required><option value="lead">{{ __('Lead') }}</option><option value="member" selected>{{ __('Member') }}</option><option value="observer">{{ __('Observer') }}</option></select></div>
            <div class="col-md-2">${dateFieldHtml('joined_on', i)}</div>
            <div class="col-md-2">${dateFieldHtml('left_on', i)}</div>
            <div class="col-md-1"><label>{{ __('Active') }}</label><select name="members[${i}][is_active]" class="form-control"><option value="1" selected>{{ __('Yes') }}</option><option value="0">{{ __('No') }}</option></select></div>
            <div class="col-md-1"><button type="button" class="btn btn-custom-default btn-sm remove-team-member"><i class="icon-trash"></i></button></div>
        `;
        wrapper.appendChild(row);
        window.initDateFields(row);
        setupSelect2(row);
    });

    wrapper.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-team-member');
        if (!btn) return;
        var row = btn.closest('.team-member-row');
        if (row) row.remove();
    });

    setupSelect2(document);
})();
</script>
@endpush
