<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Worklog') — Jira Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body style="background:var(--bg); height:100vh; display:flex; overflow:hidden;">

{{-- Sidebar --}}
<aside style="width:212px; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; flex-shrink:0;">

    <div style="padding:18px 16px 14px; border-bottom:1px solid var(--border);">
        <div class="display" style="font-size:17px; font-weight:800; font-style:italic; color:var(--text); letter-spacing:-0.03em; line-height:1;">
            Worklog
        </div>
        <div style="font-size:10px; font-weight:400; color:var(--text-muted); margin-top:3px; letter-spacing:0.06em; text-transform:uppercase;">
            Jira Tracker
        </div>
    </div>

    <nav style="flex:1; padding:8px; display:flex; flex-direction:column; gap:1px; overflow-y:auto;">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
            </svg>
            Dashboard
        </a>

        <a href="{{ route('issues.index') }}" class="nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            My Issues
        </a>

        <a href="{{ route('worklogs.index') }}" class="nav-link {{ request()->routeIs('worklogs.index') || request()->routeIs('worklogs.create') && false ? 'active' : (request()->routeIs('worklogs.*') && !request()->routeIs('worklogs.create') ? 'active' : '') }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>
            </svg>
            Worklogs
        </a>

        <div style="height:1px; background:var(--border); margin:6px 2px;"></div>

        <a href="{{ route('worklogs.create') }}"
           class="nav-link {{ request()->routeIs('worklogs.create') ? 'active' : '' }}"
           style="{{ !request()->routeIs('worklogs.create') ? 'color:var(--accent);' : '' }}">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            </svg>
            Log Time
        </a>
    </nav>

    <div style="padding:8px; border-top:1px solid var(--border);">
        <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}" style="font-size:12.5px;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                <path stroke-linecap="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            Settings
        </a>
    </div>
</aside>

{{-- Main content area --}}
<div style="flex:1; display:flex; flex-direction:column; overflow:hidden; min-width:0;">

    {{-- Topbar --}}
    <header style="height:44px; background:var(--surface); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 20px; flex-shrink:0;">
        <div style="display:flex; align-items:center; gap:10px;">
            <span class="mono" style="font-size:11px; font-weight:500; color:var(--accent); background:var(--accent-dim); padding:2px 8px; border-radius:4px; border:1px solid rgba(237,217,76,0.18);">
                {{ $projectKey ?? 'No project' }}
            </span>
            @if(isset($lastSynced) && $lastSynced)
                <span style="font-size:11.5px; color:var(--text-muted);">synced {{ $lastSynced }}</span>
            @endif
        </div>

        <form method="POST" action="{{ route('sync') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm" :disabled="busy" :class="{ 'opacity-40': busy }">
                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2"
                     :class="{ 'animate-spin': busy }">
                    <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="busy ? 'Syncing…' : 'Sync'"></span>
            </button>
        </form>
    </header>

    {{-- Flash messages --}}
    @if(session('success'))
        <div x-data x-init="setTimeout(() => { $el.style.opacity='0'; $el.style.height='0'; $el.style.marginBottom='0'; }, 3500)"
             style="margin:12px 20px 0; padding:10px 14px; background:rgba(74,222,128,0.07); border:1px solid rgba(74,222,128,0.18); border-radius:8px; font-size:13px; color:#4ADE80; display:flex; align-items:center; gap:8px; transition:all 400ms ease;">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin:12px 20px 0; padding:10px 14px; background:rgba(255,94,94,0.07); border:1px solid rgba(255,94,94,0.18); border-radius:8px; font-size:13px; color:#FF5E5E; display:flex; align-items:center; gap:8px;">
            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Page --}}
    <main style="flex:1; overflow-y:auto; padding:20px;" class="page-enter">
        @yield('content')
    </main>

</div>
</body>
</html>
