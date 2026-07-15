@extends('layouts.backend')
@section('title', 'Leave Balances')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-calendar"></i> {{ $isSelfView ? __('My Leave Balance') : __('Leave Balances') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if($canManageBalances && $hasAllAccess)
                <div class="card no-border mb-3">
                    <div class="content_wrapper content-padded">
                        <h5 class="table_banner_title mb-3">{{ __('Balance Sync (Salary Grade Policy to Employee)') }}</h5>
                        <form method="POST" action="{{ route('leave-balances.sync') }}" class="row g-2">
                            @csrf
                            <div class="col-md-2">
                                <label>{{ __('Year') }}</label>
                                <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $filters['year'] }}" required>
                            </div>
                            <div class="col-md-4">
                                <label>{{ __('Employee (Optional)') }}</label>
                                <select name="employee_id" class="form-control js-example-basic-single">
                                    <option value="0">{{ __('All Employees') }}</option>
                                    @foreach($employees as $employee)
                                        @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                        <option value="{{ $employee->id }}">{{ $name !== '' ? $name : 'Employee' }} ({{ $employee->employee_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>{{ __('Salary Grade (Optional)') }}</label>
                                <select name="salary_grade_id" class="form-control">
                                    <option value="0">{{ __('All Salary Grades') }}</option>
                                    @foreach($salaryGrades as $salaryGrade)
                                        <option value="{{ $salaryGrade->id }}">{{ $salaryGrade->grade_name }} ({{ $salaryGrade->grade_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-custom w-100"><i class="icon-refresh"></i> {{ __('Sync') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ $isSelfView ? __('My Leave Balance History') : __('Employee Leave Balance List') }}</h5>

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-2">
                            <input type="number" min="2000" max="2100" name="year" class="form-control" value="{{ $filters['year'] }}" required>
                        </div>
                        @if(! $isSelfView)
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
                            <div class="col-md-3">
                                <select name="salary_grade_id" class="form-control">
                                    <option value="0">{{ __('All Salary Grades') }}</option>
                                    @foreach($salaryGrades as $salaryGrade)
                                        <option value="{{ $salaryGrade->id }}" {{ (int) $filters['salary_grade_id'] === (int) $salaryGrade->id ? 'selected' : '' }}>
                                            {{ $salaryGrade->grade_name }} ({{ $salaryGrade->grade_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="{{ $isSelfView ? 'col-md-4' : 'col-md-2' }}">
                            <select name="leave_category_id" class="form-control">
                                <option value="0">{{ $isSelfView ? __('My Categories') : __('All Categories') }}</option>
                                @foreach($leaveCategories as $leaveCategory)
                                    <option value="{{ $leaveCategory->id }}" {{ (int) $filters['leave_category_id'] === (int) $leaveCategory->id ? 'selected' : '' }}>
                                        {{ $leaveCategory->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i></button>
                            <a href="{{ route('leave-balances.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Year') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Salary Grade') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Opening') }}</th>
                                    <th>{{ __('Allocated') }}</th>
                                    <th>{{ __('Carry Fwd') }}</th>
                                    <th>{{ __('Earned') }}</th>
                                    <th>{{ __('Availed') }}</th>
                                    <th>{{ __('Adjustments') }}</th>
                                    <th>{{ __('Closing') }}</th>
                                    @if($canManageBalances)
                                        <th>{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($balances as $balance)
                                    @php($employee = $balance->employee)
                                    @php($employeeName = trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $balance->year }}</td>
                                        <td>{{ $employeeName !== '' ? $employeeName : '-' }} ({{ $employee?->employee_code ?? '-' }})</td>
                                        <td>{{ $employee?->salaryGrade?->grade_name ?? '-' }}</td>
                                        <td>{{ $balance->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ number_format((float) $balance->opening_balance, 2) }}</td>
                                        <td>{{ number_format((float) $balance->allocated, 2) }}</td>
                                        <td>{{ number_format((float) $balance->carried_forward, 2) }}</td>
                                        <td>{{ number_format((float) ($balance->earned_credited ?? 0), 2) }}</td>
                                        <td>{{ number_format((float) $balance->availed, 2) }}</td>
                                        <td>{{ number_format((float) $balance->adjustments, 2) }}</td>
                                        <td><strong>{{ number_format((float) $balance->closing_balance, 2) }}</strong></td>
                                        @if($canManageBalances)
                                            <td class="action-buttons">
                                                <a href="{{ route('leave-balances.edit', $balance) }}" title="{{ __('Edit Balance') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canManageBalances ? 12 : 11 }}" class="text-center">{{ __('No leave balance records found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $balances->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
