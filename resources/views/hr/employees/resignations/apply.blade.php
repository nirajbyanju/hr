@extends('layouts.backend')
@section('title', 'Resignation Apply')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-logout"></i> {{ __('Resignation Apply') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if(! $employee)
                <div class="alert alert-danger">{{ __('Your account is not linked with an employee profile.') }}</div>
            @else
                <div class="mb-3">
                    <div class="content_wrapper content-padded">
                        <h5 class="table_banner_title mb-3">{{ __('Submit Resignation Request') }}</h5>
                        <form method="POST" action="{{ route('employee-resignations.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <x-date-field name="notice_date" :label="__('Notice Date')" wrapper-class="" />
                            </div>
                            <div class="col-md-3">
                                {{-- Mirrors after_or_equal:today on StoreEmployeeResignationRequest. --}}
                                <x-date-field name="requested_last_working_day" :label="__('Requested Last Working Day')"
                                              min-from="today" wrapper-class="" required />
                            </div>
                            <div class="col-md-12">
                                <label>{{ __('Reason') }}</label>
                                <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                            </div>
                            <div class="col-md-12">
                                <label>{{ __('Handover Notes') }}</label>
                                <textarea name="handover_notes" class="form-control" rows="3">{{ old('handover_notes') }}</textarea>
                            </div>
                            <div class="col-md-12 mt-2">
                                <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Submit Resignation') }}</button>
                            </div>
                    </form>
                </div>
                </div>
            @endif


            <div>
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Resignation Requests') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Applied At') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Supervisor') }}</th>
                                    <th>{{ __('Notice Date') }}</th>
                                    <th>{{ __('Requested LWD') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Supervisor Remarks') }}</th>
                                    <th>{{ __('Final Remarks') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $item)
                                    @php($employeeName = trim(($item->employee?->first_name ?? '') . ' ' . ($item->employee?->last_name ?? '')))
                                    @php($supervisorName = trim(($item->supervisorEmployee?->first_name ?? '') . ' ' . ($item->supervisorEmployee?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>{{ $employeeName !== '' ? $employeeName : '-' }} ({{ $item->employee?->employee_code ?? '-' }})</td>
                                        <td>{{ $supervisorName !== '' ? $supervisorName : '-' }}</td>
                                        <td>{{ $item->notice_date ?? '-' }}</td>
                                        <td>{{ $item->requested_last_working_day }}</td>
                                        <td>
                                            @if($item->status === 'pending_supervisor')
                                                <span class="badge bg-warning text-dark">{{ __('Pending Supervisor') }}</span>
                                            @elseif($item->status === 'pending_final')
                                                <span class="badge bg-info text-dark">{{ __('Pending Final') }}</span>
                                            @elseif($item->status === 'approved')
                                                <span class="badge bg-success">{{ __('Approved') }}</span>
                                            @elseif($item->status === 'supervisor_rejected' || $item->status === 'final_rejected')
                                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $item->status }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->supervisor_remarks ?: '-' }}</td>
                                        <td>{{ $item->final_remarks ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('No resignation requests found.') }}</td>
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

