# Employee Utilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an "Employee Utilization" top-level page that shows each team member's logged hours vs. their 8h/day target for a selected month, with green/yellow/red color bands.

**Architecture:** A single Livewire v4 single-file component (`⚡utilization.blade.php`) holds all logic and template. A thin wrapper view embeds it inside `<x-app-layout>`. A new route and sidebar item wire it into the app.

**Tech Stack:** Laravel 13, Livewire v4.3 (single-file components, no separate Volt package needed), TallStackUI v3, Carbon, PestPHP 4.

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Modify | `routes/web.php` | Add `/utilization` route inside `EnsureJiraConnected` group |
| Modify | `resources/views/layouts/app.blade.php` | Add sidebar item between Team Overview and My Issues |
| Create | `resources/views/utilization/index.blade.php` | Wrapper view using `<x-app-layout>` |
| Create | `resources/views/components/⚡utilization.blade.php` | Full Livewire component (logic + template) |
| Create | `tests/Feature/UtilizationTest.php` | Feature tests for route, component defaults, and calculation |

---

## Task 1: Route + sidebar + wrapper view

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`
- Create: `resources/views/utilization/index.blade.php`
- Create: `tests/Feature/UtilizationTest.php`

- [ ] **Step 1: Write the failing route test**

Create `tests/Feature/UtilizationTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Desktop\Facades\Settings;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain'           => 'example.atlassian.net',
                'jira_email'            => 'user@example.com',
                'jira_api_token'        => 'token',
                'selected_project_key'  => 'EB',
                default                 => $default,
            };
        });
});

test('utilization page loads', function () {
    $this->get(route('utilization.index'))->assertOk();
});
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test --filter="utilization page loads"
```

Expected: FAIL — route not found / 404.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `EnsureJiraConnected` middleware group, add after the `team-overview` route:

```php
Route::get('/utilization', fn () => view('utilization.index'))->name('utilization.index');
```

- [ ] **Step 4: Add the sidebar item**

In `resources/views/layouts/app.blade.php`, add this line between the Team Overview item and the My Issues item:

```blade
<x-side-bar.item text="Utilization" route="{{ route('utilization.index') }}" icon="chart-pie" />
```

Before (context to locate insertion point):
```blade
<x-side-bar.item text="Team Overview" route="{{ route('team-overview') }}" icon="chart-bar" />
<x-side-bar.item text="My Issues"  route="{{ route('issues.index') }}"    icon="clipboard-document-list" />
```

After:
```blade
<x-side-bar.item text="Team Overview" route="{{ route('team-overview') }}" icon="chart-bar" />
<x-side-bar.item text="Utilization"   route="{{ route('utilization.index') }}" icon="chart-pie" />
<x-side-bar.item text="My Issues"  route="{{ route('issues.index') }}"    icon="clipboard-document-list" />
```

- [ ] **Step 5: Create the wrapper view**

Create `resources/views/utilization/index.blade.php`:

```blade
<x-app-layout>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">Employee Utilization</h1>
            <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Logged hours vs. 8h/day target per team member</p>
        </div>
    </div>

    <livewire:utilization />
</x-app-layout>
```

- [ ] **Step 6: Run the test — it still fails (component missing)**

```bash
php artisan test --filter="utilization page loads"
```

Expected: FAIL — Livewire component `utilization` not found. This confirms the route and view are wired correctly but the component is missing.

- [ ] **Step 7: Create a minimal stub component so the page loads**

Create `resources/views/components/⚡utilization.blade.php` with just enough to render:

```blade
<?php

use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $month = '';
    public string $selectedProject = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function with(): array
    {
        return [
            'rows'         => [],
            'months'       => [],
            'targetHours'  => 0.0,
            'workingDays'  => 0,
        ];
    }
};
?>

<div>
    {{-- placeholder --}}
</div>
```

- [ ] **Step 8: Run the test — it passes now**

```bash
php artisan test --filter="utilization page loads"
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add routes/web.php \
        resources/views/layouts/app.blade.php \
        resources/views/utilization/index.blade.php \
        "resources/views/components/⚡utilization.blade.php" \
        tests/Feature/UtilizationTest.php
