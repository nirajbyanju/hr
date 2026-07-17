@extends('layouts.backend')
@section('title', 'On Leave')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-calendar"></i> {{ __('On Leave') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="mb-3">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('On Leave Today') }} <small class="text-muted">({{ $today }})</small></h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Employee Code') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($onLeaveToday as $application)
                                    @php($empName = trim(($application->employee?->first_name ?? '').' '.($application->employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $empName !== '' ? $empName : '-' }}</td>
                                        <td>{{ $application->employee?->employee_code ?? '-' }}</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>{{ number_format((float) $application->total_days, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">{{ __('No one is on leave today.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Upcoming Leave') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Employee Code') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($upcoming as $application)
                                    @php($empName = trim(($application->employee?->first_name ?? '').' '.($application->employee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $empName !== '' ? $empName : '-' }}</td>
                                        <td>{{ $application->employee?->employee_code ?? '-' }}</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>{{ number_format((float) $application->total_days, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">{{ __('No upcoming approved leave.') }}</td>
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
