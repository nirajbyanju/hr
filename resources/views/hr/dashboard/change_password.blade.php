@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-lock"></i> {{ __('Change Password') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded-narrow">
                    <form method="POST" action="{{ route('dashboard.password.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group mb-3">
                            <label>{{ __('Old Password') }}</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>

                        <div class="form-group mb-3">
                            <label>{{ __('New Password') }}</label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password">
                        </div>

                        <div class="form-group mb-4">
                            <label>{{ __('Confirm New Password') }}</label>
                            <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-custom">
                            <i class="icon-check"></i> {{ __('Update Password') }}
                        </button>
                        <a href="{{ route('dashboard') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
