<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JiraIssue extends Model
{
    protected $fillable = [
        'issue_key',
        'summary',
        'status',
        'project_key',
        'assignee_account_id',
        'assignee_display_name',
        'priority',
        'issue_type',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function scopeForProject(Builder $query, string $projectKey): Builder
    {
        return $query->where('project_key', $projectKey);
    }

    public function scopeAssignedTo(Builder $query, string $accountId): Builder
    {
        return $query->where('assignee_account_id', $accountId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        // NOTE: Jira status names vary by project workflow; these cover the default Jira Cloud workflow
        return $query->whereNotIn('status', ['Done', 'Closed', 'Resolved']);
    }
}
