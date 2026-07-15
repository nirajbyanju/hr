@extends('layouts.backend')
@section('title', 'Attendance API Integration')


@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-link"></i> {{ __('Attendance API Integration') }}</h1>
        <a href="{{ route('attendance.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if($latestPlainToken)
                <div class="alert alert-warning">
                            <strong>{{ __('New API Token (copy now):') }}</strong>
                        <div class="break-all">{{ $latestPlainToken }}</div>
                    <small>{{ __('This token will not be shown again.') }}</small>
                </div>
            @endif

            @if($canManageAttendance)
                <div class="card no-border mb-3">
                    <div class="content_wrapper content-padded">
                        <h5 class="table_banner_title mb-3">{{ __('Create API Client') }}</h5>
                        <form method="POST" action="{{ route('attendance.api-clients.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-4">
                                <label>{{ __('Client Name') }}</label>
                                        <input type="text" name="name" class="form-control" required placeholder="{{ __('Device/Provider name') }}">
                                    </div>
                            <div class="col-md-6">
                                <label>{{ __('Allowed IPs (optional)') }}</label>
                                        <input type="text" name="allowed_ips" class="form-control" placeholder="{{ __('e.g. 203.0.113.10,198.51.100.25') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Generate Token') }}</button>
                                </div>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card no-border mb-3">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Endpoint') }}</h5>
                    <pre class="mb-2 api-endpoint-block"><code>{{ __('POST /api/v1/attendance/logs/bulk') }}</code></pre>
                    <h6>{{ __('Headers') }}</h6>
                    <pre class="mb-2 api-endpoint-block"><code>Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json</code></pre>
                    <h6>{{ __('Payload Example') }}</h6>
                    <pre class="mb-0 api-json-block"><code>{
  "entries": [
    {
      "employee_code": "EMP0001",
      "attendance_date": "2026-03-01",
      "entry_type": "checkin",
      "entry_time": "09:01 AM",
      "remarks": "Device punch in"
    },
    {
      "employee_code": "EMP0001",
      "attendance_date": "2026-03-01",
      "entry_type": "checkout",
      "entry_time": "06:05 PM",
      "remarks": "Device punch out"
    }
  ]
}</code></pre>
                </div>
            </div>

            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('API Clients') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Allowed IPs') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Last Used') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($apiClients as $client)
                                    <tr>
                                        <td>{{ $client->name }}</td>
                                        <td>{{ $client->allowed_ips ?: '-' }}</td>
                                        <td>{{ $client->is_active ? __('Active') : __('Disabled') }}</td>
                                        <td>{{ $client->last_used_at ? $client->last_used_at->format('Y-m-d H:i:s') : '-' }}</td>
                                        <td>
                                            @if($canManageAttendance)
                                                <form method="POST" action="{{ route('attendance.api-clients.toggle', $client) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-custom-default btn-sm" type="submit">
                                                        {{ $client->is_active ? __('Disable') : __('Enable') }}
                                                    </button>
                                                </form>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">{{ __('No API clients configured.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
