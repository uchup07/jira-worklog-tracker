<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Models\JiraWorklog;
use App\Services\JiraApiService;
use App\Services\JiraBackgroundSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Notification;
use Native\Desktop\Facades\Settings;

class WorklogController extends Controller
{
    public function index(Request $request)
    {
        $projectKey = Settings::get('selected_project_key', '');
        $accountId = Settings::get('jira_account_id', '');

        $query = JiraWorklog::forProject($projectKey)
            ->orderByDesc('started_at');

        if ($request->filled('author')) {
            $query->forAuthor($request->author);
        }

        if ($request->boolean('mine')) {
            $query->forAuthor($accountId);
        }

        if ($request->filled('from')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : Carbon::now()->endOfDay();
            $query->inDateRange($from, $to);
        }

        $worklogs = $query->paginate(30);

        $authors = JiraWorklog::forProject($projectKey)
            ->selectRaw('author_account_id, author_display_name')
            ->groupBy('author_account_id', 'author_display_name')
            ->orderBy('author_display_name')
            ->get();

        return view('worklogs.index', compact('worklogs', 'authors', 'accountId'));
    }

    public function create(Request $request)
    {
        $projectKey = Settings::get('selected_project_key', '');
        $accountId = Settings::get('jira_account_id', '');
        $selectedIssue = $request->input('issue', '');

        $issues = JiraIssue::forProject($projectKey)
            ->assignedTo($accountId)
            ->open()
            ->orderBy('issue_key')
            ->get();

        return view('worklogs.create', compact('issues', 'selectedIssue'));
    }

    public function store(Request $request, JiraBackgroundSyncService $backgroundSyncService)
    {
        $validated = $request->validate([
            'issue_key' => 'required|string',
            'time_spent' => 'required|string',
            'started_at' => 'required|date',
            'comment' => 'nullable|string|max:2000',
            'return_to_issue' => 'nullable|boolean',
        ]);

        try {
            $seconds = JiraApiService::parseTimeToSeconds($validated['time_spent']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['time_spent' => 'Invalid time format. Use "1h 30m", "2h", or "30m".'])->withInput();
        }

        $started = Carbon::parse($validated['started_at'])->startOfDay()->utc();

        try {
            JiraApiService::fromSettings()->createWorklog(
                $validated['issue_key'], $seconds, $started, $validated['comment'] ?? ''
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Failed to create worklog: '.$e->getMessage())->withInput();
        }

        try {
            $backgroundSyncService->dispatch(Settings::get('selected_project_key', ''));
        } catch (\Throwable) {
            // Background sync failure should not block the user after successful creation.
        }

        $hours = round($seconds / 3600, 1);

        Notification::new()
            ->title('Worklog Created')
            ->message("{$hours}h logged to {$validated['issue_key']}")
            ->show();

        $redirectRoute = ! empty($validated['return_to_issue']) ? 'issues.show' : 'worklogs.index';
        $redirectParameters = ! empty($validated['return_to_issue']) ? ['issue' => $validated['issue_key']] : [];

        return redirect()->route($redirectRoute, $redirectParameters)->with('success', 'Worklog created successfully.');
    }
}
