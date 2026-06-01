# Team Overview Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a `/team-overview` page as a single Livewire Volt component showing 9 KPI widgets, 4 ranked-list table cards, and 4 ApexCharts visualizations, auto-refreshing every 5 minutes.

**Architecture:** One Volt full-page component (`⚡team-overview.blade.php`) holds all state (`$period`, `$selectedProject`) and computes all widget data in `with()`. Chart data is dispatched as a `chartsUpdated` browser event from `mount()`, `pollRefresh()`, and filter-change hooks. ApexCharts instances live in `wire:ignore` containers and are created/updated in a single `@script` block. A 5-minute `wire:poll.300s="pollRefresh"` keeps everything fresh.

**Tech Stack:** Laravel, Livewire 4 (Volt), TallStackUI v3, ApexCharts (npm), SQLite, NativePHP Settings facade, PestPHP

---

## File Map

| Action | File |
|---|---|
| Modify | `resources/js/app.js` |
| Modify | `routes/web.php` |
| Modify | `resources/views/layouts/app.blade.php` |
| Create | `resources/views/components/⚡team-overview.blade.php` |
| Create | `tests/Feature/TeamOverviewTest.php` |

---

### Task 1: Install ApexCharts

**Files:**
- Modify: `package.json` (via npm)
- Modify: `resources/js/app.js`

- [ ] **Step 1: Install the package**

```bash
npm install apexcharts
```

Expected: `apexcharts` appears in `package.json` dependencies.

- [ ] **Step 2: Expose ApexCharts globally**

Add to the bottom of `resources/js/app.js`:

```js
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
```

- [ ] **Step 3: Build and verify**

```bash
npm run build
```

Expected: exits 0 with no errors.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/app.js
git commit -m "feat: install and expose ApexCharts globally"
```

---

### Task 2: Route, Sidebar Item, and Failing Feature Test

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`
- Create: `tests/Feature/TeamOverviewTest.php`

- [ ] **Step 1: Add the Volt route inside the EnsureJiraConnected middleware group**

In `routes/web.php`, add this `use` at the top:

```php
use Livewire\Volt\Volt;
```

Then inside the `Route::middleware(EnsureJiraConnected::class)->group(...)` closure, add after the dashboard route:

```php
Volt::route('/team-overview', 'team-overview')->name('team-overview');
```

- [ ] **Step 2: Add sidebar nav item**

In `resources/views/layouts/app.blade.php`, add this line immediately after the Dashboard `x-side-bar.item`:

```html
<x-side-bar.item text="Team Overview" route="{{ route('team-overview') }}" icon="chart-bar" />
```

- [ ] **Step 3: Write the failing feature test**

Create `tests/Feature/TeamOverviewTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\Settings;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain'          => 'example.atlassian.net',
                'jira_email'           => 'user@example.com',
                'jira_api_token'       => 'token',
                'selected_project_key' => 'EB',
                default                => $default,
            };
        });
});

test('team overview page loads', function () {
    $this->get(route('team-overview'))->assertOk();
});

test('period defaults to month', function () {
    Livewire::test('team-overview')
        ->assertSet('period', 'month');
});

test('period can be changed to week', function () {
    Livewire::test('team-overview')
        ->set('period', 'week')
        ->assertSet('period', 'week');
});

test('period can be changed to 3months', function () {
    Livewire::test('team-overview')
        ->set('period', '3months')
        ->assertSet('period', '3months');
});
```

- [ ] **Step 4: Run the test and confirm it fails**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: FAIL — Volt component `team-overview` not found.

---

### Task 3: Scaffold the Volt Component

**Files:**
- Create: `resources/views/components/⚡team-overview.blade.php`

- [ ] **Step 1: Create the minimal scaffold**

Create `resources/views/components/⚡team-overview.blade.php`:

