<?php

namespace App\Http\Controllers;

use App\Services\JiraSyncService;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Settings;

class SyncController extends Controller
{
    public function sync(Request $request)
    {
        $projectKey = Settings::get('selected_project_key', '');

        if (empty($projectKey)) {
            return redirect()->back()->with('error', 'No project selected. Please complete setup first.');
        }

        try {
            $result = JiraSyncService::make()->syncProject($projectKey);

            $message = "Synced {$result['issues']} issues and {$result['worklogs']} worklogs.";

            return redirect()->back()->with('success', $message);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', 'Sync failed: '.$e->getMessage());
        }
    }
}
