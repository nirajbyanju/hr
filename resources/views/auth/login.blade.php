@extends('layouts.auth', ['title' => 'SamriddhiHR | Sign In', 'heading' => 'Sign In', 'authClass' => 'auth-login', 'split' => true])

@section('content')
    <div class="login-minimal-heading">
        <h2>Welcome back</h2>
        <p>Sign in to continue to SamriddhiHR.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="form">
            <div class="form-group">
                <label for="login-email" class="field-label">Email address</label>
                <div class="input-icon-group">
                    <i class="fa fa-envelope-o field-icon" aria-hidden="true"></i>
                    <input type="email" id="login-email" name="email" class="form-control" value="{{ old('email') }}" placeholder="you@company.com" autocomplete="username" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label for="login-password" class="field-label">Password</label>
                <div class="input-icon-group">
                    <i class="fa fa-lock field-icon" aria-hidden="true"></i>
                    <input type="password" id="login-password" name="password" class="form-control" placeholder="Enter password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <input type="checkbox" name="remember" class="form-check-input" id="remember-me" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember-me">Remember Me</label>
            </div>
            <input type="submit" class="btn btn-custom btn-fullwidth" value="Sign In">
        </div>
    </form>

    <div class="login-footer">
        <a href="{{ route('password.request') }}">Forgot Password</a> | <a href="{{ route('register') }}">Register</a>
    </div>

@endsection

@push('scripts')
    <script>
        var passwordToggle = document.getElementById('password-toggle');
        var passwordField = document.getElementById('login-password');
        if (passwordToggle && passwordField) {
            passwordToggle.addEventListener('click', function () {
                var isHidden = passwordField.getAttribute('type') === 'password';
                passwordField.setAttribute('type', isHidden ? 'text' : 'password');
                passwordToggle.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                passwordToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                passwordToggle.querySelector('i').className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
            });
        }
    </script>
@endpush
