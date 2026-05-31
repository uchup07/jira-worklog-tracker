<?php

namespace Tests\Unit;

use App\Services\JiraApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JiraApiServiceTest extends TestCase
{
    public function test_search_issues_uses_classic_search_endpoint(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/search' => Http::response([
                'issues' => [
                    ['key' => 'PROJ-1', 'fields' => ['summary' => 'Issue 1']],
                ],
            ], 200),
        ]);

        $service = new JiraApiService('example.atlassian.net', 'user@example.com', 'token');

        $issues = $service->searchIssues('project = "PROJ"');

        $this->assertCount(1, $issues);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.atlassian.net/rest/api/3/search'
                && $request->method() === 'POST';
        });
    }

    public function test_search_issues_falls_back_to_enhanced_get_when_classic_search_is_unavailable(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/search' => Http::response([
                'errorMessages' => ['Method not allowed'],
            ], 405),
            'https://example.atlassian.net/rest/api/3/search/jql*' => Http::response([
                'issues' => [
                    ['key' => 'PROJ-2', 'fields' => ['summary' => 'Issue 2']],
                ],
            ], 200),
        ]);

        $service = new JiraApiService('example.atlassian.net', 'user@example.com', 'token');

        $issues = $service->searchIssues('project = "PROJ"');

        $this->assertCount(1, $issues);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://example.atlassian.net/rest/api/3/search/jql?')
                && $request->method() === 'GET';
        });
    }

    public function test_search_issues_paginates_classic_search_results(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/search' => function ($request) {
                $startAt = $request['startAt'];
                $maxResults = $request['maxResults'];
                $remaining = max(0, 150 - $startAt);
                $count = min($maxResults, $remaining);
                $issues = [];

                for ($i = 0; $i < $count; $i++) {
                    $index = $startAt + $i + 1;
                    $issues[] = ['key' => "PROJ-{$index}", 'fields' => ['summary' => "Issue {$index}"]];
                }

                return Http::response(['issues' => $issues], 200);
            },
        ]);

        $service = new JiraApiService('example.atlassian.net', 'user@example.com', 'token');

        $issues = $service->searchIssues('project = "PROJ"', 150);

        $this->assertCount(150, $issues);
        Http::assertSentCount(2);
    }

    public function test_get_worklogs_for_issue_paginates_results(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue/PROJ-1/worklog*' => function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

                $startAt = (int) ($query['startAt'] ?? 0);
                $maxResults = (int) ($query['maxResults'] ?? 100);
                $remaining = max(0, 125 - $startAt);
                $count = min($maxResults, $remaining);
                $worklogs = [];

                for ($i = 0; $i < $count; $i++) {
                    $index = $startAt + $i + 1;
                    $worklogs[] = [
                        'id' => (string) $index,
                        'author' => ['accountId' => 'user-1', 'displayName' => 'User One'],
                        'timeSpentSeconds' => 60,
                    ];
                }

                return Http::response([
                    'startAt' => $startAt,
                    'maxResults' => $maxResults,
                    'total' => 125,
                    'worklogs' => $worklogs,
                ], 200);
            },
        ]);

        $service = new JiraApiService('example.atlassian.net', 'user@example.com', 'token');

        $worklogs = $service->getWorklogsForIssue('PROJ-1');

        $this->assertCount(125, $worklogs);
        Http::assertSentCount(2);
    }

    public function test_get_assignable_users_for_project_paginates_results(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/user/assignable/search*' => function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

                $startAt = (int) ($query['startAt'] ?? 0);
                $maxResults = (int) ($query['maxResults'] ?? 100);
                $remaining = max(0, 125 - $startAt);
                $count = min($maxResults, $remaining);
                $users = [];

                for ($i = 0; $i < $count; $i++) {
                    $index = $startAt + $i + 1;
                    $users[] = [
                        'accountId' => "user-{$index}",
                        'displayName' => "User {$index}",
                        'active' => true,
                    ];
                }

                return Http::response($users, 200);
            },
        ]);

        $service = new JiraApiService('example.atlassian.net', 'user@example.com', 'token');

        $users = $service->getAssignableUsersForProject('PROJ', 125);

        $this->assertCount(125, $users);
        Http::assertSentCount(2);
    }
}