git commit -m "feat: scaffold utilization route, sidebar item, wrapper view, and stub component"
```

---

## Task 2: Component logic — month picker and defaults

**Files:**
- Modify: `resources/views/components/⚡utilization.blade.php`
- Modify: `tests/Feature/UtilizationTest.php`

- [ ] **Step 1: Write failing tests for month defaults and months list**

Append to `tests/Feature/UtilizationTest.php`:

```php
use Livewire\Livewire;

test('utilization component defaults month to current month', function () {
    Livewire::test('utilization')
        ->assertSet('month', now()->format('Y-m'));
});

test('utilization component months list contains 12 entries', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return count($months) === 12;
        });
});

test('utilization component months list first entry is current month', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return $months[0]['value'] === now()->format('Y-m');
        });
});

test('utilization component months list last entry is 11 months ago', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return $months[11]['value'] === now()->subMonths(11)->format('Y-m');
        });
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --filter="UtilizationTest"
```

Expected: the new 4 tests FAIL (months returns empty array from stub).

- [ ] **Step 3: Implement the month picker logic**

Replace the `with()` method in `resources/views/components/⚡utilization.blade.php`:

```php
public function with(): array
{
    $months = collect(range(0, 11))->map(function (int $i) {
        $date = now()->subMonths($i)->startOfMonth();
        return [
            'value' => $date->format('Y-m'),
            'label' => $date->format('F Y'),
        ];
    })->toArray();

    return [
        'rows'        => [],
        'months'      => $months,
        'targetHours' => 0.0,
        'workingDays' => 0,
    ];
}
```

- [ ] **Step 4: Run tests — all pass**

```bash
php artisan test --filter="UtilizationTest"
```

Expected: all 5 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add "resources/views/components/⚡utilization.blade.php" tests/Feature/UtilizationTest.php
git commit -m "feat: add month picker logic and defaults to utilization component"
```

---

## Task 3: Component logic — utilization calculation

**Files:**
- Modify: `resources/views/components/⚡utilization.blade.php`
- Modify: `tests/Feature/UtilizationTest.php`

- [ ] **Step 1: Write failing tests for calculation**

Append to `tests/Feature/UtilizationTest.php`:

```php
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;

test('utilization rows include all active users from selected project', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-a',
        'display_name' => 'Alice',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-b',
        'display_name' => 'Bob',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $ids = array_column($rows, 'account_id');
            return in_array('user-a', $ids) && in_array('user-b', $ids);
        });
});

test('utilization excludes inactive users', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-inactive',
        'display_name' => 'Inactive',
        'active'       => false,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            return ! in_array('user-inactive', array_column($rows, 'account_id'));
        });
});

test('utilization sums worklogs across all projects for a user', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-multi',
        'display_name' => 'Multi',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    $month = now()->format('Y-m');
    $date  = now()->startOfMonth()->addDays(1)->format('Y-m-d H:i:s');

    // Worklog on project EB
    JiraWorklog::create([
        'jira_worklog_id'    => 'wl-1',
        'issue_key'          => 'EB-1',
        'author_account_id'  => 'user-multi',
        'author_display_name'=> 'Multi',
        'time_spent_seconds' => 3600,
        'started_at'         => $date,
        'synced_at'          => now(),
    ]);
    // Worklog on a different project
    JiraWorklog::create([
        'jira_worklog_id'    => 'wl-2',
        'issue_key'          => 'OTHER-1',
        'author_account_id'  => 'user-multi',
        'author_display_name'=> 'Multi',
        'time_spent_seconds' => 7200,
        'started_at'         => $date,
        'synced_at'          => now(),
    ]);

    Livewire::test('utilization', ['month' => $month])
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-multi');
            // 3600 + 7200 = 10800 seconds = 3h
            return $row && $row['actual_seconds'] === 10800;
        });
});

test('utilization user with no worklogs has 0 actual seconds and red band', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-zero',
        'display_name' => 'Zero',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-zero');
            return $row
                && $row['actual_seconds'] === 0
                && $row['color_band'] === 'red';
        });
});

test('utilization color band is green when utilization >= 90%', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-green',
        'display_name' => 'Green',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    // Count weekdays in current month and log exactly 90% of target
    $start = now()->startOfMonth();
    $end   = now()->endOfMonth();
    $weekdays = 0;
    $d = $start->copy();
    while ($d->lte($end)) {
        if ($d->isWeekday()) $weekdays++;
        $d->addDay();
    }
    $targetSeconds  = $weekdays * 8 * 3600;
    $ninetyPct      = (int) ($targetSeconds * 0.90);

    JiraWorklog::create([
        'jira_worklog_id'     => 'wl-green',
        'issue_key'           => 'EB-2',
        'author_account_id'   => 'user-green',
        'author_display_name' => 'Green',
        'time_spent_seconds'  => $ninetyPct,
        'started_at'          => now()->startOfMonth()->addDay()->format('Y-m-d H:i:s'),
        'synced_at'           => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-green');
            return $row && $row['color_band'] === 'green';
        });
});

test('utilization color band is yellow when utilization is between 70% and 89.9%', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-yellow',
        'display_name' => 'Yellow',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    $start = now()->startOfMonth();
    $end   = now()->endOfMonth();
    $weekdays = 0;
    $d = $start->copy();
    while ($d->lte($end)) {
        if ($d->isWeekday()) $weekdays++;
        $d->addDay();
    }
    $targetSeconds = $weekdays * 8 * 3600;
    $seventyFivePct = (int) ($targetSeconds * 0.75);

    JiraWorklog::create([
        'jira_worklog_id'     => 'wl-yellow',
        'issue_key'           => 'EB-3',
        'author_account_id'   => 'user-yellow',
        'author_display_name' => 'Yellow',
        'time_spent_seconds'  => $seventyFivePct,
        'started_at'          => now()->startOfMonth()->addDay()->format('Y-m-d H:i:s'),
        'synced_at'           => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-yellow');
            return $row && $row['color_band'] === 'yellow';
        });
});

test('utilization rows are sorted descending by utilization_pct', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-high',
        'display_name' => 'High',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-low',
        'display_name' => 'Low',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    $date = now()->startOfMonth()->addDay()->format('Y-m-d H:i:s');

    JiraWorklog::create([
        'jira_worklog_id'     => 'wl-high',
        'issue_key'           => 'EB-10',
        'author_account_id'   => 'user-high',
        'author_display_name' => 'High',
        'time_spent_seconds'  => 57600, // 16h
        'started_at'          => $date,
        'synced_at'           => now(),
    ]);
    JiraWorklog::create([
        'jira_worklog_id'     => 'wl-low',
        'issue_key'           => 'EB-11',
        'author_account_id'   => 'user-low',
        'author_display_name' => 'Low',
        'time_spent_seconds'  => 3600, // 1h
        'started_at'          => $date,
        'synced_at'           => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $ids = array_column($rows, 'account_id');
            $highPos = array_search('user-high', $ids);
            $lowPos  = array_search('user-low',  $ids);
            return $highPos !== false && $lowPos !== false && $highPos < $lowPos;
        });
});

test('utilization target hours equals 8 times weekday count in month', function () {
    // June 2026 has 21 weekdays → 168h target
    Livewire::test('utilization', ['month' => '2026-06'])
        ->assertViewHas('workingDays', 21)
        ->assertViewHas('targetHours', 168.0);
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
php artisan test --filter="UtilizationTest"
```

Expected: the 8 new calculation tests FAIL (rows is still `[]`).

- [ ] **Step 3: Implement the full calculation in the component**

Replace the entire PHP class block in `resources/views/components/⚡utilization.blade.php` with:

