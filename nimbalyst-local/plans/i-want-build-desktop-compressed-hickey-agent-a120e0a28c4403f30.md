# Task 3: Middleware, SetupController, and Views

## Goal
Create 5 files: middleware, controller, and 3 Blade views for the Jira Worklog Tracker setup flow.

## Context Gathered
- PHP 8.3, Laravel 13, NativePHP Desktop ^2.2, Tailwind CSS v4 (via `@tailwindcss/vite`), Alpine.js via CDN
- Vite processes `resources/css/app.css` and `resources/js/app.js`
- `app.css` uses `@import 'tailwindcss'` (v4 syntax — no config file)
- `Native\Desktop\Facades\Settings` is the NativePHP key-value store
- `JiraApiService::validateCredentials()` and `::fromSettings()` static methods exist
- `JiraApiService->getProjects()` returns array of `['key', 'name', 'id']`
- Base controller `App\Http\Controllers\Controller` is abstract with no methods
- No Middleware directory yet — must be created at `app/Http/Middleware/`
- No views subdirectories yet — must create `resources/views/layouts/` and `resources/views/setup/`
- Routes not wired yet (Task 8); views reference named routes that don't exist yet — this is expected

## Files to Create

### 1. `app/Http/Middleware/EnsureJiraConnected.php`
- Checks `Settings::get('jira_domain')` and `Settings::get('jira_api_token')` are non-empty
- Redirects to `route('setup')` if either is missing
- Otherwise passes request through

### 2. `app/Http/Controllers/SetupController.php`
- `show()` → renders `setup.index` view
- `store(Request)` → validates domain/email/api_token, strips `https://` prefix, calls `JiraApiService::validateCredentials()`, saves to Settings, redirects to `setup.project`
- `selectProject()` → calls `JiraApiService::fromSettings()->getProjects()`, renders `setup.project`
- `storeProject(Request)` → saves `selected_project_key` to Settings, redirects to `dashboard` with `run_initial_sync` flash
- `settings()` → renders `setup.index` with prefilled domain/email
- `disconnect()` → forgets all jira-related Settings keys, redirects to `setup`

### 3. `resources/views/layouts/app.blade.php`
Design: dark desktop-app shell, flat/minimalist aesthetic appropriate for a data-heavy tool app.

Structure:
- Full-height flex container: fixed left sidebar (200px) + flex-1 main area
- Sidebar: dark bg (`bg-gray-900`), app title "Jira Worklog Tracker" at top, nav links, "New Worklog" button, settings gear at bottom
- Active nav state via `request()->routeIs()`
- Main area: fixed top bar (project key + last synced timestamp + Sync button as POST form) + scrollable content slot
- Flash messages: `session('success')` green banner, `session('error')` red banner — placed inside main content area, above `@yield('content')`
- Alpine.js CDN: `<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>`
- Vite: `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- Settings accessed via `\Native\Desktop\Facades\Settings::get(...)`
- Nav items: Dashboard (`route('dashboard')`, grid icon), My Issues (`route('issues.index')`, list icon), Worklogs (`route('worklogs.index')`, clock icon)
- New Worklog link: `route('worklogs.create')`
- Settings link: `route('settings')`
- Sync POST form target: `route('sync')`
- SVG icons inline (simple, no icon library dependency)
- `@stack('scripts')` at bottom for per-page JS

### 4. `resources/views/setup/index.blade.php`
- Standalone page (does NOT extend `layouts.app`)
- Full-screen centered, dark background matching app theme (`bg-gray-950`)
- Card with app logo area + title "Jira Worklog Tracker" + subtitle
- Form: POST to `route('setup.store')`, CSRF
- Fields: Jira Domain (text, placeholder `mycompany.atlassian.net`), Email (email input), API Token (password input)
- "How to get an API token" as an anchor with Alpine.js `@click="window.open('https://id.atlassian.com/manage-profile/security/api-tokens', '_blank')"` (or plain `target="_blank"` link)
- Submit button: "Connect to Jira"
- Error display: `$errors->first('api_token')` and per-field errors
- Pre-fill from `$prefill['domain']` and `$prefill['email']` when variable is set (use `$prefill ?? []`)
- `@if(session('error'))` message display

### 5. `resources/views/setup/project.blade.php`
- Standalone page (does NOT extend `layouts.app`), same dark style
- Title: "Select a Project"
- Form: POST to `route('setup.project.store')`, CSRF
- Project list rendered as radio button cards — each shows `$project['key']` badge + `$project['name']`
- First project auto-selected
- Submit button: "Continue"
- Empty state if `$projects` is empty: show error message

## Key Decisions
- No icon library — use inline SVG paths for the 3 nav icons (grid, list, clock) and gear
- Tailwind v4 classes only — no arbitrary config; use standard palette (`gray-950`, `gray-900`, `gray-800`, `blue-500`, `blue-600`)
- `Settings::get()` calls in Blade are wrapped in `@php` block at top of layout for cleanliness, assigned to local vars
- The layout uses `@section`/`@yield` conventions; page title via `@yield('title', 'Jira Worklog Tracker')`
- `storeProject` redirects to `route('dashboard')` (not `route('sync')`) per the corrected spec

## Execution Steps

- [ ] Step 1: Create `app/Http/Middleware/EnsureJiraConnected.php`
- [ ] Step 2: Create `app/Http/Controllers/SetupController.php`
- [ ] Step 3: Create `resources/views/layouts/app.blade.php`
- [ ] Step 4: Create `resources/views/setup/index.blade.php`
- [ ] Step 5: Create `resources/views/setup/project.blade.php`
- [ ] Step 6: Run `php artisan test` to verify no regressions
