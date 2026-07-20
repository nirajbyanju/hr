@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="fa fa-id-card"></i> {{ __('My ID Card') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if($card)
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="content_wrapper content-padded">
                            <div style="width:306px; max-width:100%; height:500px;">
                                <div style="transform:scale(1.5); transform-origin:top left;">
                                    @include('hr.id-cards.partials.card')
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <a href="{{ route('my.id-card.pdf') }}" class="btn btn-custom">
                                    <i class="icon-cloud-download"></i> {{ __('Download PDF') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="content_wrapper content-padded">
                            <h5 class="mb-3">{{ __('Card details') }}</h5>
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th style="width:40%;">{{ __('Card number') }}</th>
                                        <td>{{ $card->card_number }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Status') }}</th>
                                        <td><span class="badge bg-success">{{ __('Active') }}</span></td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Issued on') }}</th>
                                        <td>{{ $card->generated_at?->format('d M Y, h:i A') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>{{ __('Employee code') }}</th>
                                        <td>{{ $employee->employee_code ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <p class="text-muted small mt-3 mb-0">
                                {{ __('This card is issued by HR. If the details are wrong, or the card is lost or damaged, contact HR to have it re-issued — you cannot change or re-issue it yourself.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @else
                <div class="content_wrapper content-padded text-center py-5">
                    <i class="fa fa-id-card fa-3x text-muted mb-3"></i>
                    <h5>{{ __('No ID card has been issued to you yet') }}</h5>
                    <p class="text-muted mb-0">
                        @if(! $hasEmployeeRecord)
                            {{ __('Your account is not linked to an employee record. Please contact HR.') }}
                        @else
                            {{ __('Once HR generates your ID card it will appear here, ready to view and download.') }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
