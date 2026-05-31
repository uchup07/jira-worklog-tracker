<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Worklog') — Jira Tracker</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    titleBarHiddenInset puts macOS traffic lights at ~x:12 y:10.
    The full-width top strip is the drag region; pad-left:80px on the logo
    slot leaves room for the three dots. Interactive children carry
    .titlebar-no-drag (or .btn / .nav-link which already have no-drag).
--}}
<body style="background:var(--bg); height:100vh; display:flex; flex-direction:column; overflow:hidden;">

{{-- ── Titlebar: drag region across full width ── --}}
<div class="titlebar-drag"
     style="height:38px; display:flex; flex-shrink:0;
            background:var(--sidebar); border-bottom:1px solid var(--sidebar-border);">

    {{-- Left slice: app name (traffic lights overlay here → pad-left:80px) --}}
    <div style="width:196px; flex-shrink:0; display:flex; align-items:center;
                padding:0 12px 0 80px; border-right:1px solid var(--sidebar-border);">
        <span class="titlebar-no-drag"
              style="font-size:13px; font-weight:700; color:var(--text); letter-spacing:-0.025em;">
            Worklog
        </span>
    </div>

    {{-- Right slice: project badge + sync --}}
    <div class="titlebar-no-drag"
         style="flex:1; display:flex; align-items:center; justify-content:space-between;
                padding:0 14px; background:var(--surface); border-bottom:1px solid var(--border);">

        <div style="display:flex; align-items:center; gap:10px;">
            <span class="mono"
                  style="font-size:11px; font-weight:500; color:var(--accent);
                         background:var(--accent-dim); padding:2px 8px; border-radius:4px;
                         border:1px solid oklch(0.857 0.168 87.5 / 0.18);">
                {{ $projectKey ?? 'No project' }}
            </span>
            @if(isset($lastSynced) && $lastSynced)
                <span style="font-size:11.5px; color:var(--text-subtle);">synced {{ $lastSynced }}</span>
            @endif
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <a href="{{ route('setup.project') }}" class="btn btn-ghost btn-sm">
                <svg width="11" height="11" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 7.5h7l2 2h9v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z"/>
                </svg>
                Choose Project
            </a>

            <form method="POST" action="{{ route('sync') }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm"
                        :disabled="busy" :class="{ 'opacity-40': busy }">
                    <svg width="11" height="11" fill="none" stroke="currentColor"
                         viewBox="0 0 24 24" stroke-width="2.2" :class="{ 'animate-spin': busy }">
                        <path stroke-linecap="round"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span x-text="busy ? 'Syncing…' : 'Sync'"></span>
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ── Main row: sidebar + content ── --}}
<div style="flex:1; display:flex; overflow:hidden; min-height:0;">

    {{-- Sidebar --}}
    <aside style="width:196px; background:var(--sidebar); border-right:1px solid var(--sidebar-border);
                  display:flex; flex-direction:column; flex-shrink:0; overflow:hidden;">

        <nav style="flex:1; padding:8px; display:flex; flex-direction:column; gap:1px; overflow-y:auto;">

            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg width="14" height="14" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="1.8" style="flex-shrink:0; color:var(--text-subtle);">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('issues.index') }}"
               class="nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
                <svg width="14" height="14" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="1.8" style="flex-shrink:0; color:var(--text-subtle);">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                My Issues
            </a>

            <a href="{{ route('worklogs.index') }}"
               class="nav-link {{ request()->routeIs('worklogs.index') ? 'active' : '' }}">
                <svg width="14" height="14" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="1.8" style="flex-shrink:0; color:var(--text-subtle);">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" d="M12 7v5l3 2"/>
                </svg>
                Worklogs
            </a>

            <div class="sidebar-sep" style="margin:6px 0;"></div>

            <a href="{{ route('worklogs.create') }}"
               class="nav-link {{ request()->routeIs('worklogs.create') ? 'active' : '' }}"
               style="{{ !request()->routeIs('worklogs.create') ? 'color:var(--accent);' : '' }}">
                <svg width="14" height="14" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="2.2" style="flex-shrink:0;">
                    <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                </svg>
                Log Time
            </a>
        </nav>

        <div style="padding:6px 8px; border-top:1px solid var(--sidebar-border);">
            <a href="{{ route('settings') }}"
               class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}"
               style="font-size:12.5px;">
                <svg width="13" height="13" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24" stroke-width="1.8" style="flex-shrink:0; color:var(--text-subtle);">
                    <path stroke-linecap="round"
                          d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                Settings
            </a>
        </div>
    </aside>

    {{-- Page area --}}
    <div style="flex:1; display:flex; flex-direction:column; overflow:hidden; min-width:0;">

        @if(session('success'))
            <div x-data
                 x-init="setTimeout(() => { $el.style.opacity='0'; $el.style.maxHeight='0'; $el.style.padding='0'; $el.style.margin='0'; }, 3200)"
                 style="margin:10px 18px 0; padding:9px 13px; background:oklch(0.75 0.17 142 / 0.08); border:1px solid oklch(0.75 0.17 142 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--green); display:flex; align-items:center; gap:8px; transition:opacity 400ms, max-height 400ms, padding 400ms, margin 400ms; max-height:60px; overflow:hidden;">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div style="margin:10px 18px 0; padding:9px 13px; background:oklch(0.65 0.22 25 / 0.08); border:1px solid oklch(0.65 0.22 25 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--red); display:flex; align-items:center; gap:8px;">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" style="flex-shrink:0;"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/></svg>
                {{ session('error') }}
            </div>
        @endif

        <main style="flex:1; overflow-y:auto; padding:18px 20px;" class="page-enter">
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
