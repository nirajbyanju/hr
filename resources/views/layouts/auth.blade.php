<!doctype html>
<html class="no-js" lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="keyword" content="madpos,pos,inventory,invoice,sales,ecommerce,product,stock,customer">
    <meta name="description" content="{{ $metaDescription ?? 'SamriddhiHR authentication' }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name', 'SamriddhiHR') }}</title>
    @php
        $assetsBase = config('madpos_ui.assets_base', 'assets');
        $favicon = config('madpos_ui.favicon', 'assets/img/brand-mark.svg');
        $logo = config('madpos_ui.logo', 'assets/img/brand-logo.svg');
    @endphp
    <link rel="apple-touch-icon" href="{{ asset($favicon) }}">
    <link rel="icon" href="{{ asset($favicon) }}">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset($assetsBase.'/css/normalize.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset($assetsBase.'/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset($assetsBase.'/css/main.css') }}">
    @stack('styles')
</head>
<body>
@if ($split ?? false)
    @php($brandName = config('app.name', 'SamriddhiHR'))
    <div id="wrapper" class="wrapper-login auth-login auth-split">
        <div class="auth-split-grid">
            <aside class="auth-brand">
                <div class="auth-brand-top">
                    <span class="auth-brand-mark">
                        <span class="auth-brand-badge"><i class="fa fa-users" aria-hidden="true"></i></span>
                        <span class="auth-brand-name">{{ $brandName }}</span>
                    </span>
                </div>
                <div class="auth-brand-body">
                    <h1 class="auth-brand-headline">The complete platform for your people.</h1>
                    <p class="auth-brand-sub">Attendance, payroll, leave and projects &mdash; managed in one place, so your team can focus on the work that matters.</p>
                    <ul class="auth-brand-features">
                        <li><i class="fa fa-clock-o" aria-hidden="true"></i><span>Attendance &amp; time tracking</span></li>
                        <li><i class="fa fa-money" aria-hidden="true"></i><span>Payroll &amp; provident fund</span></li>
                        <li><i class="fa fa-calendar-check-o" aria-hidden="true"></i><span>Leave &amp; approvals</span></li>
                        <li><i class="fa fa-tasks" aria-hidden="true"></i><span>Tasks &amp; projects</span></li>
                    </ul>
                </div>
                <div class="auth-brand-foot">&copy; {{ date('Y') }} {{ $brandName }}. All rights reserved.</div>
            </aside>
            <section class="auth-panel">
                <div class="auth-panel-inner">
                    <div class="auth-panel-brand">
                        <span class="auth-brand-badge"><i class="fa fa-users" aria-hidden="true"></i></span>
                        <span class="auth-brand-name">{{ $brandName }}</span>
                    </div>
                    <div class="form-wrapper">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                </div>
            </section>
        </div>
    </div>
@else
    <div id="wrapper" class="wrapper-login {{ $authClass ?? '' }}">
        <div class="login-inner">
            <div class="auth-logo">
                <img src="{{ asset($logo) }}" alt="{{ config('app.name', 'SamriddhiHR') }}" onerror="this.onerror=null;this.src='{{ asset('assets/img/brand-logo.svg') }}';">
            </div>
            <div class="card mb-1">
                <div class="card-body p2050">
                    <div class="form-header">
                        <h5>{{ $heading ?? 'Authentication' }}</h5>
                        @isset($subtitle)
                            <p class="auth-subtitle">{{ $subtitle }}</p>
                        @endisset
                    </div>
                </div>
            </div>
            <div class="form-wrapper">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>
@endif

    <script src="{{ asset($assetsBase.'/js/vendor/modernizr-3.5.0.min.js') }}"></script>
    <script src="{{ asset($assetsBase.'/js/vendor/jquery-3.2.1.min.js') }}"></script>
    <script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset($assetsBase.'/js/bootstrap5-bridge.js') }}"></script>
    @stack('scripts')
</body>
</html>
