<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Native\Desktop\Facades\Settings;

class DashboardController extends Controller
{
    public function index()
    {
        $accountId = Settings::get('jira_account_id', '');
        $projectKey = Settings::get('selected_project_key', '');

        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $todaySeconds = JiraWorklog::forAuthor($accountId)
            ->inDateRange($todayStart, $todayEnd)
            ->sum('time_spent_seconds');

        $weekSeconds = JiraWorklog::forAuthor($accountId)
            ->inDateRange($weekStart, $weekEnd)
            ->sum('time_spent_seconds');

        $recentWorklogs = JiraWorklog::forAuthor($accountId)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        $openIssues = JiraIssue::forProject($projectKey)
            ->assignedTo($accountId)
            ->open()
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $teamWorklogs = JiraWorklog::forProject($projectKey)
            ->inDateRange($weekStart, $weekEnd)
            ->selectRaw('author_account_id, author_display_name, SUM(time_spent_seconds) as total_seconds')
            ->groupBy('author_account_id', 'author_display_name')
            ->orderByDesc('total_seconds')
            ->get();

        return view('dashboard.index', compact(
            'todaySeconds',
            'weekSeconds',
            'recentWorklogs',
            'openIssues',
            'teamWorklogs',
        ));
    }
}
