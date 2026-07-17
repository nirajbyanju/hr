@extends('layouts.backend')
@section('title', 'Employee Status')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-layers"></i> {{ __('Employee Status Module') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" class="form-control" placeholder="{{ __('Search code, name, phone, email or blood group') }}" value="{{ $filters['q'] }}">
                        </div>
                        <div class="col-md-3">
                            <select name="employment_status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                @foreach($statusOptions as $status)
                                    <option value="{{ $status }}" {{ $filters['employment_status'] === $status ? 'selected' : '' }}>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('employee-statuses.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Blood Group') }}</th>
                                    <th>{{ __('Reports To') }}</th>
                                    <th>{{ __('Department / Designation / Grade') }}</th>
                                    <th>{{ __('Current Status') }}</th>
                                    <th>{{ __('Joined Date') }}</th>
                                    <th>{{ __('Termination Date') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')))
                                    @php($managerName = trim(($employee->manager?->first_name ?? '') . ' ' . ($employee->manager?->last_name ?? '')))
                                    @php($email = $employee->work_email ?: ($employee->personal_email ?: $employee->user?->email))
                                    <tr>
                                        <td>{{ $name !== '' ? $name : '-' }} ({{ $employee->employee_code }})</td>
                                        <td>{{ $employee->phone ?: '-' }}</td>
                                        <td>{{ $email ?: '-' }}</td>
                                        <td>{{ $employee->blood_group ?: '-' }}</td>
                                        <td>{{ $managerName !== '' ? $managerName : '-' }}</td>
                                        <td>
                                            <div><strong>{{ __('Dept:') }}</strong> {{ $employee->department?->name ?? '-' }}</div>
                                            <div><strong>{{ __('Desig:') }}</strong> {{ $employee->designation?->name ?? '-' }}</div>
                                            <div><strong>{{ __('Grade:') }}</strong> {{ $employee->salaryGrade?->grade_name ?? '-' }}</div>
                                        </td>
                                        <td><span class="badge bg-secondary">{{ __(ucwords(str_replace('_', ' ', $employee->employment_status))) }}</span></td>
                                        <td>{{ $employee->date_of_joining }}</td>
                                        <td>{{ $employee->termination_date ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                @if(auth()->user()?->hasPermission('employee.status-update'))
                                                    <a href="{{ route('employee-statuses.status-action-page', $employee) }}" class="btn btn-sm btn-primary">{{ __('Status') }}</a>
                                                @endif
                                                @if(auth()->user()?->hasPermission('employee.promotion-manage'))
                                                    <a href="{{ route('employee-statuses.promotion-page', $employee) }}" class="btn btn-sm btn-success">{{ __('Promotion') }}</a>
                                                @endif
                                                @if(auth()->user()?->hasPermission('employee.rejoin-manage') && in_array($employee->employment_status, ['resigned', 'terminated', 'inactive'], true))
                                                    <a href="{{ route('employee-statuses.rejoin-page', $employee) }}" class="btn btn-sm btn-warning">{{ __('Rejoin') }}</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">{{ __('No employees found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $employees->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
