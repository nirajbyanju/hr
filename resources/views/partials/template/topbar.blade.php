<noscript>
    <div class="noscriptmsg">We're sorry, but This Software doesn't work properly without JavaScript enabled.</div>
</noscript>

<header class="topbar clearfix">
    @php
        $authUser = auth()->user();
        $avatarPath = $authUser?->employee?->avatar_path ?: 'assets/img/user/default.jpg';
        $notificationPayload = $topbarNotifications ?? ['items' => [], 'count' => 0];
        $notificationItems = collect($notificationPayload['items'] ?? []);
        $notificationCount = (int) ($notificationPayload['count'] ?? $notificationItems->count());
    @endphp
    <nav class="navbar navbar-light app-topbar-nav">
        <div class="app-topbar-left">
            <div class="logo-container app-logo-container">
                <a class="navbar-brand text-start app-logo-link" href="{{ route('dashboard') }}">
                    <img class="app-logo-img" src="{{ asset(config('madpos_ui.logo')) }}" alt="SamriddhiHR" onerror="this.onerror=null;this.src='{{ asset('assets/img/brand-logo.svg') }}';">
                </a>
            </div>

            <ul class="navbar-nav me-2">
                <li class="d-none d-md-block">
                    <a href="#" class="sidebar-toggle"><i class="icon-menu"></i></a>
                </li>
                <li class="d-md-none">
                    <a href="#" id="sidebar-toggle"><i class="icon-menu"></i></a>
                </li>
            </ul>
        </div>

        <div class="app-topbar-right">
            <ul class="navbar-nav ms-auto" style="display: flex; flex-direction: row;">

                        <li class="dropdown d-none d-sm-block topbar-notification-menu">
                            <a href="#" class="dropdown-toggle topbar-hold-trigger" data-bs-toggle="dropdown" role="button" aria-expanded="false" title="{{ __('Notifications') }}">
                                <i class="icon-bell"></i>
                                @if($notificationCount > 0)
                                    <span class="hold-badge">{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>
                                @endif
                            </a>
                            <div class="dropdown-menu dropdown-menu-end hold-notification-dropdown" role="menu">
                                <div class="hold-notification-header">
                                    {{ __('Notifications') }} ({{ $notificationCount }})
                                </div>
                                <div class="hold-notification-list">
                                    @forelse($notificationItems as $notification)
                                        <a href="{{ $notification['url'] ?? '#' }}" class="hold-notification-item">
                                            <div class="d-flex justify-content-between align-items-center gap-3">
                                                <strong><i class="{{ $notification['icon'] ?? 'icon-info' }} me-1"></i>{{ $notification['title'] ?? __('Notification') }}</strong>
                                                <small class="text-muted">{{ $notification['time'] ?? '' }}</small>
                                            </div>
                                            <div class="small text-muted">
                                                {{ $notification['message'] ?? '' }}
                                            </div>
                                        </a>
                                    @empty
                                        <div class="hold-notification-item topbar-notification-empty">
                                            <div class="small text-muted">{{ __('No notifications available.') }}</div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </li>

                        <li class="dropdown topbar-user-menu">
                            <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                                <span class="user-img float-start">
                                    <img alt="user" src="{{ asset($avatarPath) }}">
                                </span>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end topbar-dropdown-wrapper" role="menu">
                                <ul class="dropdown-user-inner">
                                    <li>
                                        <div class="dd-userbox">
                                            <div class="dd-img">
                                                <img alt="user" src="{{ asset($avatarPath) }}">
                                            </div>
                                            <div class="dd-info">
                                                <h4>{{ auth()->user()->name ?? __('User') }}</h4>
                                                <p>{{ auth()->user()->email ?? '' }}</p>
                                            </div>
                                        </div>
                                    </li>

                                    <li class="divider"></li>
                                    @if($authUser?->employee && $authUser->hasPermission('employee.profile-update-request-submit'))
                                        <li><a href="{{ route('employees.profile-updates.create') }}"><i class="icon-note mr10"></i> {{ __('Update Profile') }}</a></li>
                                        <li class="divider"></li>
                                    @endif
                                    <li><a href="{{ route('dashboard.password.edit') }}"><i class="icon-lock mr10"></i> {{ __('Change Password') }}</a></li>
                                    <li class="divider"></li>
                                    <li>
                                        <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="icon-logout mr10"></i> {{ __('Sign Out') }}
                                        </a>
                                        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </li>

            </ul>
        </div>
    </nav>
</header>
