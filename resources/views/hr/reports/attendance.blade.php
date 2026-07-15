@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-clock"></i> {{ __('Attendance Report') }}</h1>
        <a href="{{ route('reports.attendance.export', request()->query()) }}" class="btn btn-custom-default"><i class="icon-cloud-download"></i> {{ __('Export CSV') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border mb-3">
                <div class="content_wrapper content-padded">
                    <div class="row g-3">
                        <div class="col-md-2"><strong>{{ __('Present:') }}</strong> {{ $summary['present'] }}</div>
                        <div class="col-md-2"><strong>{{ __('Late:') }}</strong> {{ $summary['late'] }}</div>
                        <div class="col-md-2"><strong>{{ __('Absent:') }}</strong> {{ $summary['absent'] }}</div>
                        <div class="col-md-2"><strong>{{ __('Leave:') }}</strong> {{ $summary['leave'] }}</div>
                        <div class="col-md-4"><strong>{{ __('Worked Hours:') }}</strong> {{ number_format(((float) $summary['worked_minutes']) / 60, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3"><select name="employee_id" class="form-control js-example-basic-single"><option value="0">{{ __('All Employees') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" {{ (int)$filters['employee_id']===$employee->id?'selected':'' }}>{{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
                        <div class="col-md-2"><select name="status" class="form-control"><option value="">{{ __('All Status') }}</option>@foreach(['present','late','absent','leave'] as $status)<option value="{{ $status }}" {{ $filters['status']===$status?'selected':'' }}>{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><input type="text" name="from_date" class="form-control datetimepicker" value="{{ $filters['from_date'] }}" placeholder="{{ __('From date') }}"></div>
                        <div class="col-md-2"><input type="text" name="to_date" class="form-control datetimepicker" value="{{ $filters['to_date'] }}" placeholder="{{ __('To date') }}"></div>
                        <div class="col-md-1"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }}</option>@endforeach</select></div>
                        <div class="col-md-2 d-flex gap-2"><button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i></button><a href="{{ route('reports.attendance') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee Code') }}</th><th>{{ __('Employee Name') }}</th><th>{{ __('Department') }}</th><th>{{ __('Status') }}</th><th>{{ __('Check In') }}</th><th>{{ __('Check Out') }}</th><th>{{ __('Worked') }}</th><th>{{ __('Source') }}</th></tr></thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->attendance_date?->format('Y-m-d') ?? $log->attendance_date }}</td>
                                        <td>{{ $log->employee?->employee_code ?: '-' }}</td>
                                        <td>{{ trim(($log->employee?->first_name ?? '').' '.($log->employee?->last_name ?? '')) ?: '-' }}</td>
                                        <td>{{ $log->employee?->department?->name ?: '-' }}</td>
                                        <td><span class="badge bg-secondary">{{ __(ucfirst($log->status)) }}</span></td>
                                        <td>{{ $log->check_in_at?->format('H:i') ?: '-' }}</td>
                                        <td>{{ $log->check_out_at?->format('H:i') ?: '-' }}</td>
                                        <td>{{ number_format(((float) $log->worked_minutes) / 60, 2) }}h</td>
                                        <td>{{ __(ucfirst($log->source)) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center">{{ __('No attendance records found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $logs->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
