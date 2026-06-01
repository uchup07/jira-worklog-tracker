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

<div wire:poll.300s="pollRefresh" style="padding:16px 18px;">
    <p style="color:var(--text);">Team Overview — scaffold</p>
</div>
