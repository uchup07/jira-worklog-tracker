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
        $validated = $request->validate([
            'project_key' => 'required|string',
            'project_name' => 'nullable|string',
        ]);

        $projectName = $this->resolveProjectName($validated['project_key'], $validated['project_name'] ?? null);

        return $this->setProjectAndRedirect($validated['project_key'], $projectName, $backgroundSyncService);
    }

    protected function setProjectAndRedirect(string $projectKey, string $projectName, JiraBackgroundSyncService $backgroundSyncService)
    {
        Settings::set('selected_project_key', $projectKey);
        Settings::set('selected_project_name', $projectName);

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

    protected function resolveProjectName(string $projectKey, ?string $projectName = null): string
    {
        $projectName = trim((string) $projectName);

        if ($projectName !== '') {
            return $projectName;
        }

        try {
            $project = collect(JiraApiService::fromSettings()->getProjects())
                ->firstWhere('key', $projectKey);

            if (is_array($project) && filled($project['name'] ?? null)) {
                return $project['name'];
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $projectKey;
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

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'required|in:dark,light',
        ]);

        Settings::set('app_theme', $validated['theme']);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return back();
    }

    public function disconnect()
    {
        foreach (['jira_domain', 'jira_email', 'jira_api_token', 'jira_account_id', 'jira_display_name', 'selected_project_key', 'selected_project_name', 'last_synced_at', 'sync_in_progress', 'sync_started_at'] as $key) {
            Settings::forget($key);
        }

        return redirect()->route('setup');
    }
}
