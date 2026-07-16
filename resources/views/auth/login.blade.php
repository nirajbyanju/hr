@extends('layouts.auth', ['title' => 'SamriddhiHR | Sign In', 'heading' => 'Sign In', 'authClass' => 'auth-login'])

@section('content')
    @php($demoPassword = config('demo_users.password', 'P@ssword'))
    @php($demoAccounts = config('demo_users.accounts', []))
    @php($roleIcons = [
        'admin' => 'fa-user',
        'hr-admin' => 'fa-users',
        'department-head' => 'fa-sitemap',
        'employee' => 'fa-user-circle-o',
    ])

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

    @if(! empty($demoAccounts))
        <div class="auth-divider">Try a demo account</div>
        <div class="demo-login-panel">
            <div class="demo-login-grid">
                @foreach($demoAccounts as $account)
                    <button
                        type="button"
                        class="demo-copy-btn"
                        data-email="{{ $account['email'] }}"
                        data-password="{{ $demoPassword }}"
                    >
                        <i class="fa {{ $roleIcons[$account['role_slug']] ?? 'fa-user' }}" aria-hidden="true"></i>
                        <span>{{ $account['label'] }}</span>
                        <small>Copy</small>
                    </button>
                @endforeach
            </div>
            <div class="demo-login-note">Default password: <code>{{ $demoPassword }}</code></div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.demo-copy-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var email = button.getAttribute('data-email');
                var password = button.getAttribute('data-password');
                var emailInput = document.getElementById('login-email');
                var passwordInput = document.getElementById('login-password');

                if (emailInput) {
                    emailInput.value = email;
                }

                if (passwordInput) {
                    passwordInput.value = password;
                }

                var text = 'Email: ' + email + '\nPassword: ' + password;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text);
                }

                var label = button.querySelector('small');
                if (label) {
                    label.textContent = 'Copied';
                }

                setTimeout(function () {
                    if (label) {
                        label.textContent = 'Copy';
                    }
                }, 1400);
            });
        });

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
