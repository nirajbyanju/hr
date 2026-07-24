@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-screen-smartphone"></i> {{ __('Attendance Scanner') }}</h1>
        <a href="{{ route('id-cards.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-id-card"></i> {{ __('ID Cards') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @include('hr.attendance.partials.method-nav')

            <div id="scan-result" class="scan-result mb-3" style="display:none;"></div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="content_wrapper content-padded h-100">
                        <h5 class="mb-2"><i class="icon-camera"></i> {{ __('Camera scan') }}</h5>
                        <p class="text-muted small">{{ __('Point an employee ID card at the camera to record check-in / check-out.') }}</p>
                        <div style="position:relative; background:#0d1117; border-radius:12px; overflow:hidden; aspect-ratio:4/3;">
                            <video id="scan-video" playsinline muted style="width:100%; height:100%; object-fit:cover; display:block;"></video>
                            <div style="position:absolute; inset:18% 22%; border:3px solid rgba(255,255,255,.7); border-radius:14px; pointer-events:none;"></div>
                        </div>
                        <canvas id="scan-canvas" hidden></canvas>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" id="cam-start" class="btn btn-custom"><i class="icon-control-play"></i> {{ __('Start camera') }}</button>
                            <button type="button" id="cam-stop" class="btn btn-outline-secondary" disabled><i class="icon-control-pause"></i> {{ __('Stop') }}</button>
                        </div>
                        <div id="cam-status" class="small text-muted mt-2"></div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="content_wrapper content-padded h-100">
                        <h5 class="mb-2"><i class="icon-magic-wand"></i> {{ __('USB scanner / manual') }}</h5>
                        <p class="text-muted small">{{ __('Use a USB QR scanner or type the card token, then press Enter.') }}</p>
                        <form id="manual-form" autocomplete="off">
                            <input type="text" id="manual-input" class="form-control form-control-lg" placeholder="{{ __('Scan or paste card token…') }}" autofocus>
                            <button type="submit" class="btn btn-custom mt-3 w-100"><i class="icon-check"></i> {{ __('Record attendance') }}</button>
                        </form>
                        <div class="mt-4">
                            <h6 class="text-muted">{{ __('How it works') }}</h6>
                            <ul class="small text-muted mb-0">
                                <li>{{ __('One scan checks the employee in; the next scan checks them out.') }}</li>
                                <li>{{ __('A card must be active — revoked cards are rejected.') }}</li>
                                <li>{{ __('Repeat scans within 45 seconds are ignored.') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .scan-result { border-radius:12px; padding:16px 20px; font-weight:600; display:flex; align-items:center; gap:14px; }
    .scan-result .sr-icon { font-size:26px; line-height:1; }
    .scan-result .sr-name { font-size:18px; }
    .scan-result .sr-sub { font-weight:400; opacity:.85; font-size:13px; }
    .scan-result.sr-checkin { background:#ecfdf3; color:#067647; border:1px solid #b3e5c6; }
    .scan-result.sr-checkout { background:#eff6ff; color:#175cd3; border:1px solid #bcd6f7; }
    .scan-result.sr-error { background:#fef3f2; color:#b42318; border:1px solid #f5c4bd; }
</style>

<script src="{{ asset('assets/js/vendor/jsqr.min.js') }}"></script>
<script>
(function () {
    const SCAN_URL = @json(route('attendance.scan.submit'));
    const CSRF = @json(csrf_token());

    const resultEl = document.getElementById('scan-result');
    const video = document.getElementById('scan-video');
    const canvas = document.getElementById('scan-canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const camStatus = document.getElementById('cam-status');
    const camStart = document.getElementById('cam-start');
    const camStop = document.getElementById('cam-stop');
    const manualForm = document.getElementById('manual-form');
    const manualInput = document.getElementById('manual-input');

    let stream = null;
    let scanning = false;
    let busy = false;
    let lastToken = '';
    let lastAt = 0;

    function showResult(kind, title, sub) {
        resultEl.className = 'scan-result mb-3 sr-' + kind;
        const icon = kind === 'checkin' ? '✓' : (kind === 'checkout' ? '↩' : '✕');
        resultEl.innerHTML =
            '<span class="sr-icon">' + icon + '</span>' +
            '<span><span class="sr-name">' + title + '</span><br><span class="sr-sub">' + sub + '</span></span>';
        resultEl.style.display = 'flex';
    }

    async function submitToken(token) {
        token = (token || '').trim();
        if (!token || busy) return;
        // Client-side debounce for the camera firing the same code repeatedly.
        const now = Date.now();
        if (token === lastToken && (now - lastAt) < 4000) return;
        lastToken = token; lastAt = now;

        busy = true;
        try {
            const res = await fetch(SCAN_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ token })
            });
            const data = await res.json();
            if (data.success) {
                showResult(data.action === 'checkin' ? 'checkin' : 'checkout',
                    data.employee + ' — ' + data.message,
                    data.employee_code + ' · ' + data.time);
            } else {
                showResult('error', data.message || 'Scan failed', '');
            }
        } catch (e) {
            showResult('error', 'Network error. Please try again.', '');
        } finally {
            busy = false;
        }
    }

    // ---- Camera ----
    function tick() {
        if (!scanning) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA && typeof jsQR === 'function') {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data) { submitToken(code.data); }
        }
        requestAnimationFrame(tick);
    }

    async function startCamera() {
        if (typeof jsQR !== 'function') { camStatus.textContent = 'QR library failed to load.'; return; }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream;
            await video.play();
            scanning = true;
            camStart.disabled = true; camStop.disabled = false;
            camStatus.textContent = 'Camera running — hold a card steady inside the frame.';
            requestAnimationFrame(tick);
        } catch (e) {
            camStatus.textContent = 'Cannot access camera (' + (e && e.name ? e.name : 'error') + '). Use the manual / USB option.';
        }
    }

    function stopCamera() {
        scanning = false;
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        camStart.disabled = false; camStop.disabled = true;
        camStatus.textContent = 'Camera stopped.';
    }

    camStart.addEventListener('click', startCamera);
    camStop.addEventListener('click', stopCamera);

    // ---- Manual / USB ----
    manualForm.addEventListener('submit', function (e) {
        e.preventDefault();
        submitToken(manualInput.value);
        manualInput.value = '';
        manualInput.focus();
    });

    window.addEventListener('beforeunload', stopCamera);
})();
</script>
@endsection
