@extends('layouts.backend')
@section('title', 'Resignation Final Approvals')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-shield"></i> {{ __('Resignation Final Approvals') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                            <option value="">{{ __('All Status') }}</option>
                                @foreach(['pending_final', 'approved', 'final_rejected', 'pending_supervisor', 'supervisor_rejected'] as $status)
                                <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="employee_id" class="form-control js-example-basic-single">
                            <option value="0">{{ __('All Employees') }}</option>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')))
                                    <option value="{{ $employee->id }}" {{ (int) $filters['employee_id'] === (int) $employee->id ? 'selected' : '' }}>
                                        {{ $name !== '' ? $name : 'Employee' }} ({{ $employee->employee_code }})
                                    </option>
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
                            <a href="{{ route('employee-resignations.final-approvals') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Requested LWD') }}</th>
                                    <th>{{ __('Supervisor') }}</th>
                                    <th>{{ __('Supervisor Remarks') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $item)
                                    @php($employeeName = trim(($item->employee?->first_name ?? '') . ' ' . ($item->employee?->last_name ?? '')))
                                    @php($supervisorName = trim(($item->supervisorEmployee?->first_name ?? '') . ' ' . ($item->supervisorEmployee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $employeeName !== '' ? $employeeName : '-' }} ({{ $item->employee?->employee_code ?? '-' }})</td>
                                        <td>{{ $item->requested_last_working_day }}</td>
                                        <td>{{ $supervisorName !== '' ? $supervisorName : '-' }}</td>
                                        <td>{{ $item->supervisor_remarks ?: '-' }}</td>
                                        <td>{{ __(ucwords(str_replace('_', ' ', $item->status))) }}</td>
                                        <td>
                                            @if($item->status === 'pending_final')
                                                <div class="d-flex gap-2 flex-column">
                                                    <form method="POST" action="{{ route('employee-resignations.final-process', $item) }}" class="d-flex gap-2 align-items-start">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="text" name="final_last_working_day" class="form-control form-control-sm resignation-date-picker" value="{{ $item->requested_last_working_day }}" placeholder="{{ __('Final LWD') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Final Approve') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('employee-resignations.final-process', $item) }}" class="d-flex gap-2 align-items-start">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @else
                                                {{ $item->final_remarks ?: '-' }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">{{ __('No requests found for selected filters.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $requests->links('pagination::bootstrap-5') }}
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
        $('.resignation-date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
})();
</script>
@endpush
