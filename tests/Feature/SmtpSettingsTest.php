<?php

use Native\Desktop\Facades\Settings;

beforeEach(function () {
    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain' => 'example.atlassian.net',
                'jira_email' => 'user@example.com',
                'jira_api_token' => 'token',
                default => $default,
            };
        });
});

test('smtp settings can be saved', function () {
    Settings::shouldReceive('set')->times(7);

    $response = $this->post(route('setup.smtp'), [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'secret',
        'smtp_from_address' => 'user@example.com',
        'smtp_from_name' => 'Worklog Tracker',
        'smtp_encryption' => 'tls',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'SMTP settings saved.');
});

test('smtp settings requires host and valid from_address', function () {
    $response = $this->post(route('setup.smtp'), [
        'smtp_host' => '',
        'smtp_from_address' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['smtp_host', 'smtp_from_address']);
});
