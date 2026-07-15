@extends('layouts.backend')
@section('title', 'Edit Leave Balance')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-pencil"></i> {{ __('Edit Leave Balance') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    @php($employee = $leaveBalance->employee)
                    @php($employeeName = trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')))

                    <div class="alert alert-info mb-3">
                        <strong>{{ __('Employee:') }}</strong> {{ $employeeName !== '' ? $employeeName : '-' }} ({{ $employee?->employee_code ?? '-' }}) |
                        <strong>{{ __('Category:') }}</strong> {{ $leaveBalance->leaveCategory?->name ?? '-' }} |
                        <strong>{{ __('Year:') }}</strong> {{ $leaveBalance->year }}
                    </div>

                    <form method="POST" action="{{ route('leave-balances.update', $leaveBalance) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Opening Balance') }}</label>
                                <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" value="{{ old('opening_balance', $leaveBalance->opening_balance) }}" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Allocated') }}</label>
                                <input type="number" step="0.01" min="0" name="allocated" class="form-control" value="{{ old('allocated', $leaveBalance->allocated) }}" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Carried Forward') }}</label>
                                <input type="number" step="0.01" min="0" name="carried_forward" class="form-control" value="{{ old('carried_forward', $leaveBalance->carried_forward) }}" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Earned Credited') }}</label>
                                <input type="number" step="0.01" min="0" name="earned_credited" class="form-control" value="{{ old('earned_credited', $leaveBalance->earned_credited ?? 0) }}" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Availed') }}</label>
                                <input type="number" step="0.01" min="0" name="availed" class="form-control" value="{{ old('availed', $leaveBalance->availed) }}" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Adjustments (+/-)') }}</label>
                                <input type="number" step="0.01" name="adjustments" class="form-control" value="{{ old('adjustments', $leaveBalance->adjustments) }}" required>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit"><i class="icon-check"></i> {{ __('Update Balance') }}</button>
                        <a href="{{ route('leave-balances.index', ['year' => $leaveBalance->year]) }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
