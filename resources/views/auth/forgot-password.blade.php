@extends('layouts.auth', ['title' => 'SamriddhiHR | Reset Password', 'heading' => 'Reset Password', 'subtitle' => 'Enter your email address and we will help you reset access.'])

@section('content')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form">
            <div class="form-group">
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Enter email" required autofocus>
            </div>
            <input type="submit" class="btn btn-custom btn-fullwidth" value="Submit">
        </div>
    </form>

    <div class="login-footer">
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
@endsection
