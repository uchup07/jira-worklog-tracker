<?php

namespace Tests\Feature;

use Native\Desktop\Facades\Settings;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    public function test_theme_preference_can_be_updated(): void
    {
        Settings::shouldReceive('set')->once()->with('app_theme', 'light');

        $response = $this->from(route('setup'))->post(route('theme.update'), [
            'theme' => 'light',
        ]);

        $response->assertRedirect(route('setup'));
    }

    public function test_setup_page_uses_saved_theme(): void
    {
        Settings::shouldReceive('get')
            ->andReturnUsing(function (string $key, mixed $default = null) {
                return match ($key) {
                    'app_theme' => 'light',
                    'selected_project_key' => 'No project',
                    'selected_project_name' => null,
                    'last_synced_at' => null,
                    'jira_domain' => '',
                    'jira_email' => '',
                    default => $default,
                };
            });

        $response = $this->get(route('setup'));

        $response->assertOk();
        $response->assertSee('data-theme="light"', false);
    }
}
