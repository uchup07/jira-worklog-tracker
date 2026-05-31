<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Models\JiraWorklog;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Settings;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $projectKey = Settings::get('selected_project_key', '');
        $accountId = Settings::get('jira_account_id', '');

        $query = JiraIssue::forProject($projectKey)
            ->assignedTo($accountId)
            ->orderByDesc('updated_at');

        if ($request->input('status') === 'open') {
            $query->open();
        }

        $issues = $query->get();

        return view('issues.index', compact('issues'));
    }

    public function show(JiraIssue $issue)
    {
        $projectKey = Settings::get('selected_project_key', '');

        abort_unless($issue->project_key === $projectKey, 404);

        $worklogs = JiraWorklog::query()
            ->where('issue_key', $issue->issue_key)
            ->orderByDesc('started_at')
            ->get();

        $totalLoggedSeconds = (int) $worklogs->sum('time_spent_seconds');

        return view('issues.show', compact('issue', 'worklogs', 'totalLoggedSeconds'));
    }
}
