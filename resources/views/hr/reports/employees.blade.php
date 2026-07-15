@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-people"></i> {{ __('Employee Report') }}</h1>
        <a href="{{ route('reports.employees.export', request()->query()) }}" class="btn btn-custom-default"><i class="icon-cloud-download"></i> {{ __('Export CSV') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border mb-3">
                <div class="content_wrapper content-padded">
                    <div class="row g-3">
                        <div class="col-md-3"><strong>{{ __('Total:') }}</strong> {{ $summary['total'] }}</div>
                        <div class="col-md-3"><strong>{{ __('Active:') }}</strong> {{ $summary['active'] }}</div>
                        <div class="col-md-3"><strong>{{ __('Inactive:') }}</strong> {{ $summary['inactive'] }}</div>
                        <div class="col-md-3"><strong>{{ __('Separated:') }}</strong> {{ $summary['terminated'] }}</div>
                    </div>
                </div>
            </div>

            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3"><input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search employee') }}"></div>
                        <div class="col-md-3">
                            <select name="department_id" class="form-control">
                                <option value="0">{{ __('All Departments') }}</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ (int) $filters['department_id'] === (int) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                @foreach(['active','inactive','resigned','terminated'] as $status)
                                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ __(ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }} / page</option>@endforeach</select></div>
                        <div class="col-md-2 d-flex gap-2"><button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button><a href="{{ route('reports.employees') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Department') }}</th><th>{{ __('Designation') }}</th><th>{{ __('Grade') }}</th><th>{{ __('Email') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @forelse($employees as $employee)
                                    <tr>
                                        <td>{{ $employee->employee_code }}</td>
                                        <td>{{ trim($employee->first_name.' '.$employee->last_name) }}</td>
                                        <td>{{ $employee->department?->name ?: '-' }}</td>
                                        <td>{{ $employee->designation?->name ?: '-' }}</td>
                                        <td>{{ $employee->salaryGrade?->grade_name ?: '-' }}</td>
                                        <td>{{ $employee->work_email ?: '-' }}</td>
                                        <td>{{ $employee->phone ?: '-' }}</td>
                                        <td>{{ $employee->date_of_joining ?: '-' }}</td>
                                        <td><span class="badge bg-secondary">{{ __(ucfirst($employee->employment_status)) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center">{{ __('No employees found.') }}</td></tr>
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
