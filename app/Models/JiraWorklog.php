<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JiraWorklog extends Model
{
    protected $fillable = [
        'jira_worklog_id',
        'issue_key',
        'author_account_id',
        'author_display_name',
        'time_spent_seconds',
        'started_at',
        'comment',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeForAuthor(Builder $query, string $accountId): Builder
    {
        return $query->where('author_account_id', $accountId);
    }

    public function scopeInDateRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('started_at', [$from, $to]);
    }

    public function scopeForProject(Builder $query, string $projectKey): Builder
    {
        if ($projectKey === '') {
            return $query;
        }

        return $query->whereIn('jira_worklogs.issue_key', function ($subQuery) use ($projectKey) {
            $subQuery->select('issue_key')
                ->from('jira_issues')
                ->where('project_key', $projectKey);
        });
    }
}
