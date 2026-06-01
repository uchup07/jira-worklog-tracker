<?php

namespace App\Services;

use App\Models\JiraIssue;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Native\Desktop\Facades\Settings;

class JiraSyncService
{
    public function __construct(private JiraApiService $api) {}

    public static function make(): static
    {
        return new static(JiraApiService::fromSettings());
    }

    public function syncProject(string $projectKey): array
    {
        $issuesSynced = $this->syncIssues($projectKey);
        $worklogsSynced = $this->syncWorklogs($projectKey);
        $usersSynced = $this->syncProjectUsers($projectKey);

        Settings::set('last_synced_at', now()->toISOString());

        return [
            'issues' => $issuesSynced,
            'worklogs' => $worklogsSynced,
            'users' => $usersSynced,
        ];
    }

    private function syncIssues(string $projectKey): int
    {
        $jql = 'project = "'.addslashes($projectKey).'" ORDER BY updated DESC';

        try {
            $issues = $this->api->searchIssues($jql, 200);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Failed to search Jira issues for project {$projectKey}: {$e->getMessage()}", previous: $e);
        }

        if (empty($issues)) {
            return 0;
        }

        $rows = array_map(function ($issue) {
            $fields = $issue['fields'];

            return [
                'issue_key' => $issue['key'],
                'summary' => $fields['summary'] ?? '',
                'status' => $fields['status']['name'] ?? 'Unknown',
                'project_key' => $fields['project']['key'] ?? '',
                'assignee_account_id' => $fields['assignee']['accountId'] ?? null,
                'assignee_display_name' => $fields['assignee']['displayName'] ?? null,
                'priority' => $fields['priority']['name'] ?? null,
                'issue_type' => $fields['issuetype']['name'] ?? 'Task',
                'sprint' => $this->extractSprint($fields['customfield_10020'] ?? null),
                'epic' => $fields['customfield_10014'] ?? null,
                'synced_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }, $issues);

        JiraIssue::upsert($rows, ['issue_key'], [
            'summary', 'status', 'project_key', 'assignee_account_id',
            'assignee_display_name', 'priority', 'issue_type', 'sprint', 'epic',
            'synced_at', 'updated_at',
        ]);

        return count($rows);
    }

    private function syncWorklogs(string $projectKey): int
    {
        $issueKeys = JiraIssue::forProject($projectKey)->pluck('issue_key')->toArray();

        if (empty($issueKeys)) {
            return 0;
        }

        $rows = [];

        foreach ($issueKeys as $issueKey) {
            try {
                $worklogs = $this->api->getWorklogsForIssue($issueKey);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException("Failed to fetch Jira worklogs for issue {$issueKey}: {$e->getMessage()}", previous: $e);
            }

            foreach ($worklogs as $worklog) {
                $rows[] = [
                    'jira_worklog_id' => (string) $worklog['id'],
                    'issue_key' => $issueKey,
                    'author_account_id' => $worklog['author']['accountId'] ?? '',
                    'author_display_name' => $worklog['author']['displayName'] ?? '',
                    'time_spent_seconds' => $worklog['timeSpentSeconds'] ?? 0,
                    'started_at' => isset($worklog['started'])
                        ? Carbon::parse($worklog['started'])->toDateTimeString()
                        : null,
                    'comment' => $this->extractComment($worklog['comment'] ?? null),
                    'synced_at' => now()->toDateTimeString(),
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }
        }

        if (empty($rows)) {
            return 0;
        }

        JiraWorklog::upsert($rows, ['jira_worklog_id'], [
            'author_account_id', 'author_display_name', 'time_spent_seconds',
            'started_at', 'comment', 'synced_at', 'updated_at',
        ]);

        return count($rows);
    }

    private function syncProjectUsers(string $projectKey): int
    {
        try {
            $assignableUsers = $this->api->getAssignableUsersForProject($projectKey);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Failed to fetch Jira users for project {$projectKey}: {$e->getMessage()}", previous: $e);
        }

        $users = collect($assignableUsers)
            ->map(fn (array $user) => [
                'project_key' => $projectKey,
                'account_id' => $user['accountId'] ?? null,
                'display_name' => $user['displayName'] ?? null,
                'email' => $user['emailAddress'] ?? null,
                'active' => $user['active'] ?? true,
                'source' => 'assignable',
            ])
            ->filter(fn (array $user) => filled($user['account_id']) && filled($user['display_name']));

        $issueAssignees = JiraIssue::forProject($projectKey)
            ->whereNotNull('assignee_account_id')
            ->whereNotNull('assignee_display_name')
            ->get()
            ->map(fn (JiraIssue $issue) => [
                'project_key' => $projectKey,
                'account_id' => $issue->assignee_account_id,
                'display_name' => $issue->assignee_display_name,
                'email' => null,
                'active' => true,
                'source' => 'issue-assignee',
            ]);

        $worklogAuthors = JiraWorklog::forProject($projectKey)
            ->select('author_account_id', 'author_display_name')
            ->distinct()
            ->get()
            ->map(fn (JiraWorklog $worklog) => [
                'project_key' => $projectKey,
                'account_id' => $worklog->author_account_id,
                'display_name' => $worklog->author_display_name,
                'email' => null,
                'active' => true,
                'source' => 'worklog-author',
            ]);

        $rows = $users
            ->concat($issueAssignees)
            ->concat($worklogAuthors)
            ->unique(fn (array $user) => $user['project_key'].'|'.$user['account_id'])
            ->map(fn (array $user) => [
                ...$user,
                'synced_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            return 0;
        }

        JiraProjectUser::upsert($rows, ['project_key', 'account_id'], [
            'display_name', 'active', 'source', 'email', 'synced_at', 'updated_at',
        ]);

        return count($rows);
    }

    public function extractSprint(?array $sprints): ?string
    {
        if (empty($sprints)) {
            return null;
        }
        $active = collect($sprints)->firstWhere('state', 'active');

        return ($active ?? $sprints[0])['name'] ?? null;
    }

    private function extractComment(mixed $comment): string
    {
        if (is_null($comment)) {
            return '';
        }

        if (is_array($comment) && isset($comment['content'])) {
            return $this->extractAdfText($comment);
        }

        if (is_string($comment)) {
            return $comment;
        }

        return '';
    }

    private function extractAdfText(array $node): string
    {
        if (isset($node['text'])) {
            return $node['text'];
        }

        if (empty($node['content'])) {
            return '';
        }

        return implode('', array_map(
            fn ($child) => $this->extractAdfText($child),
            $node['content']
        ));
    }
}