```php
<?php

use Livewire\Volt\Component;
use Native\Desktop\Facades\Settings;

new class extends Component {
    public string $period = 'month';
    public string $selectedProject = '';

    public function mount(): void
    {
        $this->selectedProject = Settings::get('selected_project_key', '');
    }

    public function with(): array
    {
        return [
            'availableProjects'  => [],
            'totalWorkSeconds'   => 0,
            'totalWorklogsToday' => 0,
            'totalWorklogsMonth' => 0,
            'activeUsers'        => 0,
            'usersNotLogging'    => collect(),
            'topContributors'    => collect(),
            'worklogsPerStatus'  => collect(),
            'worklogsPerProject' => collect(),
        ];
    }
};
?>

<div wire:poll.300s="pollRefresh" style="padding:16px 18px;">
    <p style="color:var(--text);">Team Overview — scaffold</p>
</div>
```

- [ ] **Step 2: Run the tests — all should pass**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: 4 PASS.

- [ ] **Step 3: Commit**

```bash
git add "resources/views/components/⚡team-overview.blade.php" tests/Feature/TeamOverviewTest.php routes/web.php resources/views/layouts/app.blade.php
git commit -m "feat: scaffold team-overview Volt component with route and sidebar entry"
```

---

### Task 4: Implement Widget Data Queries

**Files:**
- Modify: `resources/views/components/⚡team-overview.blade.php` (PHP section only)

- [ ] **Step 1: Replace the entire PHP section (everything between `<?php` and `?>`) with the full implementation**

```php
<?php

use App\Models\JiraIssue;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Native\Desktop\Facades\Settings;

new class extends Component {
    public string $period = 'month';
    public string $selectedProject = '';
    public string $lastRefreshed = '';

    public function mount(): void
    {
        $this->selectedProject = Settings::get('selected_project_key', '');
        $this->lastRefreshed = now()->format('H:i');
        $this->dispatch('chartsUpdated', data: $this->buildChartData());
    }

    public function pollRefresh(): void
    {
        $this->lastRefreshed = now()->format('H:i');
        $this->dispatch('chartsUpdated', data: $this->buildChartData());
    }

    public function updatedPeriod(): void
    {
        $this->dispatch('chartsUpdated', data: $this->buildChartData());
    }

    public function updatedSelectedProject(): void
    {
        $this->dispatch('chartsUpdated', data: $this->buildChartData());
    }

    public function with(): array
    {
        [$from, $to] = $this->dateRange();
        $projectKey = $this->selectedProject;

        $totalWorkSeconds = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->sum('time_spent_seconds');

        $totalWorklogsToday = JiraWorklog::forProject($projectKey)
            ->inDateRange(Carbon::today(), Carbon::today()->endOfDay())
            ->count();

        $totalWorklogsMonth = JiraWorklog::forProject($projectKey)
            ->inDateRange(Carbon::now()->startOfMonth(), Carbon::now())
            ->count();

        $activeUsers = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->distinct('author_account_id')
            ->count('author_account_id');

        $loggedAuthorIds = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->distinct()
            ->pluck('author_account_id');

        $usersNotLogging = JiraProjectUser::forProject($projectKey)
            ->where('active', true)
            ->whereNotIn('account_id', $loggedAuthorIds)
            ->get();

        $topContributors = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->selectRaw('author_account_id, author_display_name, SUM(time_spent_seconds) as total_seconds')
            ->groupBy('author_account_id', 'author_display_name')
            ->orderByDesc('total_seconds')
            ->limit(10)
            ->get();

        $worklogsPerStatus = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->join('jira_issues', 'jira_worklogs.issue_key', '=', 'jira_issues.issue_key')
            ->selectRaw('jira_issues.status, SUM(jira_worklogs.time_spent_seconds) as total_seconds')
            ->groupBy('jira_issues.status')
            ->orderByDesc('total_seconds')
            ->get();

        $worklogsPerProject = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->join('jira_issues as ji', 'jira_worklogs.issue_key', '=', 'ji.issue_key')
            ->selectRaw('ji.project_key, SUM(jira_worklogs.time_spent_seconds) as total_seconds')
            ->groupBy('ji.project_key')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get();

        $availableProjects = JiraIssue::distinct()
            ->pluck('project_key')
            ->map(fn ($key) => ['label' => $key, 'value' => $key])
            ->values()
            ->toArray();

        return compact(
            'totalWorkSeconds',
            'totalWorklogsToday',
            'totalWorklogsMonth',
            'activeUsers',
            'usersNotLogging',
            'topContributors',
            'worklogsPerStatus',
            'worklogsPerProject',
            'availableProjects',
        );
    }

    private function dateRange(): array
    {
        return match ($this->period) {
            'week'    => [Carbon::now()->startOfWeek(), Carbon::now()],
            '3months' => [Carbon::now()->subMonths(3), Carbon::now()],
            default   => [Carbon::now()->startOfMonth(), Carbon::now()],
        };
    }

    private function buildChartData(): array
    {
        [$from, $to] = $this->dateRange();
        $projectKey = $this->selectedProject;

        $perProject = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->join('jira_issues as ji2', 'jira_worklogs.issue_key', '=', 'ji2.issue_key')
            ->selectRaw('ji2.project_key as label, SUM(jira_worklogs.time_spent_seconds) as seconds')
            ->groupBy('ji2.project_key')
            ->orderByDesc('seconds')
            ->get();

        $perUser = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->selectRaw('author_display_name as label, SUM(time_spent_seconds) as seconds')
            ->groupBy('author_account_id', 'author_display_name')
            ->orderByDesc('seconds')
            ->limit(10)
            ->get();

        $dailyTrend = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->selectRaw("DATE(started_at) as date, SUM(time_spent_seconds) as seconds")
            ->groupByRaw("DATE(started_at)")
            ->orderBy('date')
            ->get();

        $weeklyTrend = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->selectRaw("strftime('%Y-W%W', started_at) as week, SUM(time_spent_seconds) as seconds")
            ->groupByRaw("strftime('%Y-W%W', started_at)")
            ->orderBy('week')
            ->get();

        return [
            'perProject'  => $perProject->toArray(),
            'perUser'     => $perUser->toArray(),
            'dailyTrend'  => $dailyTrend->toArray(),
            'weeklyTrend' => $weeklyTrend->toArray(),
        ];
    }
};
```

