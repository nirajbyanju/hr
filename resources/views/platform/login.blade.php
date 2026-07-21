<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform Console — {{ config('app.name', 'SamriddhiHR') }}</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
            font-family:'Segoe UI',system-ui,Roboto,Arial,sans-serif; color:#e7ecea;
            background:radial-gradient(900px 500px at 80% -10%, rgba(45,212,191,.15), transparent 60%), #0d1b1a; padding:20px; }
        .card { width:min(100%,400px); background:#14211f; border:1px solid #24322f; border-radius:18px; padding:34px 30px;
            box-shadow:0 24px 60px rgba(0,0,0,.4); }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:26px; }
        .brand .mark { width:40px; height:40px; border-radius:11px; background:#0f766e; display:flex; align-items:center; justify-content:center; color:#06201d; font-weight:800; font-size:19px; }
        .brand b { font-size:18px; }
        .brand small { display:block; font-weight:500; font-size:10.5px; letter-spacing:.16em; text-transform:uppercase; color:#7fb9b0; }
        h1 { font-size:21px; margin:0 0 5px; }
        .sub { color:#93a6a2; font-size:13.5px; margin:0 0 22px; }
        label { display:block; margin-bottom:15px; }
        label .lab { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#93a6a2; margin-bottom:6px; }
        label:has(:is(input, select, textarea)[required]) .lab::after { content:" *"; color:#f5776b; font-weight:700; }
        input[type=email], input[type=password] { width:100%; height:46px; border:1px solid #2b3a37; border-radius:10px;
            background:#0f1b19; color:#e7ecea; padding:0 13px; font-size:15px; }
        input:focus { outline:none; border-color:#2dd4bf; box-shadow:0 0 0 3px rgba(45,212,191,.14); }
        .btn { width:100%; height:46px; border:none; border-radius:10px; background:#0f766e; color:#fff; font-weight:700; font-size:15px; cursor:pointer; margin-top:4px; }
        .btn:hover { background:#12938a; }
        .err { background:rgba(180,35,24,.15); border:1px solid rgba(245,196,189,.3); color:#ff9c90; border-radius:10px; padding:10px 13px; font-size:13.5px; margin-bottom:18px; }
        .foot { margin-top:20px; font-size:12.5px; color:#6b7d79; text-align:center; }
        .foot a { color:#8fd9ce; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">
            <span class="mark">S</span>
            <span><b>{{ config('app.name', 'SamriddhiHR') }}</b><small>Platform Console</small></span>
        </div>
        <h1>Sign in</h1>
        <p class="sub">Restricted to platform administrators.</p>

        @if($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('platform.login.store') }}">
            @csrf
            <label>
                <span class="lab">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </label>
            <label>
                <span class="lab">Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn">Sign in to console</button>
        </form>

        <div class="foot"><a href="{{ url('/login') }}">← Company login</a></div>
    </div>
</body>
</html>
