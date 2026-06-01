# Employee Utilization — Design Spec

**Date:** 2026-06-01  
**Status:** Approved

---

## Overview

A new top-level "Employee Utilization" page that measures how much of each team member's standard working hours were actually logged in Jira for a selected month.

**Formula:** `Utilization % = actual logged hours / target hours × 100`  
**Target:** 8h/day × weekdays in the selected month  
**Example:** June 2026 has 21 weekdays → target = 168h. Yusuf logs 151h → 89.9% (yellow).

---

## Data & Calculation

### Inputs
- Selected month (`YYYY-MM`), defaulting to the current month
- Selected project (from `Settings::get('selected_project_key')`)

### Target hours
Count all Mon–Fri days in the full selected month (not just up to today).  
`$targetSeconds = $workingDays * 8 * 3600`

Weekends only are excluded — no public holiday handling.

### Actual hours
Sum `time_spent_seconds` from `jira_worklogs` where:
- `started_at` falls within the selected month (first day 00:00:00 → last day 23:59:59)
- `issue_key` is scoped to the selected project via the existing `scopeForProject` query scope

Grouped by `author_account_id`.

### Users shown
All active users from `jira_project_users` for the selected project (`active = true`).  
Users with zero worklogs in the month appear with 0h actual, full target, 0% utilization (shown in red).

### Utilization %
`round(actualSeconds / targetSeconds * 100, 1)`  
Display is uncapped (can exceed 100% if someone logs overtime).

### Color bands
| Utilization | Color |
|---|---|
| ≥ 90% | Green (`var(--green)`) |
| 70% – 89.9% | Yellow (`var(--yellow)`) |
| < 70% | Red (`var(--red)`) |

### Edge case: zero working days
If the selected month contains no weekdays (e.g. a hypothetical all-weekend month), `$targetSeconds` is 0. In this case all rows show `—` in the Utilization column rather than dividing by zero.

### Sort order
Descending by utilization % (highest performers first). Rows with `—` utilization sort to the bottom.

---

## Architecture

### New files
| File | Purpose |
|---|---|
| `resources/views/components/⚡utilization.blade.php` | Livewire v4 single-file component (logic + template) |
| `resources/views/utilization/index.blade.php` | Wrapper view using `<x-app-layout>` |

### Route
Added inside the `EnsureJiraConnected` middleware group in `routes/web.php`:
```php
Route::get('/utilization', fn () => view('utilization.index'))->name('utilization.index');
```

### Sidebar
New top-level `<x-side-bar.item>` with `chart-pie` icon, positioned between "Team Overview" and "My Issues" in `resources/views/layouts/app.blade.php`.

### No new migrations or models
All data comes from existing `jira_worklogs` and `jira_project_users` tables.

---

## Component: `⚡utilization.blade.php`

### Livewire properties
| Property | Type | Default | Notes |
|---|---|---|---|
| `$month` | `string` | current `YYYY-MM` | Drives all queries |
| `$selectedProject` | `string` | from Settings | Set in `mount()` |

### `with()` return data
| Key | Type | Description |
|---|---|---|
| `$rows` | array | Per-user utilization, sorted desc by `utilization_pct` |
| `$months` | array | Last 12 months as `['value', 'label']` for the select |
| `$targetHours` | float | Working hours target for the month (e.g. 168.0) |
| `$workingDays` | int | Weekday count for the month |

### `$rows` shape (per user)
```php
[
    'account_id'      => string,
    'display_name'    => string,
    'actual_seconds'  => int,
    'actual_hours'    => float,   // rounded to 1dp
    'target_hours'    => float,
    'utilization_pct' => float,   // e.g. 89.9
    'color_band'      => 'green'|'yellow'|'red',
]
```

---

## UI

### Filter bar
A `<select>` showing the last 12 months (e.g. "June 2026", "May 2026" …), wired to `$month` with `wire:model.live`. Changing month reactively reloads the table.

### Table columns
| Column | Content |
|---|---|
| User | `display_name` |
| Worklog | `Xh Ym` formatted actual time |
| Target | `Xh` formatted target time |
| Utilization | Percentage number + thin inline progress bar background, colored by band |

### Utilization cell detail
The cell renders the `%` number in the band color, with a subtle background fill (like a progress bar behind the text) to give an at-a-glance visual weight. No separate progress bar element — the background-image or a pseudo-element approach using inline style width.

### Empty state
If no active users exist for the project, show a centered card with a message prompting a sync.

### No pagination
One row per active team member — small enough to display in full.

---

## Livewire compatibility note

`livewire/livewire` v4.3 is installed. Livewire v4 natively auto-discovers single-file components in `resources/views/components/` without requiring the separate `livewire/volt` package. No additional installation needed.

---

## Out of scope

- Configurable hours-per-day (fixed at 8)
- Public holiday exclusion
- Per-user drill-down to individual worklogs
- Export to CSV/PDF
