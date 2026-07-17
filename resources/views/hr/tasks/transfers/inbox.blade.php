@extends('layouts.backend')
@section('title', 'My Transfer Requests')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-share-alt"></i> {{ __('My Transfer Requests') }}</h1>
        @if(auth()->user()?->hasPermission('task_transfer.view'))
            <a href="{{ route('tasks.transfers.index') }}" class="btn btn-custom-default">{{ __('Transfer Log') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        @forelse($transfers as $transfer)
            <div class="border p-3 mb-2">
                <p><strong>{{ __('Task:') }}</strong> <a href="{{ route('tasks.show', $transfer->task_id) }}">#{{ $transfer->task_id }} {{ $transfer->task?->title }}</a></p>
                <p><strong>{{ __('From:') }}</strong> {{ trim(($transfer->fromEmployee?->first_name ?? '').' '.($transfer->fromEmployee?->last_name ?? '')) }}</p>
                <p><strong>{{ __('Reason:') }}</strong> {{ $transfer->reason }}</p>
                <form method="POST" action="{{ route('tasks.transfers.decide', $transfer) }}" class="d-flex gap-2">
                    @csrf @method('PATCH')
                    <input type="text" name="note" class="form-control" placeholder="{{ __('Optional note') }}">
                    <button type="submit" name="decision" value="accept" class="btn btn-custom">{{ __('Accept') }}</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-custom-default">{{ __('Reject') }}</button>
                </form>
            </div>
        @empty
            <p class="text-muted">{{ __('No pending transfer requests.') }}</p>
        @endforelse
    </div></div></div></div>
</div>
@endsection
