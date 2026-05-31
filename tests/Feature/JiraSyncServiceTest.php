<?php

namespace Tests\Feature;

use App\Services\JiraApiService;
use App\Services\JiraSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class JiraSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_project_persists_project_users_from_assignable_users_assignees_and_authors(): void
    {
        $api = Mockery::mock(JiraApiService::class, ['example.atlassian.net', 'user@example.com', 'token']);
        $api->shouldReceive('searchIssues')->once()->with('project = "PROJ" ORDER BY updated DESC', 200)->andReturn([
            [
                'key' => 'PROJ-1',
                'fields' => [
                    'summary' => 'Example issue',
                    'status' => ['name' => 'In Progress'],
                    'project' => ['key' => 'PROJ'],
                    'assignee' => ['accountId' => 'assignee-1', 'displayName' => 'Assignee User'],
                    'priority' => ['name' => 'Medium'],
                    'issuetype' => ['name' => 'Task'],
                ],
            ],
        ]);
        $api->shouldReceive('getWorklogsForIssue')->once()->with('PROJ-1')->andReturn([
            [
                'id' => '1001',
                'author' => ['accountId' => 'author-1', 'displayName' => 'Author User'],
                'timeSpentSeconds' => 3600,
                'started' => '2026-05-31T00:00:00.000+0000',
            ],
        ]);
        $api->shouldReceive('getAssignableUsersForProject')->once()->with('PROJ')->andReturn([
            [
                'accountId' => 'assignable-1',
                'displayName' => 'Assignable User',
                'active' => true,
            ],
        ]);

        Settings::shouldReceive('set')->once()->with('last_synced_at', Mockery::type('string'));

        $result = (new JiraSyncService($api))->syncProject('PROJ');

        $this->assertSame(1, $result['issues']);
        $this->assertSame(1, $result['worklogs']);
        $this->assertSame(3, $result['users']);

        $this->assertDatabaseHas('jira_project_users', [
            'project_key' => 'PROJ',
            'account_id' => 'assignable-1',
            'display_name' => 'Assignable User',
            'source' => 'assignable',
        ]);

        $this->assertDatabaseHas('jira_project_users', [
            'project_key' => 'PROJ',
            'account_id' => 'assignee-1',
            'display_name' => 'Assignee User',
            'source' => 'issue-assignee',
        ]);

        $this->assertDatabaseHas('jira_project_users', [
            'project_key' => 'PROJ',
            'account_id' => 'author-1',
            'display_name' => 'Author User',
            'source' => 'worklog-author',
        ]);
    }
}
