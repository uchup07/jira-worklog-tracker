# Layout Migration: TallStackUI Standard Layout

**Date:** 2026-05-31  
**Scope:** `resources/views/layouts/app.blade.php` only — no child view changes.

## Goal

Replace the hand-rolled layout shell with TallStackUI's `<x-layout>` component system: header, collapsible sidebar, and content slot. Adopt TallStackUI's default Tailwind-based dark/light styling for the chrome.

## Constraints

- NativePHP `titleBarHiddenInset()` places macOS traffic lights at ~x:12 y:10. A full-width 38px drag region with `titlebar-drag` class must remain at the very top, with 80px left padding in the leftmost section to clear the buttons.
- All child views use `@extends('layouts.app')` and `@yield('content')` — these must continue to work unchanged.
- Custom CSS variables (`var(--bg)`, `var(--text)`, etc.) stay in `app.css`; page content still references them. Only the layout shell switches to TallStackUI classes. Migrating page content to Tailwind is out of scope.
- `html.dark` class already drives TallStackUI's dark mode — no changes needed to theme logic.

## Layout Shell Structure

```
<x-layout>
  <x-slot:top>      — NativePHP 38px macOS drag strip (app name only)
  <x-slot:menu>     — <x-layout.sidebar collapsible> with nav items
  <x-slot:header>   — <x-layout.header> with project controls
  (default slot)    — flash messages + @yield('content')
```

### Slot: top

Full-width 38px strip with `titlebar-drag` class. Contains only the "Worklog" app name label, left-padded 80px to clear the macOS traffic lights. The right portion is empty draggable space. No controls live here.

### Slot: menu

`<x-layout.sidebar collapsible smart>` with:

- **Brand slot (expanded):** "Worklog" app name text
- **Brand-collapsed slot:** Single "W" letter or icon
- **Nav items:**
  - Dashboard — `icon="home"` → `route('dashboard')`
  - My Issues — `icon="clipboard-document-list"` → `route('issues.index')`
  - Worklogs — `icon="clock"` → `route('worklogs.index')`
  - `<x-layout.sidebar.separator />`
  - Log Time — `icon="plus"` → `route('worklogs.create')`
- **Footer slot:** Settings — `icon="cog-6-tooth"` → `route('settings')`

The `smart` prop handles active-state detection; manual `request()->routeIs()` checks are dropped.

### Slot: header

`<x-layout.header>`:

- **Left slot:** Back button + project badge (`$projectKey`) + project name (`$projectName`) + last synced label
- **Right slot:** `<x-theme-switch>` + Choose Project link + Sync form

TallStackUI renders the sidebar collapse toggle automatically on the left side of the header — no custom button needed.

The `without-mobile-button` attribute is not set; the mobile hamburger is harmless in this NativePHP desktop context.

### Default slot

Flash messages (success/error banners, unchanged markup) followed by `@yield('content')`. The `<main>` wrapper and `page-enter` CSS animation class are removed — TallStackUI's layout renders its own `<main>` element.

## What Changes

| File | Change |
|------|--------|
| `resources/views/layouts/app.blade.php` | Full rewrite using `<x-layout>` |

## What Does Not Change

| File | Reason |
|------|--------|
| All other `*.blade.php` views | `@extends` / `@yield` interface unchanged |
| `resources/css/app.css` | Custom CSS vars kept for page content |
| `resources/js/app.js` | No changes |
| Routes, controllers, Livewire components | No changes |
