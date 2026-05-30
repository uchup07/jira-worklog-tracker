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
}
