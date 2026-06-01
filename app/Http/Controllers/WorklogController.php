<?php

namespace App\Http\Controllers;

use App\Models\JiraIssue;
use App\Services\JiraApiService;
use App\Services\JiraBackgroundSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Native\Desktop\Facades\Notification;
use Native\Desktop\Facades\Settings;

class WorklogController extends Controller
{
    public function monitoring(): View
    {
        return view('worklogs.monitoring');
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

        $redirectRoute = ! empty($validated['return_to_issue']) ? 'issues.show' : 'worklogs.monitoring';
        $redirectParameters = ! empty($validated['return_to_issue']) ? ['issue' => $validated['issue_key']] : [];

        return redirect()->route($redirectRoute, $redirectParameters)->with('success', 'Worklog created successfully.');
    }
}
