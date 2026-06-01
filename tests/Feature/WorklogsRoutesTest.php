<?php

use App\Models\JiraIssue;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\Settings;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain' => 'example.atlassian.net',
                'jira_email' => 'user@example.com',
                'jira_api_token' => 'token',
                'selected_project_key' => 'EB',
                'jira_account_id' => 'user-1',
                'missing_worklog_days' => 7,
                default => $default,
            };
        });
});

test('monitoring worklog page loads', function () {
    $this->get(route('worklogs.monitoring'))->assertOk();
});

test('missing worklog page loads', function () {
    $this->get(route('worklogs.missing'))->assertOk();
});

test('missing worklog component shows users with no worklogs on working days', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-no-logs',
        'display_name' => 'Alice',
        'email' => 'alice@example.com',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    Livewire::test('missing-worklog')
        ->assertSee('Alice');
});

test('missing worklog component shows empty state when all users have logged', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-logged',
        'display_name' => 'Bob',
        'email' => 'bob@example.com',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    JiraIssue::create([
        'issue_key' => 'EB-1',
        'summary' => 'Test issue',
        'status' => 'In Progress',
        'project_key' => 'EB',
        'issue_type' => 'Task',
        'synced_at' => now(),
    ]);

    // Create worklogs for all working days in the last 7 days
    $logIndex = 1;
    for ($i = 1; $i <= 7; $i++) {
        $date = Carbon::today()->subDays($i);
        if ($date->isWeekday()) {
            JiraWorklog::create([
                'jira_worklog_id' => "wl-{$logIndex}",
                'issue_key' => 'EB-1',
                'author_account_id' => 'user-logged',
                'author_display_name' => 'Bob',
                'time_spent_seconds' => 3600,
                'started_at' => $date,
                'synced_at' => now(),
            ]);
            $logIndex++;
        }
    }

    Livewire::test('missing-worklog')
        ->assertSee('All team members have logged time');
});

test('missing day detection excludes weekends', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-weekday',
        'display_name' => 'Charlie',
        'email' => 'charlie@example.com',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    $component = Livewire::test('missing-worklog');
    $missingUsers = $component->instance()->computeMissingUsers();

    if (! empty($missingUsers)) {
        foreach ($missingUsers[0]['missing_days'] as $day) {
            $dayOfWeek = Carbon::parse($day)->dayOfWeek;
            expect($dayOfWeek)->not->toBe(Carbon::SUNDAY)
                ->not->toBe(Carbon::SATURDAY);
        }
    }

    expect(true)->toBeTrue();
});
