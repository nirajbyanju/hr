@extends('layouts.backend')

@section('content')
@php
    $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $eventMeta = [
        'generated' => ['label' => __('Generated'), 'class' => 'bg-info'],
        'printed' => ['label' => __('Printed'), 'class' => 'bg-success'],
        'downloaded' => ['label' => __('PDF downloaded'), 'class' => 'bg-primary'],
        'revoked' => ['label' => __('Revoked'), 'class' => 'bg-danger'],
    ];
@endphp
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="fa fa-id-card"></i> {{ __('ID Card') }} — {{ $fullName }}</h1>
        <a href="{{ route('id-cards.index') }}" class="btn btn-outline-secondary btn-sm"><i class="icon-arrow-left"></i> {{ __('Back to list') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="content_wrapper content-padded">
                        <div style="width:306px; max-width:100%; height:500px;">
                            <div style="transform:scale(1.5); transform-origin:top left;">
                                @include('hr.id-cards.partials.card')
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @if($card->isActive())
                                @if($canPrint)
                                    <a href="{{ route('id-cards.print', $card) }}" target="_blank" class="btn btn-custom"><i class="icon-printer"></i> {{ __('Print') }}</a>
                                    <a href="{{ route('id-cards.pdf', $card) }}" class="btn btn-outline-secondary"><i class="icon-cloud-download"></i> {{ __('Download PDF') }}</a>
                                @endif
                                <form method="POST" action="{{ route('id-cards.generate', $employee) }}" onsubmit="return confirm('{{ __('Re-issue a new card? The current card\'s QR will stop working.') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning"><i class="icon-refresh"></i> {{ __('Re-issue') }}</button>
                                </form>
                                @if($canManage)
                                    <form method="POST" action="{{ route('id-cards.revoke', $card) }}" onsubmit="return confirm('{{ __('Revoke this card? Its QR can no longer mark attendance.') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger"><i class="icon-ban"></i> {{ __('Revoke') }}</button>
                                    </form>
                                @endif
                            @else
                                <span class="badge bg-danger align-self-center">{{ __('Revoked') }}</span>
                                <form method="POST" action="{{ route('id-cards.generate', $employee) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-custom"><i class="icon-refresh"></i> {{ __('Generate new card') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="content_wrapper content-padded">
                        <h5 class="mb-3">{{ __('Card details') }}</h5>
                        <table class="table table-sm mb-4">
                            <tbody>
                                <tr><th style="width:40%">{{ __('Card number') }}</th><td>{{ $card->card_number }}</td></tr>
                                <tr><th>{{ __('Status') }}</th><td>
                                    <span class="badge {{ $card->isActive() ? 'bg-success' : 'bg-danger' }}">{{ $card->isActive() ? __('Active') : __('Revoked') }}</span>
                                </td></tr>
                                <tr><th>{{ __('Generated') }}</th><td>{{ optional($card->generated_at)->format('d M Y H:i') }} @if($card->generatedBy) · {{ $card->generatedBy->name }} @endif</td></tr>
                                <tr><th>{{ __('Times printed') }}</th><td>{{ $card->print_count }}</td></tr>
                                <tr><th>{{ __('Last printed') }}</th><td>{{ $card->last_printed_at ? $card->last_printed_at->format('d M Y H:i') : '—' }}</td></tr>
                            </tbody>
                        </table>

                        <h5 class="mb-2">{{ __('Print & generation history') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ __('Event') }}</th>
                                        <th>{{ __('By') }}</th>
                                        <th>{{ __('IP') }}</th>
                                        <th>{{ __('When') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        @php($meta = $eventMeta[$log->event] ?? ['label' => ucfirst($log->event), 'class' => 'bg-secondary'])
                                        <tr>
                                            <td><span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>@if($log->format) <small class="text-muted">({{ strtoupper($log->format) }})</small>@endif</td>
                                            <td>{{ $log->performedBy->name ?? '—' }}</td>
                                            <td><small class="text-muted">{{ $log->ip_address ?? '—' }}</small></td>
                                            <td><small>{{ $log->created_at->format('d M Y H:i') }}</small></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">{{ __('No history yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
