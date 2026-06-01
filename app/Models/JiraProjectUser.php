<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JiraProjectUser extends Model
{
    protected $fillable = [
        'project_key',
        'account_id',
        'display_name',
        'email',
        'active',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeForProject(Builder $query, string $projectKey): Builder
    {
        return $query->where('project_key', $projectKey);
    }
}
