---
paths:
  - "resources/views/**"
  - "resources/css/**"
  - "resources/js/**"
  - "public/**"
  - "**/*.blade.php"
---

# Frontend

## Design Tokens

This project defines all tokens in `resources/css/app.css` via Tailwind CSS v4 `@theme {}` blocks. Never hardcode raw color or size values — always use the CSS custom properties below.

**Semantic color tokens (always prefer these):**

| Token | Usage |
|---|---|
| `var(--text)` | Primary text |
| `var(--text-muted)` | Secondary / label text |
| `var(--text-subtle)` | Tertiary / hint text |
| `var(--accent)` | Primary accent (gold/yellow) |
| `var(--accent-dim)` | Accent background tint |
| `var(--border)` | Dividers and input borders |
| `var(--surface)` | Card / panel background |
| `var(--surface-2)` | Input / inset background |
| `var(--sidebar)` | Sidebar background |
| `var(--sidebar-border)` | Sidebar divider |
| `var(--red)` | Error / destructive |
| `var(--green)` | Success |
| `var(--radius)` | Default border radius |

Dark mode is driven by the `html.dark` class (set by TallStackUI). Never use `data-theme`. Always test UI in both modes.

## Design Principle

This project uses a **dark terminal / editorial data aesthetic** — compact, information-dense, monochrome base with a gold accent. Key characteristics:
- Dark backgrounds, tight spacing, no decorative chrome
- Numbers in `stat-num` (large, italic monospace)
- Progress bars (`progress-track` / `progress-fill`) for ranked lists
- Badge pills (`badge-key`, `badge-status`) for issue keys and statuses
- Inline styles with CSS custom properties for one-off layout; shared utility classes for repeated patterns

## Component Stack

| Layer | What this project uses |
|---|---|
| CSS | Tailwind CSS v4 (config in `app.css @theme {}`) + inline styles with CSS vars |
| UI components | **TallStackUI v3** — registered without prefix |
| Reactivity | **Livewire 4** anonymous class components in `resources/views/components/⚡*.blade.php` |
| Charts | **ApexCharts** — globally available as `window.ApexCharts` (imported in `app.js`) |
| Icons | Heroicons (via TallStackUI `icon=` prop) |

## Project CSS Classes (do not recreate)

These classes are defined in `resources/css/app.css` — use them, never duplicate:

| Class | Purpose |
|---|---|
| `card` | Card container with surface background and border |
| `stat-num` | Large italic number display (dashboards) |
| `badge-key` | Issue key pill (e.g. EB-42) |
| `badge-status` | Jira status pill |
| `progress-track` | Thin progress bar container |
| `progress-fill` | Progress bar fill (set `width:%` inline) |
| `data-table` | Full-width table with row borders |
| `display` | Italic bold stat font |
| `mono` | Monospace text |
| `titlebar-drag` | NativePHP drag region |
| `titlebar-no-drag` | Interactive child inside drag region |

## TallStackUI Component Rules

- `<x-select>` does **not** exist — use `<x-select.native>` or `<x-select.styled>`
- `<x-button href="...">` renders as `<a>` automatically — no `tag="a"` needed
- Use `block` not `full` for full-width buttons
- Customisation via `TallStackUi::customize()` in `AppServiceProvider` — never the legacy `personalize()` method

## Livewire Patterns

**Component files:** `resources/views/components/⚡<name>.blade.php` — PHP anonymous class first, then Blade template after `?>`.

**Polling:** `wire:poll.300s="methodName"` on the root div. The method dispatches a browser event; `with()` refreshes widget data automatically on every poll cycle.

**Charts (ApexCharts + Livewire):**
- Put chart mount divs inside `wire:ignore` so Livewire never destroys them
- Dispatch chart data as a browser event: `$this->dispatch('chartsUpdated', data: [...])`
- In `@script` / `@endscript`, listen with `window.addEventListener('chartsUpdated', e => ...)` and call `chart.updateOptions()` — never destroy and recreate
- Call `Mail::purge('smtp')` (or equivalent) after `Config::set()` calls so cached drivers pick up new config

**Filter state:** Use `wire:model.live` for selects and inputs that should trigger an immediate re-render.

**Toggle buttons:** Always add `type="button"` and `aria-pressed="{{ $active ? 'true' : 'false' }}"`.

## Blade Template Gotchas

**`@if` / `@endif` adjacent to word characters:** Blade will NOT parse directives that immediately follow a word character.

```blade
{{-- BROKEN: Blade treats h@if and m@endif as literal text --}}
{{ $h }}h@if($m > 0) {{ $m }}m@endif

{{-- CORRECT: use a ternary expression --}}
{{ $h }}h{{ $m > 0 ? " {$m}m" : '' }}
```

**Hours/minutes in table cells:** Always use the ternary pattern above, not inline `@if`.

## NativePHP Layout Constraints

- `titleBarHiddenInset()` places macOS traffic lights at top-left (~x:12 y:10)
- The 38px drag strip at the top needs `padding-left: 80px` on interactive children
- Interactive elements inside the drag strip must carry `.titlebar-no-drag`
- The sidebar is offset below the drag strip via `mt-[38px]` in `AppServiceProvider` TallStackUI customisation

## Accessibility

- All `<button>` elements that don't submit a form: add `type="button"`
- Toggle/filter buttons: add `aria-pressed`
- Color is never the sole indicator (pair color with text or icon)
- Form inputs: always have an associated `<label>` or `aria-label`

## Layout

- CSS Grid for 2D layouts (`grid-template-columns`), Flexbox for 1D
- Use `gap`, not margin hacks
- Inline `style=` for one-off sizing; `class=` for repeated patterns
