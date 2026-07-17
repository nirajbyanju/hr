@extends('layouts.backend')
@section('title', 'Employee Promotion')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-arrow-up-circle"></i> {{ __('Employee Promotion') }}</h1>
        <a href="{{ route('employee-statuses.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">
                        {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }} ({{ $employee->employee_code }})
                    </h5>

                    <div class="row mb-3">
                        <div class="col-md-4"><strong>{{ __('Current Department:') }}</strong> {{ $employee->department?->name ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Current Designation:') }}</strong> {{ $employee->designation?->name ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Current Salary Grade:') }}</strong> {{ $employee->salaryGrade?->grade_name ?? '-' }}</div>
                    </div>

                    <form method="POST" action="{{ route('employee-statuses.promote', $employee) }}" class="row g-2">
                        @csrf

                        <div class="col-md-4">
                            <label>{{ __('Department (optional)') }}</label>
                            <select name="department_id" class="form-control">
                                <option value="">{{ __('Keep Current') }}</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Designation (optional)') }}</label>
                            <select name="designation_id" class="form-control">
                                <option value="">{{ __('Keep Current') }}</option>
                                @foreach($designations as $designation)
                                    <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Salary Grade (optional)') }}</label>
                            <select name="salary_grade_id" class="form-control">
                                <option value="">{{ __('Keep Current') }}</option>
                                @foreach($salaryGrades as $salaryGrade)
                                    <option value="{{ $salaryGrade->id }}">{{ $salaryGrade->grade_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Effective Date') }}</label>
                            <input type="text" name="effective_date" class="form-control status-date-picker" value="{{ now()->toDateString() }}" placeholder="{{ __('YYYY-MM-DD') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Revised Salary (optional)') }}</label>
                            <input type="number" step="0.01" min="0" name="revised_salary" class="form-control" placeholder="{{ __('e.g. 45000') }}">
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Reason') }}</label>
                            <input type="text" name="reason" class="form-control" placeholder="{{ __('Promotion reason') }}" required>
                        </div>

                        <div class="col-md-12">
                            <label>{{ __('Comments') }}</label>
                            <textarea name="comments" class="form-control" rows="3" placeholder="{{ __('Optional notes') }}"></textarea>
                        </div>

                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Submit Promotion') }}</button>
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
    if ($.fn.datepicker) {
        $('.status-date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
})();
</script>
@endpush