```php
<?php

use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $month = '';
    public string $selectedProject = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function updatedMonth(): void
    {
        // reactive — with() re-runs automatically
    }

    public function with(): array
    {
        $months = collect(range(0, 11))->map(function (int $i) {
            $date = now()->subMonths($i)->startOfMonth();
            return [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y'),
            ];
        })->toArray();

        [$workingDays, $targetSeconds, $monthStart, $monthEnd] = $this->calculateMonthMeta();

        $actualByUser = JiraWorklog::whereBetween('started_at', [$monthStart, $monthEnd])
            ->selectRaw('author_account_id, SUM(time_spent_seconds) as total_seconds')
            ->groupBy('author_account_id')
            ->pluck('total_seconds', 'author_account_id');

        $users = JiraProjectUser::where('project_key', $this->selectedProject)
            ->where('active', true)
            ->get();

        $rows = $users->map(function ($user) use ($actualByUser, $targetSeconds) {
            $actualSeconds = (int) ($actualByUser->get($user->account_id, 0));
            $actualHours   = round($actualSeconds / 3600, 1);
            $targetHours   = round($targetSeconds / 3600, 1);

            if ($targetSeconds === 0) {
                $utilizationPct = null;
                $colorBand      = 'red';
            } else {
                $utilizationPct = round($actualSeconds / $targetSeconds * 100, 1);
                $colorBand      = $utilizationPct >= 90 ? 'green'
                                : ($utilizationPct >= 70 ? 'yellow' : 'red');
            }

            return [
                'account_id'      => $user->account_id,
                'display_name'    => $user->display_name,
                'actual_seconds'  => $actualSeconds,
                'actual_hours'    => $actualHours,
                'target_hours'    => $targetHours,
                'utilization_pct' => $utilizationPct,
                'color_band'      => $colorBand,
            ];
        })
        ->sortByDesc('utilization_pct')
        ->values()
        ->toArray();

        return [
            'rows'        => $rows,
            'months'      => $months,
            'targetHours' => round($targetSeconds / 3600, 1),
            'workingDays' => $workingDays,
        ];
    }

    private function calculateMonthMeta(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $workingDays = 0;
        $cursor      = $start->copy();
        while ($cursor->lte($end)) {
            if ($cursor->isWeekday()) {
                $workingDays++;
            }
            $cursor->addDay();
        }

        $targetSeconds = $workingDays * 8 * 3600;

        return [$workingDays, $targetSeconds, $start->startOfDay(), $end->endOfDay()];
    }
};
?>

<div>
    {{-- placeholder --}}
</div>
```

- [ ] **Step 4: Run tests — all pass**

```bash
php artisan test --filter="UtilizationTest"
```

