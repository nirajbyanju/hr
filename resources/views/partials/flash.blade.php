@php
    $flashMessages = [];

    if (session('status')) {
        $flashMessages[] = ['type' => 'success', 'title' => __('Success'), 'message' => (string) session('status')];
    }
    if (session('success')) {
        $flashMessages[] = ['type' => 'success', 'title' => __('Success'), 'message' => (string) session('success')];
    }
    if (session('error')) {
        $flashMessages[] = ['type' => 'error', 'title' => __('Error'), 'message' => (string) session('error')];
    }
    if (session('warning')) {
        $flashMessages[] = ['type' => 'warning', 'title' => __('Warning'), 'message' => (string) session('warning')];
    }
    if (session('info')) {
        $flashMessages[] = ['type' => 'info', 'title' => __('Information'), 'message' => (string) session('info')];
    }
@endphp

@if (! empty($flashMessages) || (isset($errors) && $errors->any()))
    <div class="app-flash-stack">
        @foreach ($flashMessages as $flash)
            @php
                $type = $flash['type'];
                $icon = match ($type) {
                    'success' => 'icon-check',
                    'warning' => 'icon-exclamation',
                    'info' => 'icon-info',
                    default => 'icon-close',
                };
                $autoHide = in_array($type, ['success', 'info'], true) ? '1' : '0';
            @endphp

            <div class="app-flash app-flash-{{ $type }} alert alert-dismissible fade show" role="alert" data-auto-hide="{{ $autoHide }}">
                <div class="app-flash-icon"><i class="{{ $icon }}"></i></div>
                <div class="app-flash-content">
                    <div class="app-flash-title">{{ $flash['title'] }}</div>
                    <div class="app-flash-message">{{ $flash['message'] }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endforeach

        @if (isset($errors) && $errors->any())
            <div class="app-flash app-flash-error alert alert-dismissible fade show" role="alert" data-auto-hide="0">
                <div class="app-flash-icon"><i class="icon-close"></i></div>
                <div class="app-flash-content">
                    <div class="app-flash-title">{{ __('Validation Error') }}</div>
                    <ul class="app-flash-list mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif
    </div>

    <script>
        (function () {
            var alerts = document.querySelectorAll('.app-flash[data-auto-hide="1"]');
            alerts.forEach(function (el) {
                setTimeout(function () {
                    if (window.bootstrap && window.bootstrap.Alert) {
                        window.bootstrap.Alert.getOrCreateInstance(el).close();
                        return;
                    }
                    el.classList.remove('show');
                    el.remove();
                }, 5000);
            });
        })();
    </script>
@endif
