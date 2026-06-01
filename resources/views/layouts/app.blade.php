<!DOCTYPE html>
<html lang="en"
      class="{{ $appTheme === 'dark' ? 'dark' : '' }}"
      x-data="tallstackui_darkTheme({ default: @js($appTheme), name: 'app-theme' })"
      x-bind:class="{ dark: darkTheme }"
      x-effect="window.appTheme && window.appTheme.sync(darkTheme ? 'dark' : 'light')">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Worklog') — Jira Tracker</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="overflow-hidden" x-bind:class="{ dark: darkTheme }">

<x-layout>

    {{--
        titleBarHiddenInset puts macOS traffic lights at ~x:12 y:10.
        This 38px strip is the drag region; the sidebar is offset to
        start below it via AppServiceProvider soft customization.
        Interactive children carry .titlebar-no-drag.
    --}}
    <x-slot:top>
        <div class="titlebar-drag"
             style="height:38px; display:flex; align-items:center;
                    padding:0 12px 0 80px;
                    background:var(--sidebar); border-bottom:1px solid var(--sidebar-border);">
            <span class="titlebar-no-drag"
                  style="font-size:13px; font-weight:700; color:var(--text); letter-spacing:-0.025em;">
                Worklog
            </span>
        </div>
    </x-slot:top>

    <x-slot:menu>
        <x-side-bar collapsible smart>

            <x-slot:brand>
                <div style="padding:12px 16px; font-size:13px; font-weight:700;
                            color:var(--text); letter-spacing:-0.025em;">
                    Worklog
                </div>
            </x-slot:brand>

            <x-slot:brand-collapsed>
                <div style="padding:12px; font-size:13px; font-weight:700;
                            color:var(--text); text-align:center;">
                    W
                </div>
            </x-slot:brand-collapsed>

            <x-side-bar.item text="Dashboard"    route="{{ route('dashboard') }}"       icon="home" />
            <x-side-bar.item text="Team Overview" route="{{ route('team-overview') }}" icon="chart-bar" />
            <x-side-bar.item text="My Issues"  route="{{ route('issues.index') }}"    icon="clipboard-document-list" />
            <x-side-bar.item text="Worklogs">
                <x-side-bar.item text="Monitoring" route="{{ route('worklogs.monitoring') }}" icon="table-cells" />
                <x-side-bar.item text="Missing"    route="{{ route('worklogs.missing') }}"    icon="user-minus" />
            </x-side-bar.item>

            <x-side-bar.separator line />
            <x-side-bar.item text="Log Time"   route="{{ route('worklogs.create') }}" icon="plus" />

            <x-slot:footer>
                <x-side-bar.item text="Settings" route="{{ route('settings') }}" icon="cog-6-tooth" />
            </x-slot:footer>

        </x-side-bar>
    </x-slot:menu>

    <x-slot:header>
        <x-layout.header>

            <x-slot:left>
                <div class="flex items-center space-x-4">
                    @if(!request()->routeIs('dashboard'))
                        <x-button icon="chevron-left" position="left"  outline onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ route('dashboard') }}'">Back</x-button>
                    @endif

                    <div class="flex items-center space-x-4">
                        <span class="mono"
                              style="font-size:11px; font-weight:500; color:var(--accent);
                                     background:var(--accent-dim); padding:2px 8px; border-radius:4px;
                                     border:1px solid oklch(0.857 0.168 87.5 / 0.18); flex-shrink:0;">
                            {{ $projectKey ?? 'No project' }}
                        </span>
                        @if(! empty($projectName))
                            <span style="font-size:12.5px; font-weight:600; color:var(--text);
                                         overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                {{ $projectName }}
                            </span>
                        @endif
                    </div>
                    @if(isset($lastSynced) && $lastSynced)
                        <span style="font-size:11.5px; color:var(--text-subtle);">
                            synced {{ $lastSynced }}
                        </span>
                    @endif
                </div>
            </x-slot:left>

            <x-slot:right>
                <div style="display:flex; align-items:center; gap:8px;">
                    <x-theme-switch simple class="app-theme-switch" />
                    <x-button.circle icon="folder"  href="{{ route('setup.project') }}" flat />

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
            </x-slot:right>

        </x-layout.header>
    </x-slot:header>

    @if(session('success'))
        <div x-data
             x-init="setTimeout(() => { $el.style.opacity='0'; $el.style.maxHeight='0'; $el.style.padding='0'; $el.style.margin='0'; }, 3200)"
             style="margin:10px 18px 0; padding:9px 13px; background:oklch(0.75 0.17 142 / 0.08); border:1px solid oklch(0.75 0.17 142 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--green); display:flex; align-items:center; gap:8px; transition:opacity 400ms, max-height 400ms, padding 400ms, margin 400ms; max-height:60px; overflow:hidden;">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="margin:10px 18px 0; padding:9px 13px; background:oklch(0.65 0.22 25 / 0.08); border:1px solid oklch(0.65 0.22 25 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--red); display:flex; align-items:center; gap:8px;">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" style="flex-shrink:0;">
                <circle cx="12" cy="12" r="9"/>
                <path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif


    {{ $slot }}



</x-layout>

@livewireScripts
</body>
</html>
