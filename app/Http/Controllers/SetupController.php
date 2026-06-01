<?php

namespace App\Http\Controllers;

use App\Services\JiraApiService;
use App\Services\JiraBackgroundSyncService;
use Illuminate\Http\RedirectResponse;
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

    public function updateSmtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string',
            'smtp_password' => 'nullable|string',
            'smtp_from_address' => 'required|email',
            'smtp_from_name' => 'required|string',
            'smtp_encryption' => 'nullable|in:tls,ssl',
        ]);

        foreach ($validated as $key => $value) {
            Settings::set($key, $value);
        }

        return redirect()->back()->with('success', 'SMTP settings saved.');
    }
}