- [ ] **Step 2: Add a view-data assertion to the test file**

In `tests/Feature/TeamOverviewTest.php`, add:

```php
test('team overview exposes all required view data keys', function () {
    Livewire::test('team-overview')
        ->assertViewHasAll([
            'totalWorkSeconds',
            'totalWorklogsToday',
            'totalWorklogsMonth',
            'activeUsers',
            'usersNotLogging',
            'topContributors',
            'worklogsPerStatus',
            'worklogsPerProject',
            'availableProjects',
        ]);
});
```

- [ ] **Step 3: Run the tests**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: 5 PASS.

- [ ] **Step 4: Commit**

```bash
git add "resources/views/components/⚡team-overview.blade.php" tests/Feature/TeamOverviewTest.php
git commit -m "feat: implement team overview widget data queries and chart data builder"
```

---

### Task 5: Filter Bar and KPI Stat Cards

**Files:**
- Modify: `resources/views/components/⚡team-overview.blade.php` (Blade template section only)

- [ ] **Step 1: Replace everything after the closing `?>` with the filter bar and KPI row**

```html
<div wire:poll.300s="pollRefresh"
     style="padding:16px 18px; display:flex; flex-direction:column; gap:12px;
            overflow-y:auto; height:100%;">

    {{-- Filter bar --}}
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">

            @if(!empty($availableProjects))
                <select wire:model.live="selectedProject"
                        style="font-size:12px; padding:5px 10px; border-radius:6px;
                               border:1px solid var(--border); background:var(--surface-2);
                               color:var(--text); cursor:pointer;">
                    @foreach($availableProjects as $p)
                        <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            @else
                <span style="font-size:12px; color:var(--text-muted);">No project — <a href="{{ route('setup.project') }}" style="color:var(--accent);">configure one</a></span>
            @endif

            @foreach(['week' => 'This Week', 'month' => 'This Month', '3months' => '3 Months'] as $val => $label)
                <button wire:click="$set('period', '{{ $val }}')"
                        style="font-size:11.5px; padding:4px 10px; border-radius:5px; cursor:pointer;
                               border:1px solid {{ $period === $val ? 'var(--accent)' : 'var(--border)' }};
                               background:{{ $period === $val ? 'var(--accent-dim)' : 'transparent' }};
                               color:{{ $period === $val ? 'var(--accent)' : 'var(--text-muted)' }};">
                    {{ $label }}
                </button>
            @endforeach

        </div>
        @if($lastRefreshed)
            <span style="font-size:11px; color:var(--text-subtle);">refreshed {{ $lastRefreshed }}</span>
        @endif
    </div>

    {{-- KPI stat cards --}}
    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:10px;">

        {{-- Total Work Hours --}}
        @php $wH = floor($totalWorkSeconds/3600); $wM = floor(($totalWorkSeconds%3600)/60); @endphp
        <div class="card" style="padding:14px 16px; position:relative; overflow:hidden;">
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Work Hours</div>
            <div class="stat-num" style="font-size:32px; color:var(--accent);">
                {{ $wH }}<span style="font-size:15px; opacity:0.6; margin-left:1px;">h</span>@if($wM > 0){{ $wM }}<span style="font-size:12px; opacity:0.6; margin-left:1px;">m</span>@endif
            </div>
        </div>

        {{-- Worklogs Today --}}
        <div class="card" style="padding:14px 16px;">
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Logs Today</div>
            <div class="stat-num" style="font-size:32px;">{{ $totalWorklogsToday }}</div>
        </div>

        {{-- Worklogs This Month --}}
        <div class="card" style="padding:14px 16px;">
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Logs This Month</div>
            <div class="stat-num" style="font-size:32px;">{{ $totalWorklogsMonth }}</div>
        </div>

        {{-- Active Users --}}
        <div class="card" style="padding:14px 16px;">
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Active Users</div>
            <div class="stat-num" style="font-size:32px;">{{ $activeUsers }}</div>
        </div>

        {{-- Users Not Logging --}}
        <div class="card" style="padding:14px 16px;">
            <div style="font-size:10px; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Not Logging</div>
            <div class="stat-num" style="font-size:32px; color:{{ $usersNotLogging->isNotEmpty() ? 'var(--red, #f87171)' : 'var(--text)' }};">
                {{ $usersNotLogging->count() }}
            </div>
            @if($usersNotLogging->isNotEmpty())
                <div style="margin-top:5px; display:flex; flex-direction:column; gap:2px;">
                    @foreach($usersNotLogging->take(3) as $u)
                        <div style="font-size:10.5px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $u->display_name }}</div>
                    @endforeach
                    @if($usersNotLogging->count() > 3)
                        <div style="font-size:10px; color:var(--text-subtle);">+{{ $usersNotLogging->count() - 3 }} more</div>
                    @endif
                </div>
            @endif
        </div>

    </div>

    {{-- TASKS 6–7 content goes here --}}

</div>
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: 5 PASS.

- [ ] **Step 3: Commit**

```bash
git add "resources/views/components/⚡team-overview.blade.php"
git commit -m "feat: add team overview filter bar and KPI stat cards"
```

---

### Task 6: Table Widget Cards

**Files:**
- Modify: `resources/views/components/⚡team-overview.blade.php` (Blade template)

- [ ] **Step 1: Replace `{{-- TASKS 6–7 content goes here --}}` with the four table cards**

```html
    {{-- Row 3: Top Contributors + Worklogs per Status --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">

        <div class="card" style="overflow:hidden;">
            <div style="padding:11px 14px; border-bottom:1px solid var(--border);">
                <span style="font-size:12px; font-weight:600; color:var(--text);">Top Contributors</span>
            </div>
            @if($topContributors->isEmpty())
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">No worklogs in this period.</div>
            @else
                @php $maxC = $topContributors->max('total_seconds') ?: 1; @endphp
                <div style="padding:12px 14px; display:flex; flex-direction:column; gap:10px;">
                    @foreach($topContributors as $c)
                        @php
                            $cH = floor($c->total_seconds/3600); $cM = floor(($c->total_seconds%3600)/60);
                            $cT = $cH > 0 ? "{$cH}h".($cM > 0 ? " {$cM}m" : '') : "{$cM}m";
                            $cPct = round(($c->total_seconds / $maxC) * 100);
                        @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:4px;">
                                <span style="font-size:12px; color:var(--text); max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $c->author_display_name }}</span>
                                <span class="display" style="font-size:13px; font-weight:700; font-style:italic;">{{ $cT }}</span>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ $cPct }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card" style="overflow:hidden;">
            <div style="padding:11px 14px; border-bottom:1px solid var(--border);">
                <span style="font-size:12px; font-weight:600; color:var(--text);">Worklogs per Status</span>
            </div>
            @if($worklogsPerStatus->isEmpty())
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">No worklogs in this period.</div>
            @else
                @php $maxSt = $worklogsPerStatus->max('total_seconds') ?: 1; @endphp
                <div style="padding:12px 14px; display:flex; flex-direction:column; gap:10px;">
                    @foreach($worklogsPerStatus as $s)
                        @php
                            $sH = floor($s->total_seconds/3600); $sM = floor(($s->total_seconds%3600)/60);
                            $sT = $sH > 0 ? "{$sH}h".($sM > 0 ? " {$sM}m" : '') : "{$sM}m";
                            $sPct = round(($s->total_seconds / $maxSt) * 100);
                        @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:4px;">
                                <span class="badge-status">{{ $s->status }}</span>
                                <span class="display" style="font-size:13px; font-weight:700; font-style:italic;">{{ $sT }}</span>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ $sPct }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Row 4: Projects by Workload + Worklogs per Project table --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">

        <div class="card" style="overflow:hidden;">
            <div style="padding:11px 14px; border-bottom:1px solid var(--border);">
                <span style="font-size:12px; font-weight:600; color:var(--text);">Projects by Workload</span>
            </div>
            @if($worklogsPerProject->isEmpty())
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">No worklogs in this period.</div>
            @else
                @php $maxPj = $worklogsPerProject->max('total_seconds') ?: 1; @endphp
                <div style="padding:12px 14px; display:flex; flex-direction:column; gap:10px;">
                    @foreach($worklogsPerProject as $pj)
                        @php
                            $pjH = floor($pj->total_seconds/3600); $pjM = floor(($pj->total_seconds%3600)/60);
                            $pjT = $pjH > 0 ? "{$pjH}h".($pjM > 0 ? " {$pjM}m" : '') : "{$pjM}m";
                            $pjPct = round(($pj->total_seconds / $maxPj) * 100);
                        @endphp
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:4px;">
                                <span class="badge-key">{{ $pj->project_key }}</span>
                                <span class="display" style="font-size:13px; font-weight:700; font-style:italic;">{{ $pjT }}</span>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width:{{ $pjPct }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card" style="overflow:hidden;">
            <div style="padding:11px 14px; border-bottom:1px solid var(--border);">
                <span style="font-size:12px; font-weight:600; color:var(--text);">Worklogs per Project</span>
            </div>
            @if($worklogsPerProject->isEmpty())
                <div style="padding:20px; text-align:center; font-size:12px; color:var(--text-muted);">No worklogs in this period.</div>
            @else
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="font-size:10.5px; color:var(--text-muted); padding:8px 14px; text-align:left; font-weight:500;">Project</th>
                            <th style="font-size:10.5px; color:var(--text-muted); padding:8px 14px; text-align:right; font-weight:500;">Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($worklogsPerProject as $pj)
                            @php $rH = floor($pj->total_seconds/3600); $rM = floor(($pj->total_seconds%3600)/60); @endphp
                            <tr>
                                <td><span class="badge-key">{{ $pj->project_key }}</span></td>
                                <td style="text-align:right;" class="display">
                                    {{ $rH }}h@if($rM > 0) {{ $rM }}m@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>

    {{-- TASK 7 chart rows go here --}}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: 5 PASS.

- [ ] **Step 3: Commit**

```bash
git add "resources/views/components/⚡team-overview.blade.php"
git commit -m "feat: add Top Contributors, Worklogs per Status, and per-Project table cards"
```

---

### Task 7: ApexCharts Integration

**Files:**
- Modify: `resources/views/components/⚡team-overview.blade.php` (Blade template — add chart containers, @script block, close outer div)

- [ ] **Step 1: Replace `{{-- TASK 7 chart rows go here --}}` with chart containers, the closing tag of the outer div, and the @script block**

```html
    {{-- Row 5: Charts 2×2 --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">

        <div class="card" style="padding:14px;">
            <div style="font-size:12px; font-weight:600; color:var(--text); margin-bottom:8px;">Worklogs per Project</div>
            <div id="chart-per-project" wire:ignore></div>
        </div>

        <div class="card" style="padding:14px;">
            <div style="font-size:12px; font-weight:600; color:var(--text); margin-bottom:8px;">Worklogs per User</div>
            <div id="chart-per-user" wire:ignore></div>
        </div>

    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">

        <div class="card" style="padding:14px;">
            <div style="font-size:12px; font-weight:600; color:var(--text); margin-bottom:8px;">Daily Worklog Trend</div>
            <div id="chart-daily" wire:ignore></div>
        </div>

        <div class="card" style="padding:14px;">
            <div style="font-size:12px; font-weight:600; color:var(--text); margin-bottom:8px;">Weekly Worklog Trend</div>
            <div id="chart-weekly" wire:ignore></div>
        </div>

    </div>

</div>{{-- /wire:poll wrapper --}}

@script
<script>
    const _charts = {};

    function _fmtSec(s) {
        const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
        return h > 0 ? `${h}h${m > 0 ? ` ${m}m` : ''}` : `${m}m`;
    }

    function _dark() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }

    function _base() {
        return {
            chart:  { background: 'transparent', toolbar: { show: false } },
            theme:  { mode: _dark() },
            colors: ['#edd94c', '#60a5fa', '#34d399', '#f87171', '#a78bfa', '#fb923c'],
            grid:   { borderColor: 'rgba(255,255,255,0.06)' },
        };
    }

    function _applyCharts(data) {
        // Donut — per project
        const ppSeries = data.perProject.map(d => +(d.seconds / 3600).toFixed(2));
        const ppLabels = data.perProject.map(d => d.label || 'Unknown');
        if (!_charts.perProject) {
            _charts.perProject = new ApexCharts(document.querySelector('#chart-per-project'), {
                ..._base(),
                chart:   { ..._base().chart, type: 'donut', height: 260 },
                series:  ppSeries,
                labels:  ppLabels,
                legend:  { position: 'bottom', fontSize: '11px' },
                tooltip: { y: { formatter: v => _fmtSec(v * 3600) } },
            });
            _charts.perProject.render();
        } else {
            _charts.perProject.updateOptions({ series: ppSeries, labels: ppLabels });
        }

        // Horizontal bar — per user
        const puData = data.perUser.map(d => +(d.seconds / 3600).toFixed(1));
        const puCats = data.perUser.map(d => d.label || 'Unknown');
        if (!_charts.perUser) {
            _charts.perUser = new ApexCharts(document.querySelector('#chart-per-user'), {
                ..._base(),
                chart:        { ..._base().chart, type: 'bar', height: 260 },
                plotOptions:  { bar: { horizontal: true, borderRadius: 3 } },
                series:       [{ name: 'Hours', data: puData }],
                xaxis:        { categories: puCats },
                tooltip:      { y: { formatter: v => `${v}h` } },
            });
            _charts.perUser.render();
        } else {
            _charts.perUser.updateOptions({ series: [{ name: 'Hours', data: puData }], xaxis: { categories: puCats } });
        }

        // Area — daily trend
        const dtData = data.dailyTrend.map(d => +(d.seconds / 3600).toFixed(1));
        const dtCats = data.dailyTrend.map(d => d.date);
        if (!_charts.daily) {
            _charts.daily = new ApexCharts(document.querySelector('#chart-daily'), {
                ..._base(),
                chart:   { ..._base().chart, type: 'area', height: 220 },
                series:  [{ name: 'Hours', data: dtData }],
                xaxis:   { categories: dtCats, labels: { rotate: -45, style: { fontSize: '10px' } } },
                stroke:  { curve: 'smooth', width: 2 },
                fill:    { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
                tooltip: { y: { formatter: v => `${v}h` } },
            });
            _charts.daily.render();
        } else {
            _charts.daily.updateOptions({ series: [{ name: 'Hours', data: dtData }], xaxis: { categories: dtCats } });
        }

        // Bar — weekly trend
        const wtData = data.weeklyTrend.map(d => +(d.seconds / 3600).toFixed(1));
        const wtCats = data.weeklyTrend.map(d => d.week);
        if (!_charts.weekly) {
            _charts.weekly = new ApexCharts(document.querySelector('#chart-weekly'), {
                ..._base(),
                chart:        { ..._base().chart, type: 'bar', height: 220 },
                plotOptions:  { bar: { borderRadius: 3, columnWidth: '60%' } },
                series:       [{ name: 'Hours', data: wtData }],
                xaxis:        { categories: wtCats, labels: { style: { fontSize: '10px' } } },
                tooltip:      { y: { formatter: v => `${v}h` } },
            });
            _charts.weekly.render();
        } else {
            _charts.weekly.updateOptions({ series: [{ name: 'Hours', data: wtData }], xaxis: { categories: wtCats } });
        }
    }

    window.addEventListener('chartsUpdated', (e) => {
        _applyCharts(e.detail.data);
    });
</script>
@endscript
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=TeamOverviewTest
```

Expected: 5 PASS.

- [ ] **Step 3: Start the dev server and manually verify the page**

```bash
composer run dev
```

Open the app and navigate to Team Overview. Verify:
- Sidebar shows "Team Overview" link
- Filter bar shows the project dropdown and three period buttons; the active button has accent color
- 5 KPI stat cards render (zeros if no data, real values if data exists)
- Top Contributors and Worklogs per Status cards render (empty state or rows with progress bars)
- Projects by Workload and Worklogs per Project cards render
- All 4 chart containers are present in the DOM
- Charts initialize (ApexCharts renders into the containers when the `chartsUpdated` event fires from `mount()`)
- Changing period updates KPI cards immediately
- Changing project selector updates KPI cards immediately

- [ ] **Step 4: Commit**

```bash
git add "resources/views/components/⚡team-overview.blade.php"
git commit -m "feat: integrate ApexCharts with wire:ignore containers and chartsUpdated event"
```

- [ ] **Step 5: Run the full test suite to check for regressions**

```bash
php artisan test
```

Expected: All existing tests pass alongside the new 5.

- [ ] **Step 6: Final commit if any adjustments were needed**

```bash
git add -p
git commit -m "fix: team overview adjustments from manual verification"
```

Skip this step if Step 3 required no changes.
