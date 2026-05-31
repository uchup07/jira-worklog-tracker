# Project Instructions

## Commands

```bash
# Build
npm run build                         # compile frontend assets

# Test
php artisan test                      # run full suite
php artisan test --filter=ClassName   # run specific test

# Lint & Format
./vendor/bin/pint                     # auto-fix PHP style

# Dev
composer run dev          # web server + vite + queue + logs
composer run native:dev   # run as NativePHP desktop app
```

## Tech Stack

- **NativePHP 2.2** — Electron desktop wrapper; `titleBarHiddenInset()` places macOS traffic lights at top-left, so the drag region needs `padding-left: 80px` on the left 196px
- **TallStackUI v3** — Blade component library; registered without prefix (env `TALLSTACKUI_PREFIX` unset). Requires `@import '...tallstackui/css/v4.css'` and `@plugin '@tailwindcss/forms'` in `app.css`
- **Livewire v4 (Volt)** — Single-file components live in `resources/views/components/⚡*.blade.php`
- **Tailwind CSS v4** — Config is in `app.css` via `@theme {}`, not `tailwind.config.js`

## TallStackUI Gotchas

- `<x-select>` does not exist — use `<x-select.native>` (requires `[['label'=>'...','value'=>'...'],...]` options) or `<x-select.styled>`
- `<x-button href="...">` renders as `<a>` automatically — no `tag="a"` needed; use `block` not `full` for full-width
- NativePHP Settings facade: `Native\Desktop\Facades\Settings` (NOT `NativePHP\Desktop\Facades\Settings`)
- Force dark mode on `<html class="dark">` since TallStackUI reads this class for theming

## Key Decisions

- NativePHP wraps the app as an Electron desktop app; `NativeAppServiceProvider` is the entry point for native integrations.
- Two SQLite databases: `database.sqlite` (app data) and `nativephp.sqlite` (NativePHP internal state).
