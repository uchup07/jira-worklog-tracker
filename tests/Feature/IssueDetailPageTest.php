<?php

namespace Tests\Feature;

use App\Models\JiraIssue;
use App\Models\JiraWorklog;
use App\Services\JiraBackgroundSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Notification;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class IssueDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_detail_page_shows_issue_data_and_worklogs(): void
    {
        $issue = JiraIssue::create([
            'issue_key' => 'EB-42',
            'summary' => 'Build issue detail page',
            'status' => 'In Progress',
            'project_key' => 'EB',
            'assignee_account_id' => 'user-1',
            'assignee_display_name' => 'Yusuf',
            'priority' => 'High',
            'issue_type' => 'Task',
            'synced_at' => now(),
        ]);

        JiraWorklog::create([
            'jira_worklog_id' => '1001',
            'issue_key' => 'EB-42',
            'author_account_id' => 'user-1',
            'author_display_name' => 'Yusuf',
            'time_spent_seconds' => 5400,
            'started_at' => now()->subDay(),
            'comment' => 'Implemented the detail screen',
            'synced_at' => now(),
        ]);

        JiraWorklog::create([
            'jira_worklog_id' => '1002',
            'issue_key' => 'EB-42',
            'author_account_id' => 'user-2',
            'author_display_name' => 'Teammate',
            'time_spent_seconds' => 1800,
            'started_at' => now(),
            'comment' => 'Reviewed the UI changes',
            'synced_at' => now(),
        ]);

        $this->mockConnectedSettings();

        $response = $this->get(route('issues.show', $issue));

        $response->assertOk();
        $response->assertSee('EB-42');
        $response->assertSee('Build issue detail page');
        $response->assertSee('Implemented the detail screen');
        $response->assertSee('Reviewed the UI changes');
        $response->assertSee('2 logs', escape: false);
    }

    public function test_worklog_created_from_issue_detail_redirects_back_to_issue_page(): void
    {
        $issue = JiraIssue::create([
            'issue_key' => 'EB-42',
            'summary' => 'Build issue detail page',
            'status' => 'In Progress',
            'project_key' => 'EB',
            'assignee_account_id' => 'user-1',
            'assignee_display_name' => 'Yusuf',
            'priority' => 'High',
            'issue_type' => 'Task',
            'synced_at' => now(),
        ]);

        $this->mockConnectedSettings();

        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue/EB-42/worklog' => Http::response([
                'id' => '9001',
            ], 201),
        ]);

        $backgroundSyncService = \Mockery::mock(JiraBackgroundSyncService::class);
        $backgroundSyncService->shouldReceive('dispatch')->once()->with('EB');
        $this->app->instance(JiraBackgroundSyncService::class, $backgroundSyncService);

        Notification::shouldReceive('new')->once()->andReturnSelf();
        Notification::shouldReceive('title')->once()->with('Worklog Created')->andReturnSelf();
        Notification::shouldReceive('message')->once()->with('1.5h logged to EB-42')->andReturnSelf();
        Notification::shouldReceive('show')->once();

        $response = $this->post(route('worklogs.store'), [
            'issue_key' => 'EB-42',
            'time_spent' => '1h 30m',
            'started_at' => now()->toDateString(),
            'comment' => 'Logged from issue detail',
            'return_to_issue' => '1',
        ]);

        $response->assertRedirect(route('issues.show', $issue));
        $response->assertSessionHas('success', 'Worklog created successfully.');
    }

    private function mockConnectedSettings(): void
    {
        Settings::shouldReceive('get')
            ->andReturnUsing(function (string $key, mixed $default = null) {
                return match ($key) {
                    'jira_domain' => 'example.atlassian.net',
                    'jira_email' => 'user@example.com',
                    'jira_api_token' => 'token',
                    'selected_project_key' => 'EB',
                    default => $default,
                };
            });
    }
}
