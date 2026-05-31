<?php

namespace Tests\Feature;

use App\Jobs\SyncJiraProjectJob;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    public function test_sync_route_dispatches_background_job(): void
    {
        Queue::fake();

        Settings::shouldReceive('get')
            ->andReturnUsing(function (string $key, mixed $default = null) {
                return match ($key) {
                    'jira_domain' => 'example.atlassian.net',
                    'jira_api_token' => 'token',
                    'selected_project_key' => 'EB',
                    'sync_in_progress' => false,
                    default => $default,
                };
            });

        Settings::shouldReceive('set')->once()->with('sync_in_progress', true);
        Settings::shouldReceive('set')->once()->with('sync_started_at', Mockery::type('string'));

        $response = $this->from('/dashboard')->post(route('sync'));

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success', 'Background sync started. You will get a desktop notification when it finishes.');

        Queue::assertPushed(SyncJiraProjectJob::class, function (SyncJiraProjectJob $job) {
            return $job->projectKey === 'EB';
        });
    }

    public function test_sync_route_rejects_when_background_sync_is_already_running(): void
    {
        Queue::fake();

        Settings::shouldReceive('get')
            ->andReturnUsing(function (string $key, mixed $default = null) {
                return match ($key) {
                    'jira_domain' => 'example.atlassian.net',
                    'jira_api_token' => 'token',
                    'selected_project_key' => 'EB',
                    'sync_in_progress' => true,
                    'sync_started_at' => now()->subSeconds(5)->toISOString(),
                    default => $default,
                };
            });

        $response = $this->from('/dashboard')->post(route('sync'));

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error', 'Sync failed: Sync is already running in the background.');
        Queue::assertNothingPushed();
    }
}
