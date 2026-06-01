<?php

use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\Settings;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::now());

    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain' => 'example.atlassian.net',
                'jira_email' => 'user@example.com',
                'jira_api_token' => 'token',
                'selected_project_key' => 'EB',
                default => $default,
            };
        });
});

afterEach(function () {
    Carbon::setTestNow();
});

test('utilization page loads', function () {
    $this->get(route('utilization.index'))->assertOk();
});

test('utilization component defaults month to current month', function () {
    Livewire::test('utilization')
        ->assertSet('month', now()->format('Y-m'));
});

test('utilization component months list contains 12 entries', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return count($months) === 12;
        });
});

test('utilization component months list first entry is current month', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return $months[0]['value'] === now()->format('Y-m');
        });
});

test('utilization component months list last entry is 11 months ago', function () {
    Livewire::test('utilization')
        ->assertViewHas('months', function ($months) {
            return $months[11]['value'] === now()->subMonths(11)->format('Y-m');
        });
});

test('utilization rows include all active users from selected project', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-a',
        'display_name' => 'Alice',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-b',
        'display_name' => 'Bob',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $ids = array_column($rows, 'account_id');

            return in_array('user-a', $ids) && in_array('user-b', $ids);
        });
});

test('utilization excludes inactive users', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-inactive',
        'display_name' => 'Inactive',
        'active' => false,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            return ! in_array('user-inactive', array_column($rows, 'account_id'));
        });
});

test('utilization sums worklogs across all projects for a user', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-multi',
        'display_name' => 'Multi',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    $month = now()->format('Y-m');
    $date = now()->startOfMonth()->addDays(1)->format('Y-m-d H:i:s');

    // Worklog on project EB
    JiraWorklog::create([
        'jira_worklog_id' => 'wl-1',
        'issue_key' => 'EB-1',
        'author_account_id' => 'user-multi',
        'author_display_name' => 'Multi',
        'time_spent_seconds' => 3600,
        'started_at' => $date,
        'synced_at' => now(),
    ]);
    // Worklog on a different project
    JiraWorklog::create([
        'jira_worklog_id' => 'wl-2',
        'issue_key' => 'OTHER-1',
        'author_account_id' => 'user-multi',
        'author_display_name' => 'Multi',
        'time_spent_seconds' => 7200,
        'started_at' => $date,
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->set('month', $month)
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-multi');

            // 3600 + 7200 = 10800 seconds = 3h
            return $row && $row['actual_seconds'] === 10800;
        });
});

test('utilization user with no worklogs has 0 actual seconds and red band', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-zero',
        'display_name' => 'Zero',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-zero');

            return $row
                && $row['actual_seconds'] === 0
                && $row['color_band'] === 'red';
        });
});

test('utilization color band is green when utilization >= 90%', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-green',
        'display_name' => 'Green',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    // Count weekdays in current month and log exactly 90% of target
    $start = now()->startOfMonth();
    $end = now()->endOfMonth();
    $weekdays = 0;
    $d = $start->copy();
    while ($d->lte($end)) {
        if ($d->isWeekday()) {
            $weekdays++;
        }
        $d->addDay();
    }
    $targetSeconds = $weekdays * 8 * 3600;
    $ninetyPct = (int) ($targetSeconds * 0.90);

    JiraWorklog::create([
        'jira_worklog_id' => 'wl-green',
        'issue_key' => 'EB-2',
        'author_account_id' => 'user-green',
        'author_display_name' => 'Green',
        'time_spent_seconds' => $ninetyPct,
        'started_at' => now()->startOfMonth()->addDay()->format('Y-m-d H:i:s'),
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-green');

            return $row && $row['color_band'] === 'green';
        });
});

test('utilization color band is yellow when utilization is between 70% and 89.9%', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-yellow',
        'display_name' => 'Yellow',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    $start = now()->startOfMonth();
    $end = now()->endOfMonth();
    $weekdays = 0;
    $d = $start->copy();
    while ($d->lte($end)) {
        if ($d->isWeekday()) {
            $weekdays++;
        }
        $d->addDay();
    }
    $targetSeconds = $weekdays * 8 * 3600;
    $seventyFivePct = (int) ($targetSeconds * 0.75);

    JiraWorklog::create([
        'jira_worklog_id' => 'wl-yellow',
        'issue_key' => 'EB-3',
        'author_account_id' => 'user-yellow',
        'author_display_name' => 'Yellow',
        'time_spent_seconds' => $seventyFivePct,
        'started_at' => now()->startOfMonth()->addDay()->format('Y-m-d H:i:s'),
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $row = collect($rows)->firstWhere('account_id', 'user-yellow');

            return $row && $row['color_band'] === 'yellow';
        });
});

test('utilization rows are sorted descending by utilization_pct', function () {
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-high',
        'display_name' => 'High',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);
    JiraProjectUser::create([
        'project_key' => 'EB',
        'account_id' => 'user-low',
        'display_name' => 'Low',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    $date = now()->startOfMonth()->addDay()->format('Y-m-d H:i:s');

    JiraWorklog::create([
        'jira_worklog_id' => 'wl-high',
        'issue_key' => 'EB-10',
        'author_account_id' => 'user-high',
        'author_display_name' => 'High',
        'time_spent_seconds' => 57600, // 16h
        'started_at' => $date,
        'synced_at' => now(),
    ]);
    JiraWorklog::create([
        'jira_worklog_id' => 'wl-low',
        'issue_key' => 'EB-11',
        'author_account_id' => 'user-low',
        'author_display_name' => 'Low',
        'time_spent_seconds' => 3600, // 1h
        'started_at' => $date,
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            $ids = array_column($rows, 'account_id');
            $highPos = array_search('user-high', $ids);
            $lowPos = array_search('user-low', $ids);

            return $highPos !== false && $lowPos !== false && $highPos < $lowPos;
        });
});

test('utilization target hours equals 8 times weekday count in month', function () {
    // June 2026 has 22 weekdays (Mon 1 Jun – Tue 30 Jun) → 176h target
    Livewire::test('utilization')
        ->set('month', '2026-06')
        ->assertViewHas('workingDays', 22)
        ->assertViewHas('targetHours', 176.0);
});

test('utilization excludes users from other projects', function () {
    JiraProjectUser::create([
        'project_key' => 'OTHER',
        'account_id' => 'user-other-project',
        'display_name' => 'Other Project User',
        'active' => true,
        'source' => 'assignable',
        'synced_at' => now(),
    ]);

    Livewire::test('utilization')
        ->assertViewHas('rows', function ($rows) {
            return ! in_array('user-other-project', array_column($rows, 'account_id'));
        });
});
