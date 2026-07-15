@extends('layouts.backend')
@section('title', 'Apply Leave')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-plus"></i> {{ __('Apply Leave') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if(! $employee)
                <div class="alert alert-danger">{{ __('Your account is not linked with an employee profile. Contact HR.') }}</div>
            @else
                <div class="card no-border mb-3">
                    <div class="content_wrapper content-padded">
                            <h5 class="table_banner_title mb-3">{{ __('New Leave Request') }}</h5>
                        <form method="POST" action="{{ route('leave-applications.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <label>{{ __('Leave Category') }}</label>

                                <select name="leave_category_id" class="form-control" required>
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach($leaveCategories as $category)
                                        <option value="{{ $category->id }}" {{ (int) old('leave_category_id') === (int) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }} ({{ $category->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Start Date') }}</label>
                                <input type="text" name="start_date" class="form-control leave-date-picker" value="{{ old('start_date') }}" placeholder="{{ __('YYYY-MM-DD') }}" autocomplete="off" required>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('End Date') }}</label>
                                <input type="text" name="end_date" class="form-control leave-date-picker" value="{{ old('end_date') }}" placeholder="{{ __('YYYY-MM-DD') }}" autocomplete="off" required>
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Half Day') }}</label>
                                @php($isHalfDay = (int) old('is_half_day', 0))
                                <select name="is_half_day" id="is_half_day" class="form-control" required>
                                    <option value="0" {{ $isHalfDay === 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ $isHalfDay === 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="half_day_session_group">
                                <label>{{ __('Half-day Session') }}</label>
                                <select name="half_day_session" class="form-control">
                                    <option value="">{{ __('Select Session') }}</option>
                                    <option value="first_half" {{ old('half_day_session') === 'first_half' ? 'selected' : '' }}>{{ __('First Half') }}</option>
                                    <option value="second_half" {{ old('half_day_session') === 'second_half' ? 'selected' : '' }}>{{ __('Second Half') }}</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label>{{ __('Reason') }}</label>
                                <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                            </div>
                            <div class="col-md-12 mt-2">
                                <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Submit Leave Request') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('My Leave Requests') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Applied At') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Approver') }}</th>
                                    <th>{{ __('Remarks') }}</th>
                                </tr>
                            </thead>
                            
                            <tbody>
                                @forelse($applications as $application)
                                    <tr>
                                        <td>{{ $application->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>{{ number_format((float) $application->total_days, 2) }}</td>
                                        <td>
                                        @if($application->status === 'approved')
                                            <span class="badge bg-success">{{ __('Approved') }}</span>
                                        @elseif($application->status === 'rejected')
                                            <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                        @endif
                                        </td>
                                            <td>{{ $application->approver?->name ?? '-' }}</td>
                                            <td>{{ $application->approval_remarks ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No leave requests found.') }}</td>
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

@push('scripts')
<script>
(function () {
    if ($.fn.datepicker) {
        $('.leave-date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }

    var halfDaySelect = document.getElementById('is_half_day');
    var halfDaySessionGroup = document.getElementById('half_day_session_group');
    function toggleHalfDaySession() {
        if (!halfDaySelect || !halfDaySessionGroup) {
            return;
        }

        halfDaySessionGroup.style.display = halfDaySelect.value === '1' ? '' : 'none';
    }

    toggleHalfDaySession();
    if (halfDaySelect) {
        halfDaySelect.addEventListener('change', toggleHalfDaySession);
    }
})();
</script>
@endpush