Expected: all 13 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add "resources/views/components/⚡utilization.blade.php" tests/Feature/UtilizationTest.php
git commit -m "feat: implement utilization calculation logic with cross-project worklog totals"
```

---

## Task 4: Component template

**Files:**
- Modify: `resources/views/components/⚡utilization.blade.php`

- [ ] **Step 1: Replace the placeholder template**

Replace `<div>{{-- placeholder --}}</div>` at the bottom of `resources/views/components/⚡utilization.blade.php` with the full template:

```blade
<div style="display:flex; flex-direction:column; gap:14px;">

    {{-- Filter bar --}}
    <div style="display:flex; align-items:center; gap:10px; padding:10px 14px;
                background:var(--surface); border:1px solid var(--border);
                border-radius:var(--radius); flex-wrap:wrap;">

        <select wire:model.live="month"
                style="background:var(--surface-2); border:1px solid var(--border);
                       border-radius:var(--radius); padding:5px 9px; font-size:12.5px;
                       font-family:'Geist',sans-serif; color:var(--text); outline:none; cursor:pointer;">
            @foreach($months as $m)
                <option value="{{ $m['value'] }}">{{ $m['label'] }}</option>
            @endforeach
        </select>

        <span style="font-size:12px; color:var(--text-muted);">
            {{ $workingDays }} working days &middot; {{ $targetHours }}h target
        </span>
    </div>

    {{-- Table --}}
    @if(empty($rows))
        <div class="card" style="padding:40px; text-align:center;">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 stroke-width="1.2" style="color:var(--text-subtle); margin:0 auto 10px; display:block;">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            <p style="font-size:13px; color:var(--text-muted);">No team members found. Try syncing the project first.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Worklog</th>
                        <th>Target</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $color = match($row['color_band']) {
                                'green'  => 'var(--green)',
                                'yellow' => 'var(--accent)',
                                default  => 'var(--red)',
                            };
                            $dimColor = match($row['color_band']) {
                                'green'  => 'oklch(0.750 0.170 142 / 0.10)',
                                'yellow' => 'var(--accent-dim)',
                                default  => 'oklch(0.650 0.220 25 / 0.10)',
                            };
                            $barWidth = $row['utilization_pct'] !== null
                                ? min((float) $row['utilization_pct'], 100)
                                : 0;

                            $h = floor($row['actual_seconds'] / 3600);
                            $m = floor(($row['actual_seconds'] % 3600) / 60);
                            $actualFormatted = $h > 0
                                ? "{$h}h" . ($m > 0 ? " {$m}m" : '')
                                : "{$m}m";
                        @endphp
                        <tr>
                            <td style="font-size:13px; font-weight:500; color:var(--text);">
                                {{ $row['display_name'] }}
                            </td>
                            <td>
                                <span style="font-size:14px; font-weight:700; letter-spacing:-0.03em;
                                             color:var(--text);">{{ $actualFormatted }}</span>
                            </td>
                            <td>
                                <span style="font-size:13px; color:var(--text-muted);">
                                    {{ $row['target_hours'] }}h
                                </span>
                            </td>
                            <td style="position:relative; min-width:120px;">
                                {{-- Progress bar background --}}
                                <div style="position:absolute; inset:0; width:{{ $barWidth }}%;
                                            background:{{ $dimColor }}; z-index:0;
                                            transition:width 300ms ease;"></div>
                                {{-- Text --}}
                                <span style="position:relative; z-index:1; font-size:14px;
                                             font-weight:700; letter-spacing:-0.03em;
                                             color:{{ $color }};">
                                    @if($row['utilization_pct'] !== null)
                                        {{ $row['utilization_pct'] }}%
                                    @else
                                        &mdash;
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
```

- [ ] **Step 2: Run the full test suite to confirm no regressions**

```bash
php artisan test
```

Expected: all tests PASS.

- [ ] **Step 3: Commit**

```bash
git add "resources/views/components/⚡utilization.blade.php"
git commit -m "feat: add utilization table template with progress bar and color bands"
```

---

## Self-Review Checklist

- **Spec coverage:**
  - ✓ Top-level sidebar item with `chart-pie` icon
  - ✓ Route `/utilization` inside `EnsureJiraConnected`
  - ✓ Wrapper view with `<x-app-layout>`
  - ✓ Month picker (last 12 months, `wire:model.live`)
  - ✓ User roster from selected project's active `jira_project_users`
  - ✓ Worklog totals cross-project (no `scopeForProject` on worklog query)
  - ✓ Target = 8h × weekdays in selected month
  - ✓ Color bands: ≥90% green, 70-89% yellow, <70% red (`--accent` for yellow)
  - ✓ Table columns: User, Worklog, Target, Utilization
  - ✓ Inline progress bar background on Utilization cell
  - ✓ Sorted descending by utilization %
  - ✓ No pagination
  - ✓ Zero target → show `—` (null `utilization_pct`)
  - ✓ No new migrations needed
  - ✓ No separate `livewire/volt` install needed

- **Placeholder scan:** No TBDs or incomplete steps. ✓

- **Type consistency:**
  - `$rows[*]['color_band']` defined as `'green'|'yellow'|'red'` in Task 3, matched in Task 4 template `match()`. ✓
  - `$rows[*]['utilization_pct']` is `float|null` in Task 3, null-checked in template. ✓
  - `calculateMonthMeta()` returns `[int, int, Carbon, Carbon]` — used correctly in `with()`. ✓
