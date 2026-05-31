# Plan: Theme Switch — Fix & Polish

## Context

The app already has `<x-theme-switch simple only-icons sm>` in the titlebar, but two bugs prevent it from working correctly:

1. **`dark` class is on `<body>`, not `<html>`** — CLAUDE.md explicitly states "Force dark mode on `<html class="dark">` since TallStackUI reads this class for theming." TallStackUI's Tailwind `dark:` variants (e.g. `dark:text-dark-200`) are checked against ancestors, and TallStackUI specifically reads from `<html>`. With `dark` only on `<body>`, TallStackUI's own theme-switch icons get wrong colors.

2. **CSS overrides target non-existent classes** — The current `.app-theme-switch` block targets `bg-gray-100`, `bg-white`, `text-gray-500` etc. The simple/only-icons variant doesn't render any of these. The only classes that actually appear are `text-dark-500 dark:text-dark-200` (wrapper), `text-yellow-500` (moon icon), `text-blue-500` (sun icon).

## Changes

### 1. `resources/views/layouts/app.blade.php`

**Line 2–6** — Add `x-bind:class="{ dark: darkTheme }"` to `<html>` alongside the existing `x-bind:data-theme`:

```html
<html lang="en"
      data-theme="{{ $appTheme }}"
      x-data="tallstackui_darkTheme({ default: @js($appTheme), name: 'app-theme' })"
      x-bind:data-theme="darkTheme ? 'dark' : 'light'"
      x-bind:class="{ dark: darkTheme }"
      x-effect="window.appTheme && window.appTheme.sync(darkTheme ? 'dark' : 'light')">
```

No change to `<body>` or the `<x-theme-switch>` component on line 73.

### 2. `resources/css/app.css`

**Lines 240–267** — Replace the `.app-theme-switch` block with a lean version that only targets the classes the simple/only-icons variant actually renders:

```css
/* ─── Theme switcher ─── */
.app-theme-switch button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--radius);
    transition: background 120ms ease;
}

.app-theme-switch button:hover {
    background: var(--sidebar-accent);
}

.app-theme-switch [class*="text-yellow-500"] {
    color: var(--accent) !important;
}

.app-theme-switch [class*="text-blue-500"] {
    color: var(--blue) !important;
}
```

This removes all the dead bg-gray/bg-white/text-gray-500 rules and adds a hover state that matches the `.btn-ghost` feel used by neighbouring titlebar buttons.

## Critical Files

- `resources/views/layouts/app.blade.php` — lines 2–6 (html tag)
- `resources/css/app.css` — lines 240–267 (theme-switch block)

## Git Workflow

```bash
git checkout -b fix/theme-switch
git add resources/css/app.css resources/views/layouts/app.blade.php
git commit -m "fix: drive theme tokens from html.dark class instead of data-theme attribute"
git push -u origin fix/theme-switch
```

## Verification

1. Run `composer run dev` or `composer run native:dev`
2. Click the sun/moon icon in the top-right titlebar — the app should switch between dark and light themes
3. Verify the icon colour changes: moon should be yellow (`var(--accent)`), sun should be blue (`var(--blue)`)
4. Reload the app — the theme should persist (stored via NativePHP Settings)
5. Check that the icon has a subtle background on hover matching the other titlebar buttons
