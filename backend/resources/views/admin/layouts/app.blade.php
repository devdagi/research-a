<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Company Device Manager</title>
    <style>
        :root {
            --bg: #0f172a;
            --panel: #1e293b;
            --panel-2: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --accent-2: #0ea5e9;
            --ok: #34d399;
            --warn: #fbbf24;
            --danger: #f87171;
            --border: #475569;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: radial-gradient(circle at top left, #1e3a5f, var(--bg) 45%);
            color: var(--text);
            min-height: 100vh;
        }
        a { color: var(--accent); text-decoration: none; }
        .shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .sidebar {
            background: rgba(15, 23, 42, 0.92);
            border-right: 1px solid var(--border);
            padding: 1.5rem 1rem;
        }
        .brand { font-weight: 700; font-size: 1.05rem; margin-bottom: 1.5rem; letter-spacing: 0.02em; }
        .nav a {
            display: block;
            padding: 0.65rem 0.8rem;
            border-radius: 0.5rem;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }
        .nav a:hover, .nav a.active { background: var(--panel); color: var(--text); }
        .main { padding: 1.5rem 2rem; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; gap: 1rem; }
        h1 { margin: 0; font-size: 1.5rem; }
        .card {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid var(--border);
            border-radius: 0.85rem;
            padding: 1.1rem 1.2rem;
            margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat-label { color: var(--muted); font-size: 0.85rem; }
        .stat-value { font-size: 1.6rem; font-weight: 700; margin-top: 0.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.7rem 0.5rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 0.85rem; }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--panel-2);
        }
        .badge.ok { background: rgba(52, 211, 153, 0.2); color: var(--ok); }
        .badge.warn { background: rgba(251, 191, 36, 0.2); color: var(--warn); }
        .badge.danger { background: rgba(248, 113, 113, 0.2); color: var(--danger); }
        .btn {
            display: inline-block;
            border: 0;
            border-radius: 0.5rem;
            padding: 0.55rem 0.9rem;
            background: var(--accent-2);
            color: #042f4a;
            font-weight: 600;
            cursor: pointer;
        }
        .btn.secondary { background: var(--panel-2); color: var(--text); }
        .btn.danger { background: #b91c1c; color: white; }
        .btn:hover { filter: brightness(1.08); }
        input, select, textarea {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 0.5rem;
            padding: 0.55rem 0.7rem;
        }
        label { display: block; margin-bottom: 0.35rem; color: var(--muted); font-size: 0.85rem; }
        .field { margin-bottom: 0.9rem; }
        .flash {
            padding: 0.8rem 1rem;
            border-radius: 0.6rem;
            margin-bottom: 1rem;
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.35);
        }
        .flash.error { background: rgba(248, 113, 113, 0.15); border-color: rgba(248, 113, 113, 0.35); }
        .muted { color: var(--muted); }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .map-placeholder {
            height: 280px;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border: 1px dashed var(--border);
            display: grid;
            place-items: center;
            color: var(--muted);
            text-align: center;
            padding: 1rem;
        }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { border-right: 0; border-bottom: 1px solid var(--border); }
            .nav { display: flex; flex-wrap: wrap; gap: 0.25rem; }
            .nav a { margin: 0; }
            .main { padding: 1rem; }
        }
        /* Fullscreen Map & Audio page — no sidebar / topbar */
        body.map-audio-mode { height: 100vh; overflow: hidden; }
        body.map-audio-mode .shell {
            height: 100vh;
            min-height: 100vh;
            grid-template-columns: 1fr;
        }
        body.map-audio-mode .sidebar { display: none; }
        body.map-audio-mode .main {
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        body.map-audio-mode .topbar { display: none; }
        body.map-audio-mode .flash { margin: 0.75rem 1rem 0; }
    </style>
</head>
<body class="@yield('body_class')">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">Company Device Manager</div>
        <nav class="nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Overview</a>
            <a href="{{ route('admin.map-audio.index') }}" class="{{ request()->routeIs('admin.map-audio.*') ? 'active' : '' }}">Map &amp; Audio</a>
            <a href="{{ route('admin.devices.index') }}" class="{{ request()->routeIs('admin.devices.*') ? 'active' : '' }}">Devices</a>
            <a href="{{ route('admin.commands.index') }}" class="{{ request()->routeIs('admin.commands.*') ? 'active' : '' }}">Commands</a>
            <a href="{{ route('admin.live-sessions.index') }}" class="{{ request()->routeIs('admin.live-sessions.*') ? 'active' : '' }}">Live sessions</a>
            <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">Audit log</a>
            <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Settings</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-top: 1.5rem;">
            @csrf
            <button class="btn secondary" type="submit">Sign out</button>
        </form>
    </aside>
    <main class="main">
        <div class="topbar">
            <h1>@yield('title', 'Dashboard')</h1>
            <div class="muted">{{ auth()->user()?->name }}</div>
        </div>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash error">
                <ul style="margin:0;padding-left:1.1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
