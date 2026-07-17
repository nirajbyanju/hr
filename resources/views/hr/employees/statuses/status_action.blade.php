@extends('layouts.backend')
@section('title', 'Employee Status Action')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-layers"></i> {{ __('Employee Status Action') }}</h1>
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
                        <div class="col-md-4"><strong>{{ __('Current Status:') }}</strong> {{ __(ucwords(str_replace('_', ' ', $employee->employment_status))) }}</div>
                        <div class="col-md-4"><strong>{{ __('Department:') }}</strong> {{ $employee->department?->name ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Designation:') }}</strong> {{ $employee->designation?->name ?? '-' }}</div>
                    </div>

                    <form method="POST" action="{{ route('employee-statuses.update', $employee) }}" class="row g-2">
                        @csrf
                        @method('PATCH')

                        <div class="col-md-4">
                            <label>{{ __('Employment Status') }}</label>
                            <select name="employment_status" class="form-control" required>
                                @foreach($statusOptions as $status)
                                    <option value="{{ $status }}" {{ $employee->employment_status === $status ? 'selected' : '' }}>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Effective Date') }}</label>
                            <input type="text" name="effective_date" class="form-control status-date-picker" value="{{ now()->toDateString() }}" placeholder="{{ __('YYYY-MM-DD') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label>{{ __('Reason') }}</label>
                            <input type="text" name="reason" class="form-control" placeholder="{{ __('Status change reason') }}">
                        </div>

                        <div class="col-md-12">
                            <label>{{ __('Comments') }}</label>
                            <textarea name="comments" class="form-control" rows="3" placeholder="{{ __('Optional comments') }}"></textarea>
                        </div>

                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Update Status') }}</button>
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
