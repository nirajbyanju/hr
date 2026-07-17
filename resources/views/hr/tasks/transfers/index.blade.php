@extends('layouts.backend')
@section('title', 'Task Transfer Log')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-share-alt"></i> {{ __('Task Transfer Log') }}</h1>
        <a href="{{ route('tasks.transfers.inbox') }}" class="btn btn-custom-default">{{ __('My Inbox') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <div class="table-responsive"><table class="table table-bordered align-middle">
            <thead><tr><th>{{ __('Task') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th>{{ __('Requested By') }}</th><th>{{ __('Decided') }}</th></tr></thead>
            <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td><a href="{{ route('tasks.show', $transfer->task_id) }}">#{{ $transfer->task_id }} {{ $transfer->task?->title }}</a></td>
                        <td>{{ trim(($transfer->fromEmployee?->first_name ?? '').' '.($transfer->fromEmployee?->last_name ?? '')) }}</td>
                        <td>{{ trim(($transfer->toEmployee?->first_name ?? '').' '.($transfer->toEmployee?->last_name ?? '')) }}</td>
                        <td>{{ $transfer->reason }}</td>
                        <td><span class="badge {{ $transfer->status === 'accepted' ? 'bg-success' : ($transfer->status === 'rejected' ? 'bg-danger' : 'bg-warning') }}">{{ ucfirst($transfer->status) }}</span></td>
                        <td>{{ $transfer->requestedBy?->name ?? '-' }}</td>
                        <td>{{ $transfer->decided_at ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">{{ __('No transfer requests yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $transfers->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection
