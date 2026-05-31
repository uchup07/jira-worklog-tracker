<?php

namespace App\Http\Controllers;

use App\Services\JiraBackgroundSyncService;
use App\Services\JiraApiService;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Settings;

class SetupController extends Controller
{
    public function show()
    {
        return view('setup.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'email' => 'required|email',
            'api_token' => 'required|string',
        ]);

        $domain = trim($request->domain, '/');
        $domain = preg_replace('#^https?://#', '', $domain);

        $result = JiraApiService::validateCredentials($domain, $request->email, $request->api_token);

        if (! $result['success']) {
            return back()->withErrors(['api_token' => 'Could not connect to Jira: '.$result['error']])->withInput();
        }

        Settings::set('jira_domain', $domain);
        Settings::set('jira_email', $request->email);
        Settings::set('jira_api_token', $request->api_token);
        Settings::set('jira_account_id', $result['user']['accountId'] ?? '');
        Settings::set('jira_display_name', $result['user']['displayName'] ?? '');

        return redirect()->route('setup.project');
    }

    public function selectProject()
    {
        $service = JiraApiService::fromSettings();
        $projects = $service->getProjects();

        return view('setup.project', compact('projects'));
    }

    public function storeProject(Request $request, JiraBackgroundSyncService $backgroundSyncService)
    {
        $request->validate(['project_key' => 'required|string']);

        return $this->setProjectAndRedirect($request->project_key, $backgroundSyncService);
    }

    protected function setProjectAndRedirect(string $projectKey, JiraBackgroundSyncService $backgroundSyncService)
    {
        Settings::set('selected_project_key', $projectKey);

        $message = "Project changed to {$projectKey}.";

        try {
            $backgroundSyncService->dispatch($projectKey);
            $message .= ' Background sync started.';
        } catch (\RuntimeException $e) {
            report($e);
            $message .= ' Background sync was not started.';
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    public function settings()
    {
        return view('setup.index', [
            'prefill' => [
                'domain' => Settings::get('jira_domain', ''),
                'email' => Settings::get('jira_email', ''),
            ],
        ]);
    }

    public function disconnect()
    {
        foreach (['jira_domain', 'jira_email', 'jira_api_token', 'jira_account_id', 'jira_display_name', 'selected_project_key', 'last_synced_at', 'sync_in_progress', 'sync_started_at'] as $key) {
            Settings::forget($key);
        }

        return redirect()->route('setup');
    }
}
