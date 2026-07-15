@extends('layouts.backend')
@section('title', 'Resignation Supervisor Approvals')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-user-following"></i> {{ __('Resignation Supervisor Approvals') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                @foreach(['pending_supervisor', 'pending_final', 'approved', 'supervisor_rejected', 'final_rejected'] as $status)
                                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
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
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('employee-resignations.supervisor-approvals') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Notice Date') }}</th>
                                    <th>{{ __('Requested LWD') }}</th>
                                    <th>{{ __('Reason') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $item)
                                    @php($employeeName = trim(($item->employee?->first_name ?? '') . ' ' . ($item->employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $employeeName !== '' ? $employeeName : '-' }} ({{ $item->employee?->employee_code ?? '-' }})</td>
                                        <td>{{ $item->notice_date ?? '-' }}</td>
                                        <td>{{ $item->requested_last_working_day }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit((string) $item->reason, 80) }}</td>
                                        <td>{{ __(ucwords(str_replace('_', ' ', $item->status))) }}</td>
                                        <td>
                                            @if($item->status === 'pending_supervisor')
                                                <div class="d-flex gap-2">
                                                    <form method="POST" action="{{ route('employee-resignations.supervisor-process', $item) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="{{ __('Supervisor note (optional)') }}">
                                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Approve') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('employee-resignations.supervisor-process', $item) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="text" name="remarks" class="form-control form-control-sm" placeholder="{{ __('Reject reason') }}" required>
                                                        <button type="submit" class="btn btn-sm btn-danger mt-1">{{ __('Reject') }}</button>
                                                    </form>
                                                </div>
                                            @else
                                                {{ $item->supervisor_remarks ?: '-' }}
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">{{ __('No resignation requests found.') }}</td>
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
