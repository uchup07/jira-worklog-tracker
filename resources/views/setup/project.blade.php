<!DOCTYPE html>
<html lang="en"
      data-theme="{{ $appTheme }}"
      x-data="tallstackui_darkTheme({ default: @js($appTheme), name: 'app-theme' })"
      x-bind:data-theme="darkTheme ? 'dark' : 'light'"
      x-effect="window.appTheme && window.appTheme.sync(darkTheme ? 'dark' : 'light')">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Select Project — Worklog Tracker</title>
    <tallstackui:script />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-bind:class="{ dark: darkTheme }"
      style="background:var(--bg); min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; -webkit-app-region:drag;">

    <div class="titlebar-no-drag" style="position:fixed; top:16px; right:16px; z-index:20;">
        <x-theme-switch simple only-icons sm class="app-theme-switch" />
    </div>

    <div class="titlebar-no-drag" style="width:100%; max-width:460px;">

        <div style="text-align:center; margin-bottom:28px;">
            <div style="width:36px; height:36px; border-radius:9px; background:var(--accent); display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="oklch(0.109 0 0)" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                    <path stroke-linecap="round" d="M9 12l2 2 4-4"/>
                </svg>
            </div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">
                Select a Project
            </h1>
            <p style="font-size:12.5px; color:var(--text-muted); margin-top:5px;">
                Choose the Jira project to track worklogs for
            </p>
        </div>

        <div class="card" style="padding:20px;">

            @if(empty($projects))
                <div style="padding:24px; text-align:center; border:1px dashed var(--border-2); border-radius:var(--radius);">
                    <p style="font-size:13px; color:var(--text-muted);">No projects found.</p>
                    <p style="font-size:11.5px; color:var(--text-subtle); margin-top:4px;">Ensure your API token has project access.</p>
                </div>
                <a href="{{ route('setup') }}"
                   style="display:block; text-align:center; margin-top:16px; font-size:12.5px; color:var(--text-muted); text-decoration:none; transition:color 100ms;"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--text-muted)'">
                    ← Back to credentials
                </a>
            @else
                <livewire:setup-project-selector :projects="$projects" />

                <div style="text-align:center; margin-top:12px;">
                    <a href="{{ route('setup') }}"
                       style="font-size:11.5px; color:var(--text-subtle); text-decoration:none; transition:color 100ms;"
                       onmouseover="this.style.color='var(--text-muted)'" onmouseout="this.style.color='var(--text-subtle)'">
                        ← Back to credentials
                    </a>
                </div>
            @endif

        </div>

    </div>

    @livewireScripts
</body>
</html>
