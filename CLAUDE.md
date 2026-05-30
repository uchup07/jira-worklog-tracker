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

## Key Decisions

- NativePHP wraps the app as an Electron desktop app; `NativeAppServiceProvider` is the entry point for native integrations.
- Two SQLite databases: `database.sqlite` (app data) and `nativephp.sqlite` (NativePHP internal state).
