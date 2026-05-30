<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Native\Desktop\Facades\Settings;

class EnsureJiraConnected
{
    public function handle(Request $request, Closure $next)
    {
        if (empty(Settings::get('jira_domain', '')) || empty(Settings::get('jira_api_token', ''))) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
