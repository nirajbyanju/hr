@extends('layouts.backend')
@section('title', 'Leave Reports')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-chart"></i> {{ __('Leave Reports') }}</h1>
        <a href="{{ route('leave-reports.export', ['employee_id' => $filters['employee_id'], 'leave_category_id' => $filters['leave_category_id'], 'status' => $filters['status'], 'search' => $filters['search'] ?? '', 'from_date' => $filters['from_date'], 'to_date' => $filters['to_date']]) }}"
           class="btn btn-custom-default">
            <i class="icon-cloud-download"></i> {{ __('Export Excel (CSV)') }}
        </a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name / code / email') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="employee_id" class="form-control js-example-basic-single">
                                <option value="0">{{ __('All Employees') }}</option>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    <option value="{{ $employee->id }}" {{ (int) $filters['employee_id'] === (int) $employee->id ? 'selected' : '' }}>
                                        {{ $name !== '' ? $name : 'Employee' }} ({{ $employee->employee_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="leave_category_id" class="form-control">
                                <option value="0">{{ __('All Categories') }}</option>
                                @foreach($leaveCategories as $category)
                                    <option value="{{ $category->id }}" {{ (int) $filters['leave_category_id'] === (int) $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                @foreach(['pending' => 'Pending', 'supervisor_approved' => 'Awaiting HR Approval', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $status => $label)
                                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <x-date-field name="from_date" :value="$filters['from_date']" :placeholder="__('From date')" wrapper-class="" />
                        </div>
                        <div class="col-md-2">
                            <x-date-field name="to_date" :value="$filters['to_date']" :placeholder="__('To date')" wrapper-class="" />
                        </div>
                        <div class="col-md-1">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('leave-reports.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Applied At') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Employee Code') }}</th>
                                    <th>{{ __('Salary Grade') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Approver') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    @php($empName = trim(($application->employee?->first_name ?? '').' '.($application->employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $application->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>{{ $empName !== '' ? $empName : '-' }}</td>
                                        <td>{{ $application->employee?->employee_code ?? '-' }}</td>
                                        <td>{{ $application->employee?->salaryGrade?->grade_name ?? '-' }}</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>{{ number_format((float) $application->total_days, 2) }}</td>
                                        <td>{{ __(match($application->status) {
                                            'supervisor_approved' => 'Awaiting HR Approval',
                                            default => ucfirst($application->status),
                                        }) }}</td>
                                        <td>{{ $application->approver?->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">{{ __('No leave records found for selected filters.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $applications->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
