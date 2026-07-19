<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Platform Console') — {{ config('app.name', 'SamriddhiHR') }}</title>
    <style>
        :root {
            --bg:#eef1f4; --surface:#fff; --ink:#14181d; --ink-2:#5b6572; --ink-3:#8a95a1;
            --line:#e2e6ea; --accent:#0f766e; --accent-2:#0b5c55; --accent-soft:#e2f1ef;
            --ok:#067647; --ok-bg:#ecfdf3; --ok-line:#b3e5c6;
            --warn:#b54708; --warn-bg:#fffaeb; --warn-line:#f2dda0;
            --danger:#b42318; --danger-bg:#fef3f2; --danger-line:#f5c4bd;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink);
            font-family:'Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif; font-size:15px; line-height:1.5; }
        a { color:var(--accent); text-decoration:none; }
        .topbar { background:#0d1b1a; color:#fff; }
        .topbar-inner { max-width:1120px; margin:0 auto; padding:0 20px; height:58px; display:flex; align-items:center; gap:22px; }
        .brand { display:flex; align-items:center; gap:10px; font-weight:700; }
        .brand .mark { width:28px; height:28px; border-radius:8px; background:var(--accent); display:flex; align-items:center; justify-content:center; color:#06201d; font-weight:800; }
        .brand small { display:block; font-weight:500; font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:#7fb9b0; }
        .topbar nav { display:flex; gap:6px; margin-left:12px; }
        .topbar nav a { color:#cdd6d4; padding:6px 12px; border-radius:8px; font-size:14px; }
        .topbar nav a:hover, .topbar nav a.active { background:rgba(255,255,255,.09); color:#fff; }
        .topbar .spacer { flex:1; }
        .topbar .who { color:#9fb0ad; font-size:13px; }
        .btn { display:inline-flex; align-items:center; gap:7px; border:1px solid transparent; border-radius:9px;
            padding:9px 15px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; background:var(--surface); color:var(--ink); }
        .btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
        .btn-primary:hover { background:var(--accent-2); }
        .btn-ghost { background:transparent; border-color:var(--line); color:var(--ink-2); }
        .btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
        .btn-danger { background:transparent; border-color:var(--danger-line); color:var(--danger); }
        .btn-danger:hover { background:var(--danger-bg); }
        .btn-sm { padding:6px 11px; font-size:13px; border-radius:8px; }
        .btn-logout { background:transparent; border:1px solid rgba(255,255,255,.18); color:#cdd6d4; }
        .btn-logout:hover { background:rgba(255,255,255,.08); color:#fff; }
        .wrap { max-width:1120px; margin:0 auto; padding:28px 20px 64px; }
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        .page-head h1 { font-size:23px; margin:0; }
        .page-head p { margin:3px 0 0; color:var(--ink-2); font-size:14px; }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:14px; }
        .flash { border-radius:11px; padding:13px 16px; margin-bottom:18px; font-size:14px; font-weight:500; border:1px solid; }
        .flash-ok { background:var(--ok-bg); color:var(--ok); border-color:var(--ok-line); }
        .flash-err { background:var(--danger-bg); color:var(--danger); border-color:var(--danger-line); }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:14px; margin-bottom:24px; }
        .stat { background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
        .stat .n { font-size:26px; font-weight:800; letter-spacing:-.02em; }
        .stat .l { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-top:2px; }
        .tbl-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:14px; min-width:920px; }
        thead th { text-align:left; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-3);
            font-weight:600; padding:13px 16px; border-bottom:1px solid var(--line); }
        tbody td { padding:13px 16px; border-bottom:1px solid var(--line); vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        .co-name { font-weight:700; }
        .co-host { font-family:ui-monospace,'Cascadia Code',Consolas,monospace; font-size:12.5px; color:var(--ink-2); }
        .pill { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; letter-spacing:.04em;
            text-transform:uppercase; padding:3px 9px; border-radius:999px; border:1px solid; }
        .pill-ok { color:var(--ok); background:var(--ok-bg); border-color:var(--ok-line); }
        .pill-off { color:var(--warn); background:var(--warn-bg); border-color:var(--warn-line); }
        .pill-pending { color:#175cd3; background:#eff8ff; border-color:#b2ddff; }
        .pill-expired { color:var(--danger); background:var(--danger-bg); border-color:var(--danger-line); }
        .pill-default { color:var(--accent-2); background:var(--accent-soft); border-color:#bfe0da; }
        .row-actions { display:flex; gap:6px; justify-content:flex-end; }
        form.inline { display:inline; }
        label.field { display:block; margin-bottom:16px; }
        label.field .lab { display:block; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--ink-2); margin-bottom:6px; }
        .input { width:100%; height:44px; border:1px solid var(--line); border-radius:10px; padding:0 13px; font-size:15px; background:#fff; color:var(--ink); }
        .input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .help { font-size:12.5px; color:var(--ink-3); margin-top:5px; }
        .err { color:var(--danger); font-size:12.5px; margin-top:5px; }
        .form-card { padding:0; max-width:780px; overflow:hidden; }
        .form-card form { display:block; }
        .form-section { padding:24px 28px; border-bottom:1px solid var(--line); }
        .form-section:last-of-type { border-bottom:none; }
        .section-title { margin-bottom:18px; }
        .section-title h2 { margin:0; font-size:17px; }
        .section-title p { margin:3px 0 0; color:var(--ink-2); font-size:13.5px; }
        .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .form-actions { display:flex; gap:10px; padding:20px 28px 24px; background:#f8fafb; border-top:1px solid var(--line); }
        @media (max-width:620px){
            .form-section, .form-actions { padding-left:18px; padding-right:18px; }
            .grid-2 { grid-template-columns:1fr; gap:0; }
            .form-actions { flex-direction:column; }
            .form-actions .btn { justify-content:center; }
        }
        .prefixed { display:flex; align-items:center; border:1px solid var(--line); border-radius:10px; overflow:hidden; }
        .prefixed .input { border:none; border-radius:0; }
        .prefixed .suffix { padding:0 12px; color:var(--ink-3); font-family:ui-monospace,Consolas,monospace; font-size:13px; white-space:nowrap; background:#f5f7f8; height:44px; display:flex; align-items:center; border-left:1px solid var(--line); }
        :focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <span class="mark">S</span>
                <span>{{ config('app.name', 'SamriddhiHR') }}<small>Platform Console</small></span>
            </div>
            <nav>
                <a href="{{ route('platform.dashboard') }}" class="{{ request()->routeIs('platform.dashboard') ? 'active' : '' }}">Companies</a>
            </nav>
            <span class="spacer"></span>
            <span class="who">{{ auth()->user()?->email }}</span>
            <form method="POST" action="{{ route('platform.logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-logout">Sign out</button>
            </form>
        </div>
    </header>

    <div class="wrap">
        @if(session('success'))<div class="flash flash-ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash flash-err">{{ session('error') }}</div>@endif
        @yield('content')
    </div>
</body>
</html>
