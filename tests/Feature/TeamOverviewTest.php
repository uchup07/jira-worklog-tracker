<?php

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
                default => $default,
            };
        });
});

test('team overview page loads', function () {
    $this->get(route('team-overview'))->assertOk();
});

test('period defaults to month', function () {
    Livewire::test('team-overview')
        ->assertSet('period', 'month');
});

test('period can be changed to week', function () {
    Livewire::test('team-overview')
        ->set('period', 'week')
        ->assertSet('period', 'week');
});

test('period can be changed to 3months', function () {
    Livewire::test('team-overview')
        ->set('period', '3months')
        ->assertSet('period', '3months');
});
