<?php

namespace App\Http\Controllers;

use App\Services\JiraBackgroundSyncService;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Settings;

class SyncController extends Controller
{
    public function sync(Request $request, JiraBackgroundSyncService $backgroundSyncService)
    {
        $projectKey = Settings::get('selected_project_key', '');

        if (empty($projectKey)) {
            return redirect()->back()->with('error', 'No project selected. Please complete setup first.');
        }

        try {
            $backgroundSyncService->dispatch($projectKey);

            return redirect()->back()->with('success', 'Background sync started. You will get a desktop notification when it finishes.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }
}
