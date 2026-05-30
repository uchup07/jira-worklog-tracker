<?php

namespace App\Services;

use App\Models\JiraIssue;
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

        Settings::set('last_synced_at', now()->toISOString());

        return [
            'issues' => $issuesSynced,
            'worklogs' => $worklogsSynced,
        ];
    }

    private function syncIssues(string $projectKey): int
    {
        $jql = 'project = "'.addslashes($projectKey).'" ORDER BY updated DESC';
        $issues = $this->api->searchIssues($jql, 200);

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
                'synced_at' => now()->toDateTimeString(),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }, $issues);

        JiraIssue::upsert($rows, ['issue_key'], [
            'summary', 'status', 'project_key', 'assignee_account_id',
            'assignee_display_name', 'priority', 'issue_type', 'synced_at', 'updated_at',
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
            $worklogs = $this->api->getWorklogsForIssue($issueKey);

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
