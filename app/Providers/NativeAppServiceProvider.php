<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        Window::open()
            ->width(1280)
            ->height(800)
            ->minWidth(900)
            ->minHeight(600)
            ->title('Jira Worklog Tracker')
            ->url(route('home'))
            ->rememberState()
            ->titleBarHiddenInset();

        Menu::create(
            Menu::app(),
            Menu::make(
                Menu::route('worklogs.create', 'New Worklog', 'CmdOrCtrl+N'),
                Menu::separator(),
                Menu::route('worklogs.index', 'View Worklogs', null),
                Menu::route('issues.index', 'My Issues', null),
                Menu::separator(),
                Menu::label('Sync with Jira', 'CmdOrCtrl+R')->event('jira.sync'),
            )->label('Worklog'),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );
    }

    public function phpIni(): array
    {
        return [];
    }
}
