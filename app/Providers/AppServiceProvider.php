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

        // Offset sidebar below NativePHP 38px drag strip; explicit height so background fills viewport
        TallStackUi::customize()
            ->sideBar()
            //->block('desktop.wrapper.first.base', 'fixed left-0 top-[38px] z-50 flex flex-col')
            ->block('desktop.wrapper.first.base')->append('mt-[38px]')
            ->block('desktop.wrapper.second', 'dark:bg-dark-700 dark:border-dark-600 flex grow flex-col border-r border-gray-200 bg-white pb-4 transition-[width] duration-300')
            ->block('desktop.wrapper.third', 'flex flex-col')
            ->block('desktop.wrapper.fourth', 'flex flex-1 flex-col');

        // Height chain: wrapper.first fills space below 38px titlebar; wrapper.second is flex column
        // so the header takes natural height and main expands to fill the rest with scroll
        TallStackUi::customize()
            ->layout()
            ->block('wrapper.first', 'flex-1 h-[calc(100vh-38px)]')
            ->block('wrapper.second.expanded', 'md:pl-72 transition-[padding] duration-300 h-full flex flex-col')
            ->block('wrapper.second.collapsed', 'md:pl-22 transition-[padding] duration-300 h-full flex flex-col')
            ->block('main', 'flex-1 min-h-0 overflow-y-auto px-5 py-4 max-w-full');
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
