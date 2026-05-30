<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Facades\Settings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $view->with('projectKey', Settings::get('selected_project_key', 'No project'));
            $lastSynced = Settings::get('last_synced_at', null);
            $view->with('lastSynced', $lastSynced ? Carbon::parse($lastSynced)->diffForHumans() : null);
        });
    }
}
