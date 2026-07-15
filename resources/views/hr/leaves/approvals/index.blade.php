@extends('layouts.backend')
@section('title', 'Leave Approvals')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-check"></i> {{ __('Leave Approvals') }}</h1>
        <a href="{{ route('leave-approvals.export', ['status' => $filters['status'], 'employee_id' => $filters['employee_id'], 'from_date' => $filters['from_date'], 'to_date' => $filters['to_date']]) }}"
           class="btn btn-custom-default">
            <i class="icon-cloud-download"></i> {{ __('Export Excel (CSV)') }}
        </a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="actionable" {{ $filters['status'] === 'actionable' ? 'selected' : '' }}>{{ __('Needs Action') }}</option>
                                <option value="">{{ __('All Status') }}</option>
                                <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>{{ __('Pending (awaiting supervisor)') }}</option>
                                <option value="supervisor_approved" {{ $filters['status'] === 'supervisor_approved' ? 'selected' : '' }}>{{ __('Awaiting HR Approval') }}</option>
                                <option value="approved" {{ $filters['status'] === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                                <option value="rejected" {{ $filters['status'] === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                            </select>
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
                            <input type="text" name="from_date" class="form-control leave-date-picker" value="{{ $filters['from_date'] }}" placeholder="{{ __('From date') }}">
                        </div>
                        <div class="col-md-2">
                            <input type="text" name="to_date" class="form-control leave-date-picker" value="{{ $filters['to_date'] }}" placeholder="{{ __('To date') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i></button>
                            <a href="{{ route('leave-approvals.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Reason') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    @php($empName = trim(($application->employee?->first_name ?? '').' '.($application->employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $empName !== '' ? $empName : '-' }} ({{ $application->employee?->employee_code ?? '-' }})</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>
                                            {{ number_format((float) $application->total_days, 2) }}
                                            @if(in_array($application->status, ['pending', 'supervisor_approved'], true) && isset($pendingSplits[$application->id]))
                                                @php($split = $pendingSplits[$application->id])
                                                @if($split['unpaid'] > 0)
                                                    <br><small class="text-danger">{{ __('Preview') }}: {{ number_format($split['paid'], 2) }} {{ __('paid') }}, {{ number_format($split['unpaid'], 2) }} {{ __('unpaid') }}</small>
                                                @else
                                                    <br><small class="text-muted">{{ __('Fully covered by balance') }}</small>
                                                @endif
                                            @elseif($application->status === 'approved' && (float) ($application->unpaid_days ?? 0) > 0)
                                                <br><small class="text-danger">{{ number_format((float) $application->paid_days, 2) }} {{ __('paid') }}, {{ number_format((float) $application->unpaid_days, 2) }} {{ __('unpaid') }}</small>
                                            @endif
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit((string) $application->reason, 80) }}</td>
                                        <td>
                                            @if($application->status === 'approved')
                                                <span class="badge bg-success">{{ __('Approved') }}</span>
                                            @elseif($application->status === 'rejected')
                                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                            @elseif($application->status === 'supervisor_approved')
                                                <span class="badge bg-info text-dark">{{ __('Awaiting HR Approval') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php($hasSupervisor = (bool) ($application->employee?->reports_to_id))
                                            @if($application->status === 'pending' && ! $isFinalApprover)
                                                {{-- Stage 1: plain supervisor sign-off --}}
                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Sign Off') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger mt-1">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @elseif($application->status === 'pending' && $isFinalApprover && $hasSupervisor)
                                                {{-- HR finalizing before the supervisor has signed off: override, reason required --}}
                                                <div class="d-flex flex-column gap-1">
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}" class="d-flex gap-2">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="text" name="override_reason" class="form-control form-control-sm" placeholder="{{ __('Reason for approving before sign-off') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Approve') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger mt-1">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @elseif($application->status === 'pending' && $isFinalApprover)
                                                {{-- No supervisor assigned at all: not an override --}}
                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Approve') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger mt-1">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @elseif($application->status === 'supervisor_approved' && $isFinalApprover)
                                                {{-- Stage 2: HR final approval --}}
                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Final Approve') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('leave-approvals.process', $application) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger mt-1">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @elseif($application->status === 'supervisor_approved')
                                                <span class="text-muted">{{ __('Signed off, awaiting HR') }}</span>
                                            @else
                                                {{ $application->approval_remarks ?: '-' }}
                                                @if($application->override_reason)
                                                    <br><small class="text-muted">{{ __('Override reason') }}: {{ $application->override_reason }}</small>
                                                @endif
                                            @endif
                                        </td>
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
})();
</script>
@endpush
