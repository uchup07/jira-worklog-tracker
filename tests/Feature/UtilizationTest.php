<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
