# TallStackUI Layout Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hand-rolled `layouts/app.blade.php` with TallStackUI's `<x-layout>` component system — collapsible sidebar, sticky header, and NativePHP drag strip — without touching any child view.

**Architecture:** Two-file change. `AppServiceProvider::boot()` gets one soft-customization call to offset the fixed sidebar 38px from the top so it sits below the NativePHP drag strip. `layouts/app.blade.php` is then fully replaced with a `<x-layout>` shell using `top`, `menu`, `header`, and default slots. All child views continue to work via `@extends` / `@yield('content')` unchanged.

**Tech Stack:** TallStackUI v3 (`<x-layout>`, `<x-layout.sidebar>`, `<x-layout.header>`), NativePHP, Alpine.js, Livewire v4, Tailwind CSS v4, Laravel.

---

### Task 1: Offset sidebar below the NativePHP drag strip

TallStackUI's sidebar is `position: fixed; top: 0` by default, which would visually overlap the 38px drag strip rendered in the `<x-slot:top>`. One soft-customization call fixes this.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the TallStackUi facade import**

In `app/Providers/AppServiceProvider.php`, add to the `use` block (after the existing imports):

```php
use TallStackUi\Facades\TallStackUi;
```

- [ ] **Step 2: Add sidebar top-offset in boot()**

At the end of the `boot()` method (after the `View::composer` call), add:

```php
TallStackUi::customize()
    ->sideBar()
    ->block('desktop.wrapper.first.base', 'fixed left-0 bottom-0 z-50 flex flex-col top-[38px]');
```

This replaces the default `inset-y-0` vertical stretch so the sidebar starts 38px from the top, below the drag strip.

The full `boot()` method should now look like:

```php
public function boot(): void
{
    $this->ensureNativeDatabaseIsMigrated();

    View::composer('*', function ($view) {
        $view->with('projectKey', Settings::get('selected_project_key', 'No project'));
        $view->with('projectName', Settings::get('selected_project_name'));
        $view->with('appTheme', $this->resolveAppTheme());
        $lastSynced = Settings::get('last_synced_at', null);
        $view->with('lastSynced', $lastSynced ? Carbon::parse($lastSynced)->diffForHumans() : null);
    });

    TallStackUi::customize()
        ->sideBar()
        ->block('desktop.wrapper.first.base', 'fixed left-0 bottom-0 z-50 flex flex-col top-[38px]');
}
```

- [ ] **Step 3: Verify no PHP errors**

```bash
php artisan about
```

Expected: no exceptions, Laravel info table printed cleanly.

- [ ] **Step 4: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "feat: offset TallStackUI sidebar below NativePHP titlebar drag strip"
```

---

### Task 2: Rewrite layouts/app.blade.php

Replace the entire file with the `<x-layout>` shell. The `<html>` / `<head>` / `<body>` wrapper stays — TallStackUI's `<x-layout>` is a body-level component, not a full HTML document.

> **Note on component names:** The Layout docs use `<x-layout.sidebar>` in the `<x-slot:menu>` context. If Laravel throws "component not found", use `<x-side-bar>` and `<x-side-bar.item>` / `<x-side-bar.separator>` instead.

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Replace the entire file**

Overwrite `resources/views/layouts/app.blade.php` with:

```blade
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
<body x-bind:class="{ dark: darkTheme }">

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
        <x-layout.sidebar collapsible smart>

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

            <x-layout.sidebar.item text="Dashboard"  route="{{ route('dashboard') }}"       icon="home" />
            <x-layout.sidebar.item text="My Issues"  route="{{ route('issues.index') }}"    icon="clipboard-document-list" />
            <x-layout.sidebar.item text="Worklogs"   route="{{ route('worklogs.index') }}"  icon="clock" />
            <x-layout.sidebar.separator />
            <x-layout.sidebar.item text="Log Time"   route="{{ route('worklogs.create') }}" icon="plus" />

            <x-slot:footer>
                <x-layout.sidebar.item text="Settings" route="{{ route('settings') }}" icon="cog-6-tooth" />
            </x-slot:footer>

        </x-layout.sidebar>
    </x-slot:menu>

    <x-slot:header>
        <x-layout.header>

            <x-slot:left>
                <div style="display:flex; align-items:center; gap:10px;">
                    <button type="button" class="btn btn-ghost btn-sm titlebar-no-drag"
                            onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ route('dashboard') }}'">
                        <svg width="11" height="11" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                        </svg>
                        Back
                    </button>
                    <div style="display:flex; align-items:center; gap:10px; min-width:0;">
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
                    <x-theme-switch simple only-icons sm class="app-theme-switch titlebar-no-drag" />

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

    @yield('content')

</x-layout>

@livewireScripts
</body>
</html>
```

- [ ] **Step 2: Run existing tests**

```bash
php artisan test
```

Expected: all tests pass with no new failures.

- [ ] **Step 3: Start the dev server and verify visually**

```bash
composer run dev
```

Open the browser and confirm:

1. A 38px drag strip appears at the very top showing "Worklog" (with left padding for macOS traffic lights)
2. Sidebar renders below the drag strip — not overlapping it
3. Sidebar shows: Dashboard, My Issues, Worklogs, a separator, Log Time, and Settings pinned at the bottom
4. The collapse toggle (≡) in the header collapses/expands the sidebar to icon-only
5. Header left: Back button, project badge, project name, synced timestamp
6. Header right: theme toggle, Choose Project, Sync button
7. All nav links route to the correct pages
8. Dark/light theme toggle works
9. Flash messages appear below the header when triggered (test via a form submit)
10. All existing page content renders correctly inside the content area

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: migrate layout shell to TallStackUI x-layout with collapsible sidebar"
```
