# Team Overview Dashboard — Design Spec

**Date**: 2026-06-01  
**Status**: Approved

---

## Summary

A new "Team Overview" page added alongside the existing personal dashboard. It gives a team-wide picture of worklog activity: KPI widgets, ranked lists, and four ApexCharts visualizations. Data auto-refreshes every 5 minutes via Livewire polling. A project selector and date range filter control the scope of all time-sensitive widgets.

---

## Architecture

### New files

- `resources/views/components/⚡team-overview.blade.php` — single Volt full-page component

### Dependencies

- ApexCharts installed via `npm install apexcharts`, imported in `resources/js/app.js`

### Route

Added to `routes/web.php`:

```php
Volt::route('/team-overview', 'team-overview')->name('team-overview');
```

### Sidebar

One new item added to `resources/views/layouts/app.blade.php` after the Dashboard entry:

```html
<x-side-bar.item text="Team Overview" route="{{ route('team-overview') }}" icon="chart-bar" />
```

### Polling

`wire:poll.300s` on the component root triggers a Livewire re-render every 5 minutes, refreshing all widget data and dispatching a `chartsUpdated` browser event for ApexCharts.

### Livewire state properties

| Property | Type | Default | Description |
|---|---|---|---|
| `$period` | `string` | `'month'` | `'week'` \| `'month'` \| `'3months'` |
| `$selectedProject` | `string` | `Settings::get('selected_project_key')` | Active project key |

Both are bound via `wire:model`, triggering instant re-render on change.

---

## Date Range Derivation

| Period value | From | To |
|---|---|---|
| `week` | `Carbon::now()->startOfWeek()` | now |
| `month` | `Carbon::now()->startOfMonth()` | now |
| `3months` | `Carbon::now()->subMonths(3)` | now |

---

## Widgets & Data Queries

All queries run inside the Volt component's computed data pass (one poll = one set of queries).

| Widget | Scope | Query |
|---|---|---|
| Total Work Hours | Selected period | `JiraWorklog::forProject()->inDateRange()->sum('time_spent_seconds')` → formatted as `Xh Ym` |
| Total Worklogs Today | Always today | `JiraWorklog::forProject()->inDateRange(today, endOfDay)->count()` |
| Total Worklogs This Month | Always this month | `JiraWorklog::forProject()->inDateRange(startOfMonth, now)->count()` |
| Active Users | Selected period | `JiraWorklog::forProject()->inDateRange()->distinct('author_account_id')->count()` |
| Users Not Logging | Selected period | `JiraProjectUser::forProject()->active()->whereNotIn('account_id', $loggedAuthorIds)->get()` |
| Projects w/ Largest Worklogs | Selected period | Group `jira_worklogs` by `project_key` (via `jira_issues` join), sum seconds, top 5 |
| Top Contributors | Selected period | Group by `author_account_id`, sum seconds, order desc, top 10 |
| Worklogs per Status | Selected period | Join `jira_worklogs` → `jira_issues` on `issue_key`, group by `status`, sum seconds |
| Worklogs per Project | Selected period | Reuses "Projects w/ Largest Worklogs" query result |

**Users Not Logging**: compares `jira_project_users.account_id` (where `active = true`) against the set of `author_account_id` values that appear in worklogs within the selected period. Names are shown in a list below the count.

**Projects w/ Largest Worklogs**: scoped to the selected project's issues by default. The query groups by `project_key` from the joined `jira_issues` table — this naturally extends to multi-project in future without schema changes.

---

## Charts

All four charts are initialized once and updated without DOM destruction on every poll.

| Chart | Type | Data |
|---|---|---|
| Worklogs per Project | Donut | Reuses "Worklogs per Project" widget data |
| Worklogs per User | Horizontal Bar | Reuses "Top Contributors" widget data |
| Daily Worklog Trend | Area | `GROUP BY DATE(started_at)` for each day in selected period |
| Weekly Worklog Trend | Bar | `GROUP BY strftime('%W', started_at)` for selected period |

### Chart lifecycle

1. On first render, chart data is JSON-encoded into a `data-charts` attribute on a `wire:ignore` wrapper div.
2. Alpine.js reads that attribute on `x-init` and creates four ApexCharts instances.
3. On every `wire:poll` re-render, Volt dispatches the `chartsUpdated` browser event with fresh data JSON.
4. The `@script` listener calls `chart.updateOptions()` on each instance — no destroy/recreate.

### Dark mode

ApexCharts receives `theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' }` at init. The app drives dark mode via `html.dark`, so this is a single class check.

### Data shapes

Daily trend:
```json
[{ "date": "2026-05-26", "seconds": 14400 }, ...]
```

Weekly trend:
```json
[{ "week": "W21", "seconds": 72000 }, ...]
```

---

## Page Layout

Layout follows the existing dashboard aesthetic (dark cards, CSS tokens, same grid gap).

```
┌─────────────────────────────────────────────────────────────┐
│  [Project selector ▼]   [This Week] [This Month] [3 Months] │  ← Filter bar
│                                          Last refreshed: Xs  │
├───────────┬───────────┬───────────┬───────────┬─────────────┤
│ Work Hours│Logs Today │Logs/Month │Active Users│Not Logging  │  ← Row 2: KPI cards (5)
├─────────────────────────┬───────────────────────────────────┤
│  Top Contributors       │  Worklogs per Status              │  ← Row 3
├─────────────────────────┴───────────────────────────────────┤
│  Projects w/ Largest Worklogs  │  Worklogs per Project      │  ← Row 4
├─────────────────────────┬───────────────────────────────────┤
│  Donut: per Project     │  H-Bar: per User                  │  ← Row 5: Charts
├─────────────────────────┼───────────────────────────────────┤
│  Area: Daily Trend      │  Bar: Weekly Trend                │
└─────────────────────────┴───────────────────────────────────┘
```

The page scrolls vertically inside the existing `x-layout` content area. No horizontal scroll.

---

## Error Handling

- If `$selectedProject` is empty (no project configured), show an inline prompt to set one via Settings.
- If queries return empty (no worklogs in period), widgets show `0` / `0h` — not an error state.
- ApexCharts gracefully handles empty series — no extra guard needed.

---

## Testing

- Unit test the `JiraWorklog` and `JiraProjectUser` query scopes used by new widget queries.
- Feature test the `/team-overview` route: assert it returns 200 and passes the expected view data keys.
- No browser/JS tests required — ApexCharts rendering is visual-only.
