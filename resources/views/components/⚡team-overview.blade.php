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
