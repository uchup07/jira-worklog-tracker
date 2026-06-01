<?php

use App\Models\JiraIssue;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
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

        $usersNotLogging = $projectKey
            ? JiraProjectUser::forProject($projectKey)
                ->where('active', true)
                ->whereNotIn('account_id', $loggedAuthorIds)
                ->get()
            : collect();

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
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()],
            '3months' => [Carbon::now()->subMonths(3), Carbon::now()],
            default => [Carbon::now()->startOfMonth(), Carbon::now()],
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
            ->selectRaw('DATE(started_at) as date, SUM(time_spent_seconds) as seconds')
            ->groupByRaw('DATE(started_at)')
            ->orderBy('date')
            ->get();

        $weeklyTrend = JiraWorklog::forProject($projectKey)
            ->inDateRange($from, $to)
            ->selectRaw("strftime('%Y-W%W', started_at) as week, SUM(time_spent_seconds) as seconds")
            ->groupByRaw("strftime('%Y-W%W', started_at)")
            ->orderBy('week')
            ->get();

        return [
            'perProject' => $perProject->toArray(),
            'perUser' => $perUser->toArray(),
            'dailyTrend' => $dailyTrend->toArray(),
            'weeklyTrend' => $weeklyTrend->toArray(),
        ];
    }
};
?>

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
                    <option value="">All Projects</option>
                    @foreach($availableProjects as $p)
                        <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                    @endforeach
                </select>
            @else
                <span style="font-size:12px; color:var(--text-muted);">No project — <a href="{{ route('setup.project') }}" style="color:var(--accent);">configure one</a></span>
            @endif

            @foreach(['week' => 'This Week', 'month' => 'This Month', '3months' => '3 Months'] as $val => $label)
                <button type="button"
                        wire:click="$set('period', '{{ $val }}')"
                        aria-pressed="{{ $period === $val ? 'true' : 'false' }}"
                        style="font-size:11px; padding:4px 10px; border-radius:5px; cursor:pointer;
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
            <div class="stat-num" style="font-size:32px; color:{{ $usersNotLogging->isNotEmpty() ? 'var(--red)' : 'var(--text)' }};">
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
                                    {{ $rH }}h{{ $rM > 0 ? " {$rM}m" : '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>

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
