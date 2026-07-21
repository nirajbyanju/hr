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
            --shell:#0d1b1a; --shell-2:#132725; --shell-ink:#cdd6d4; --shell-ink-2:#7fb9b0;
            --sidebar:248px;
        }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink);
            font-family:'Segoe UI',system-ui,-apple-system,Roboto,Arial,sans-serif; font-size:15px; line-height:1.5; }
        a { color:var(--accent); text-decoration:none; }

        /* ---- Shell: fixed sidebar, content offset to match ---- */
        .sidebar { position:fixed; inset:0 auto 0 0; width:var(--sidebar); background:var(--shell); color:var(--shell-ink);
            display:flex; flex-direction:column; z-index:20; }
        .brand { display:flex; align-items:center; gap:11px; padding:19px 20px; border-bottom:1px solid rgba(255,255,255,.07); color:#fff; font-weight:700; }
        .brand .mark { width:30px; height:30px; border-radius:9px; background:var(--accent); display:flex; align-items:center;
            justify-content:center; color:#06201d; font-weight:800; flex:none; }
        .brand small { display:block; font-weight:500; font-size:10.5px; letter-spacing:.14em; text-transform:uppercase; color:var(--shell-ink-2); }
        .side-nav { flex:1; overflow-y:auto; padding:16px 12px; }
        .nav-group { font-size:10.5px; letter-spacing:.12em; text-transform:uppercase; color:#6f8480; font-weight:700;
            padding:14px 10px 7px; }
        .nav-group:first-child { padding-top:2px; }
        .side-nav a { display:flex; align-items:center; gap:10px; color:var(--shell-ink); padding:9px 11px; border-radius:9px;
            font-size:14.5px; margin-bottom:2px; }
        .side-nav a:hover { background:rgba(255,255,255,.07); color:#fff; }
        .side-nav a.active { background:var(--accent); color:#fff; font-weight:600; }
        .side-nav a .ico { width:17px; text-align:center; flex:none; opacity:.9; }
        .side-foot { border-top:1px solid rgba(255,255,255,.07); padding:14px; }
        .side-user { display:flex; align-items:center; gap:10px; margin-bottom:11px; min-width:0; }
        .side-user .who { min-width:0; }
        .side-user .who > b { display:block; color:#fff; font-size:13.5px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .side-user .who > span { display:block; color:#8fa3a0; font-size:11.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .btn-logout { background:transparent; border:1px solid rgba(255,255,255,.18); color:var(--shell-ink); width:100%; justify-content:center; }
        .btn-logout:hover { background:rgba(255,255,255,.08); color:#fff; }
        .wrap { margin-left:var(--sidebar); padding:28px 30px 64px; max-width:1320px; }

        /* ---- Buttons ---- */
        .btn { display:inline-flex; align-items:center; gap:7px; border:1px solid transparent; border-radius:9px;
            padding:9px 15px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; background:var(--surface); color:var(--ink); }
        .btn-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
        .btn-primary:hover { background:var(--accent-2); }
        .btn-ghost { background:transparent; border-color:var(--line); color:var(--ink-2); }
        .btn-ghost:hover { border-color:var(--accent); color:var(--accent); }
        .btn-danger { background:transparent; border-color:var(--danger-line); color:var(--danger); }
        .btn-danger:hover { background:var(--danger-bg); }
        .btn-sm { padding:6px 11px; font-size:13px; border-radius:8px; }
        .btn[disabled], .btn.is-disabled { opacity:.5; cursor:not-allowed; pointer-events:none; }

        /* ---- Page furniture ---- */
        .page-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; flex-wrap:wrap; }
        .page-head h1 { font-size:23px; margin:0; }
        .page-head p { margin:3px 0 0; color:var(--ink-2); font-size:14px; }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:14px; }
        .flash { border-radius:11px; padding:13px 16px; margin-bottom:18px; font-size:14px; font-weight:500; border:1px solid; }
        .flash-ok { background:var(--ok-bg); color:var(--ok); border-color:var(--ok-line); }
        .flash-err { background:var(--danger-bg); color:var(--danger); border-color:var(--danger-line); }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:22px; }
        .stat { background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:16px 18px; }
        .stat .n { font-size:26px; font-weight:800; letter-spacing:-.02em; }
        .stat .l { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-3); margin-top:2px; }
        .toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:-6px 0 16px; }

        /* ---- Search + filter bar ---- */
        .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:center; padding:14px 16px; border-bottom:1px solid var(--line); }
        .filters .search { position:relative; flex:1; min-width:220px; }
        .filters .search .input { padding-left:38px; height:40px; }
        .filters .search .mag { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--ink-3); font-size:14px; pointer-events:none; }
        .filters select.input { height:40px; width:auto; min-width:150px; }
        .filter-chip { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-2);
            background:var(--accent-soft); border:1px solid #bfe0da; border-radius:999px; padding:3px 10px; }

        /* ---- Tables ---- */
        .tbl-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:14px; min-width:920px; }
        thead th { text-align:left; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--ink-3);
            font-weight:600; padding:13px 16px; border-bottom:1px solid var(--line); background:#fbfcfd; }
        tbody td { padding:13px 16px; border-bottom:1px solid var(--line); vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#fafbfc; }
        .co-name { font-weight:700; }
        .co-host { font-family:ui-monospace,'Cascadia Code',Consolas,monospace; font-size:12.5px; color:var(--ink-2); }
        .row-actions { display:flex; gap:6px; justify-content:flex-end; }
        form.inline { display:inline; }
        .nowrap { white-space:nowrap; }

        /* ---- Identity ---- */
        .avatar { width:36px; height:36px; border-radius:50%; background:var(--accent-soft); color:var(--accent-2);
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex:none; letter-spacing:.02em; }
        .avatar-sm { width:30px; height:30px; font-size:11.5px; }
        .avatar-lg { width:62px; height:62px; font-size:21px; }
        .avatar-dark { background:rgba(255,255,255,.12); color:#fff; }
        .idcell { display:flex; align-items:center; gap:11px; min-width:0; }
        /* Direct children only — a nested .pill inside the name must keep its
           own inline-flex layout rather than being stretched to a block. */
        .idcell .who > b { display:block; font-weight:700; }
        .idcell .who > span { display:block; font-size:12.5px; color:var(--ink-2); }

        /* ---- Badges ---- */
        .pill { display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; letter-spacing:.04em;
            text-transform:uppercase; padding:3px 9px; border-radius:999px; border:1px solid; white-space:nowrap; }
        .pill-ok { color:var(--ok); background:var(--ok-bg); border-color:var(--ok-line); }
        .pill-off { color:var(--warn); background:var(--warn-bg); border-color:var(--warn-line); }
        .pill-pending { color:#175cd3; background:#eff8ff; border-color:#b2ddff; }
        .pill-expired { color:var(--danger); background:var(--danger-bg); border-color:var(--danger-line); }
        .pill-default { color:var(--accent-2); background:var(--accent-soft); border-color:#bfe0da; }

        /* ---- Empty state ---- */
        .empty { text-align:center; padding:52px 24px; }
        .empty .ico { font-size:30px; opacity:.35; }
        .empty h3 { margin:12px 0 4px; font-size:16px; }
        .empty p { margin:0 0 16px; color:var(--ink-2); font-size:14px; }

        /* ---- Pagination ---- */
        .pager { display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:13px 16px; border-top:1px solid var(--line); flex-wrap:wrap; }
        .pager .help { margin:0; }
        .pager nav { display:flex; }
        .pager svg { width:16px; height:16px; }
        .pager a, .pager span[aria-current], .pager span[aria-disabled] {
            display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 9px;
            border:1px solid var(--line); border-radius:8px; margin-left:5px; font-size:13.5px; color:var(--ink-2); background:var(--surface); }
        .pager a:hover { border-color:var(--accent); color:var(--accent); }
        .pager span[aria-current] { background:var(--accent); border-color:var(--accent); color:#fff; font-weight:700; }
        .pager span[aria-disabled] { opacity:.45; }
        .pager p { margin:0; font-size:13px; color:var(--ink-3); }
        .pager p span, .pager p .font-medium { font-weight:600; color:var(--ink-2); }

        /* ---- Detail view ---- */
        .split { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:18px; align-items:start; }
        .card-head { display:flex; align-items:center; gap:14px; padding:20px 22px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
        .card-head h2 { margin:0; font-size:18px; }
        .card-head .sub { margin:2px 0 0; color:var(--ink-2); font-size:13.5px; }
        .card-body { padding:6px 22px 18px; }
        .kv { display:flex; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid var(--line); font-size:14px; }
        .kv:last-child { border-bottom:none; }
        .kv dt { color:var(--ink-2); }
        .kv dd { margin:0; text-align:right; font-weight:600; }
        .kv dd small { display:block; font-weight:400; font-size:12px; color:var(--ink-3); }
        .stack { display:flex; flex-direction:column; gap:8px; padding:16px 18px; }
        .stack .btn { justify-content:flex-start; }
        .note { font-size:13px; color:var(--ink-2); background:var(--warn-bg); border:1px solid var(--warn-line);
            border-radius:10px; padding:11px 13px; }

        /* ---- Forms ---- */
        .field { display:block; margin-bottom:16px; }
        .field .lab { display:block; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--ink-2); margin-bottom:6px; }
        /* Required marker, driven by the field's own `required` attribute. */
        .field:has(:is(input, select, textarea)[required]) .lab::after { content:" *"; color:var(--danger); font-weight:700; }
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
        .check { display:flex; align-items:flex-start; gap:11px; padding:13px 15px; border:1px solid var(--line); border-radius:11px; cursor:pointer; }
        .check input { width:17px; height:17px; margin:2px 0 0; accent-color:var(--accent); flex:none; }
        .check b { display:block; font-size:14px; }
        .check .help { margin-top:2px; }
        .prefixed { display:flex; align-items:center; border:1px solid var(--line); border-radius:10px; overflow:hidden; }
        .prefixed .input { border:none; border-radius:0; }
        .prefixed .suffix { padding:0 12px; color:var(--ink-3); font-family:ui-monospace,Consolas,monospace; font-size:13px; white-space:nowrap; background:#f5f7f8; height:44px; display:flex; align-items:center; border-left:1px solid var(--line); }

        /* ---- Usage meter (reports) ---- */
        .meter { width:104px; height:6px; border-radius:999px; background:var(--line); overflow:hidden; margin-top:5px; }
        .meter i { display:block; height:100%; border-radius:999px; background:var(--accent); }
        .meter.is-warn i { background:#f79009; }
        .meter.is-full i { background:var(--danger); }
        .seats { font-weight:700; }
        .seats-sub { font-size:12px; color:var(--ink-3); }

        :focus-visible { outline:2px solid var(--accent); outline-offset:2px; }

        /* ---- Responsive: sidebar becomes a top bar ---- */
        @media (max-width:900px) {
            .sidebar { position:static; width:auto; flex-direction:row; align-items:center; gap:10px; padding-right:12px; flex-wrap:wrap; }
            .brand { border-bottom:none; padding:14px 16px; }
            .side-nav { flex:1 0 100%; order:3; display:flex; gap:4px; overflow-x:auto; padding:0 12px 12px; }
            .side-nav a { white-space:nowrap; margin:0; }
            .nav-group { display:none; }
            .side-foot { border-top:none; padding:0 4px 0 0; margin-left:auto; display:flex; align-items:center; gap:10px; }
            .side-user { margin-bottom:0; }
            .side-user .who { display:none; }
            .btn-logout { width:auto; }
            .wrap { margin-left:0; padding:22px 18px 56px; }
            .split { grid-template-columns:1fr; }
        }
        @media (max-width:620px) {
            .form-section, .form-actions { padding-left:18px; padding-right:18px; }
            .grid-2 { grid-template-columns:1fr; gap:0; }
            .form-actions { flex-direction:column; }
            .form-actions .btn { justify-content:center; }
            .page-head { align-items:flex-start; }
            .filters .search { min-width:100%; }
            .filters select.input, .filters .btn { flex:1; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset(config('madpos_ui.assets_base').'/css/date-field.css') }}">
</head>
<body>
    @php($me = auth('central')->user())
    <aside class="sidebar">
        <div class="brand">
            <span class="mark">S</span>
            <span>{{ config('app.name', 'SamriddhiHR') }}<small>Platform Console</small></span>
        </div>

        <nav class="side-nav">
            <div class="nav-group">Tenants</div>
            <a href="{{ route('platform.dashboard') }}" class="{{ request()->routeIs('platform.dashboard') || request()->routeIs('platform.companies.*') ? 'active' : '' }}">
                <span class="ico">▣</span> Companies
            </a>
            <a href="{{ route('platform.reports.usage') }}" class="{{ request()->routeIs('platform.reports.*') ? 'active' : '' }}">
                <span class="ico">◫</span> Usage report
            </a>

            <div class="nav-group">Administration</div>
            <a href="{{ route('platform.admins.index') }}" class="{{ request()->routeIs('platform.admins.*') ? 'active' : '' }}">
                <span class="ico">◉</span> Platform admins
            </a>
        </nav>

        <div class="side-foot">
            @if($me)
                <a href="{{ route('platform.admins.show', $me) }}" class="side-user" title="Your account">
                    <span class="avatar avatar-sm avatar-dark">{{ $me->initials() }}</span>
                    <span class="who">
                        <b>{{ $me->name }}</b>
                        <span>{{ $me->email }}</span>
                    </span>
                </a>
            @endif
            <form method="POST" action="{{ route('platform.logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-logout">Sign out</button>
            </form>
        </div>
    </aside>

    <div class="wrap">
        @if(session('success'))<div class="flash flash-ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="flash flash-err">{{ session('error') }}</div>@endif
        @yield('content')
    </div>

    {{-- The same picker the tenant app uses; see public/assets/js/date-field.js. --}}
    <script src="{{ asset(config('madpos_ui.assets_base').'/js/date-field.js') }}"></script>
</body>
</html>
