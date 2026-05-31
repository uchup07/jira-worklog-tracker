<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Native\Desktop\Facades\Settings;
use TallStackUi\Facades\TallStackUi;
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

        View::composer('*', function ($view) {
            $view->with('projectKey', Settings::get('selected_project_key', 'No project'));
            $view->with('projectName', Settings::get('selected_project_name'));
            $view->with('appTheme', $this->resolveAppTheme());
            $lastSynced = Settings::get('last_synced_at', null);
            $view->with('lastSynced', $lastSynced ? Carbon::parse($lastSynced)->diffForHumans() : null);
        });

        // Offset sidebar below NativePHP 38px drag strip; fix nav items height constraint
        TallStackUi::customize()
            ->sideBar()
            ->block('desktop.wrapper.first.base', 'fixed left-0 bottom-0 z-50 flex flex-col top-[38px]')
            ->block('desktop.wrapper.third', 'flex flex-col')
            ->block('desktop.wrapper.fourth', 'flex flex-1 flex-col');

        // Make content area fill viewport; reduce excessive padding; enable content scroll
        TallStackUi::customize()
            ->layout()
            ->block('wrapper.first', 'min-h-screen')
            ->block('main', 'overflow-y-auto px-5 py-4 max-w-full');
    }

    protected function resolveAppTheme(): string
    {
        $theme = Settings::get('app_theme', 'dark');

        return in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';
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
