<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Facades\Settings;
use Throwable;

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
        $this->ensureNativeDatabaseIsMigrated();

        View::composer('layouts.app', function ($view) {
            $view->with('projectKey', Settings::get('selected_project_key', 'No project'));
            $lastSynced = Settings::get('last_synced_at', null);
            $view->with('lastSynced', $lastSynced ? Carbon::parse($lastSynced)->diffForHumans() : null);
        });
    }

    protected function ensureNativeDatabaseIsMigrated(): void
    {
        if (! config('nativephp-internal.running') || ! config('app.debug') || app()->runningInConsole()) {
            return;
        }

        $migrationFiles = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME));

        try {
            $ranMigrations = DB::table('migrations')->pluck('migration');
        } catch (Throwable) {
            $ranMigrations = collect();
        }

        if ($migrationFiles->diff($ranMigrations)->isNotEmpty()) {
            Artisan::call('native:migrate', ['--force' => true]);
        }
    }
}
