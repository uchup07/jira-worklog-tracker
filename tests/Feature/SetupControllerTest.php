<?php

namespace Tests\Feature;

use App\Services\JiraBackgroundSyncService;
use Mockery;
use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    public function test_store_project_persists_selected_project_key_and_name(): void
    {
        $backgroundSyncService = Mockery::mock(JiraBackgroundSyncService::class);
        $backgroundSyncService->shouldReceive('dispatch')->once()->with('EB');
        $this->app->instance(JiraBackgroundSyncService::class, $backgroundSyncService);

        Settings::shouldReceive('set')->once()->with('selected_project_key', 'EB');
        Settings::shouldReceive('set')->once()->with('selected_project_name', 'Engineering Board');

        $response = $this->post(route('setup.project.store'), [
            'project_key' => 'EB',
            'project_name' => 'Engineering Board',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Project changed to EB. Background sync started.');
    }

    public function test_store_project_falls_back_to_project_name_lookup_when_hidden_field_is_missing(): void
    {
        $backgroundSyncService = Mockery::mock(JiraBackgroundSyncService::class);
        $backgroundSyncService->shouldReceive('dispatch')->once()->with('EB');
        $this->app->instance(JiraBackgroundSyncService::class, $backgroundSyncService);

        Settings::shouldReceive('get')
            ->andReturnUsing(function (string $key, mixed $default = null) {
                return match ($key) {
                    'jira_domain' => 'example.atlassian.net',
                    'jira_email' => 'user@example.com',
                    'jira_api_token' => 'token',
                    default => $default,
                };
            });

        Settings::shouldReceive('set')->once()->with('selected_project_key', 'EB');
        Settings::shouldReceive('set')->once()->with('selected_project_name', 'Engineering Board');

        \Illuminate\Support\Facades\Http::fake([
            'https://example.atlassian.net/rest/api/3/project*' => \Illuminate\Support\Facades\Http::response([
                ['key' => 'EB', 'name' => 'Engineering Board', 'id' => '10000'],
            ], 200),
        ]);

        $response = $this->post(route('setup.project.store'), [
            'project_key' => 'EB',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Project changed to EB. Background sync started.');
    }
}
