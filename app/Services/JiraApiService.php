<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Settings;

class JiraApiService
{
    private const SEARCH_PAGE_SIZE = 100;

    private const WORKLOG_PAGE_SIZE = 100;

    private const USER_PAGE_SIZE = 100;

    public function __construct(
        private string $domain,
        private string $email,
        private string $apiToken,
    ) {}

    public static function fromSettings(): static
    {
        $domain = Settings::get('jira_domain', '');
        $email = Settings::get('jira_email', '');
        $token = Settings::get('jira_api_token', '');

        if (empty($domain) || empty($email) || empty($token)) {
            throw new \RuntimeException('Jira not configured');
        }

        return new static($domain, $email, $token);
    }

    public static function validateCredentials(string $domain, string $email, string $token): array
    {
        try {
            $response = Http::withBasicAuth($email, $token)
                ->acceptJson()
                ->timeout(15)
                ->get("https://{$domain}/rest/api/3/myself");

            if ($response->successful()) {
                return ['success' => true, 'user' => $response->json()];
            }

            return ['success' => false, 'error' => "HTTP {$response->status()}: {$response->body()}"];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function parseTimeToSeconds(string $input): int
    {
        $input = trim($input);

        // e.g. "1h 30m" or "1h30m"
        if (preg_match('/^(\d+)h\s*(\d+)m$/i', $input, $m)) {
            return ((int) $m[1] * 3600) + ((int) $m[2] * 60);
        }

        // e.g. "2h"
        if (preg_match('/^(\d+)h$/i', $input, $m)) {
            return (int) $m[1] * 3600;
        }

        // e.g. "30m" or "90m"
        if (preg_match('/^(\d+)m$/i', $input, $m)) {
            return (int) $m[1] * 60;
        }

        // pure integer — treat as seconds
        if (preg_match('/^\d+$/', $input)) {
            return (int) $input;
        }

        throw new \InvalidArgumentException("Cannot parse time string: {$input}");
    }

    public function getCurrentUser(): array
    {
        return $this->handleResponse(
            $this->baseRequest()->get('/myself')
        );
    }

    public function getProjects(int $maxResults = 50): array
    {
        $data = $this->handleResponse(
            $this->baseRequest()->get('/project', ['maxResults' => $maxResults, 'orderBy' => 'name'])
        );

        return array_map(fn ($p) => [
            'key' => $p['key'],
            'name' => $p['name'],
            'id' => $p['id'],
        ], $data);
    }

    public function searchIssues(string $jql, int $maxResults = 200, int $startAt = 0): array
    {
        $issues = [];
        $remaining = $maxResults;
        $offset = $startAt;

        while ($remaining > 0) {
            $pageSize = min(self::SEARCH_PAGE_SIZE, $remaining);
            $data = $this->searchIssuesPage($jql, $pageSize, $offset);
            $pageIssues = $data['issues'] ?? [];

            $issues = [...$issues, ...$pageIssues];

            if (count($pageIssues) < $pageSize) {
                break;
            }

            $offset += count($pageIssues);
            $remaining -= count($pageIssues);
        }

        return $issues;
    }

    public function getWorklogsForIssue(string $issueKey): array
    {
        $worklogs = [];
        $startAt = 0;

        do {
            $data = $this->handleResponse(
                $this->baseRequest()->get("/issue/{$issueKey}/worklog", [
                    'startAt' => $startAt,
                    'maxResults' => self::WORKLOG_PAGE_SIZE,
                ])
            );

            $pageWorklogs = $data['worklogs'] ?? [];
            $worklogs = [...$worklogs, ...$pageWorklogs];
            $startAt += count($pageWorklogs);
            $total = $data['total'] ?? count($worklogs);
        } while (! empty($pageWorklogs) && $startAt < $total);

        return $worklogs;
    }

    public function getAssignableUsersForProject(string $projectKey, int $maxResults = 1000): array
    {
        $users = [];
        $remaining = min($maxResults, 1000);
        $startAt = 0;

        while ($remaining > 0) {
            $pageSize = min(self::USER_PAGE_SIZE, $remaining);

            $pageUsers = $this->handleResponse(
                $this->baseRequest()->get('/user/assignable/search', [
                    'project' => $projectKey,
                    'startAt' => $startAt,
                    'maxResults' => $pageSize,
                ])
            );

            if (empty($pageUsers)) {
                break;
            }

            $users = [...$users, ...$pageUsers];

            if (count($pageUsers) < $pageSize) {
                break;
            }

            $startAt += count($pageUsers);
            $remaining -= count($pageUsers);
        }

        return $users;
    }

    private function searchIssuesPage(string $jql, int $maxResults, int $startAt): array
    {
        $fields = [
            'summary', 'status', 'priority', 'issuetype', 'assignee', 'project',
            'customfield_10020',   // Sprint
            'customfield_10014',   // Epic Link
        ];

        $payload = [
            'jql' => $jql,
            'maxResults' => $maxResults,
            'startAt' => $startAt,
            'fields' => $fields,
        ];

        // /search is still documented and accepts the classic request body shape.
        // Enhanced search payloads have been inconsistent across tenants, so use
        // /search first and fall back to enhanced GET if a tenant has removed it.
        $response = $this->baseRequest()->post('/search', $payload);

        if (in_array($response->status(), [404, 405, 410], true)) {
            $response = $this->baseRequest()->get('/search/jql', [
                'jql' => $jql,
                'maxResults' => $maxResults,
                'startAt' => $startAt,
                'fields' => implode(',', $fields),
            ]);
        }

        return $this->handleResponse($response);
    }

    public function createWorklog(string $issueKey, int $timeSpentSeconds, Carbon $started, string $comment = ''): array
    {
        $body = [
            'timeSpentSeconds' => $timeSpentSeconds,
            'started' => $started->format('Y-m-d\TH:i:s.000+0000'),
        ];

        if ($comment !== '') {
            $body['comment'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $comment]],
                    ],
                ],
            ];
        }

        return $this->handleResponse(
            $this->baseRequest()->post("/issue/{$issueKey}/worklog", $body)
        );
    }

    private function baseRequest()
    {
        return Http::withBasicAuth($this->email, $this->apiToken)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(15)
            ->baseUrl("https://{$this->domain}/rest/api/3");
    }

    private function handleResponse(Response $response): array
    {
        if ($response->status() >= 400) {
            throw new \RuntimeException("Jira API error {$response->status()}: {$response->body()}");
        }

        return $response->json() ?? [];
    }
}
