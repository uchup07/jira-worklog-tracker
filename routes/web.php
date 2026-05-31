<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WorklogController;
use App\Http\Middleware\EnsureJiraConnected;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
Route::get('/setup/project', [SetupController::class, 'selectProject'])->name('setup.project');
Route::post('/setup/project', [SetupController::class, 'storeProject'])->name('setup.project.store');
Route::post('/theme', [SetupController::class, 'updateTheme'])->name('theme.update');
Route::post('/setup/disconnect', [SetupController::class, 'disconnect'])->name('setup.disconnect');

Route::middleware(EnsureJiraConnected::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');

    Route::get('/worklogs', [WorklogController::class, 'index'])->name('worklogs.index');
    Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');
    Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');

    Route::post('/sync', [SyncController::class, 'sync'])->name('sync');

    Route::get('/settings', [SetupController::class, 'settings'])->name('settings');
});
