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
