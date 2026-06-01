<?php

use App\Services\JiraApiService;
use App\Services\JiraSyncService;

function makeSyncService(): JiraSyncService
{
    $api = Mockery::mock(JiraApiService::class);

    return new JiraSyncService($api);
}

test('extractSprint returns null for null input', function () {
    expect(makeSyncService()->extractSprint(null))->toBeNull();
});

test('extractSprint returns null for empty array', function () {
    expect(makeSyncService()->extractSprint([]))->toBeNull();
});

test('extractSprint returns name of the active sprint', function () {
    $sprints = [
        ['name' => 'Sprint 1', 'state' => 'closed'],
        ['name' => 'Sprint 2', 'state' => 'active'],
        ['name' => 'Sprint 3', 'state' => 'future'],
    ];
    expect(makeSyncService()->extractSprint($sprints))->toBe('Sprint 2');
});

test('extractSprint falls back to first sprint when none is active', function () {
    $sprints = [
        ['name' => 'Sprint 1', 'state' => 'closed'],
        ['name' => 'Sprint 2', 'state' => 'closed'],
    ];
    expect(makeSyncService()->extractSprint($sprints))->toBe('Sprint 1');
});
