---
planStatus:
  planId: jira-worklog-tracker-desktop
  title: Jira Worklog Tracker — NativePHP Desktop App
  status: draft
  planType: feature
  priority: high
  owner: yusufmulhajat
  stakeholders: []
  tags: [nativephp, jira, desktop, worklog]
  created: "2026-05-30"
  updated: "2026-05-30T00:00:00.000Z"
  progress: 0
---

# Jira Worklog Tracker — NativePHP Desktop App

## Context

Building a full-featured NativePHP desktop application for tracking Jira worklogs. Users authenticate once with their Jira Cloud credentials (email + API token), then can log time against issues assigned to them and view their team's worklogs across a project. Data is cached locally in SQLite for fast navigation; a sync action pulls fresh data from the Jira REST API v3.

**User decisions:**
- Auth: Email + API Token (stored via NativePHP `Settings` facade)
- Target: Jira Cloud (`*.atlassian.net`)
- Data: Cached locally in SQLite, synced on demand
- Team scope: All worklogs across a selected Jira project/board

---

## Architecture Overview

```
NativeAppServiceProvider
  └── Window::open() → route('home')  [redirects to /setup if not connected]
  └── Menu with New Worklog + Sync shortcuts

Middleware: EnsureJiraConnected
  └── If Settings::get('jira_domain') is empty → redirect to /setup

JiraApiService
  └── Wraps Jira Cloud REST API v3 (Basic auth: email:api_token)
  └── Methods: validateCredentials, getCurrentUser, getProjects,
               searchIssues, getWorklogs, createWorklog

Local SQLite (database.sqlite)
  └── jira_issues   — cached issues with assignee, status, project
  └── jira_worklogs — cached worklogs with author, time, comment

NativePHP Settings facade (persistent key-value)
  └── jira_domain, jira_email, jira_api_token
  └── jira_account_id, jira_display_name
  └── selected_project_key, last_synced_at
```

---

## Implementation Phases

### Phase 1 — Foundation: JiraApiService + Credentials Setup

**New files:**
- `app/Services/JiraApiService.php`
- `app/Http/Controllers/SetupController.php`
- `app/Http/Middleware/EnsureJiraConnected.php`
- `resources/views/setup/index.blade.php`
- `resources/views/layouts/app.blade.php`

**`JiraApiService`** methods (all use `Http::withBasicAuth($email, $token)`):
```php
validateCredentials(domain, email, token): array  // GET /myself
getCurrentUser(): array
getProjects(): array                               // GET /project?maxResults=50
searchIssues(string $jql, int $maxResults): array  // POST /issue/search
getWorklogsForIssue(string $issueKey): array       // GET /issue/{key}/worklog
createWorklog(issueKey, timeSpentSeconds, started, comment): array
```

**`EnsureJiraConnected` middleware:**  
Check `Settings::get('jira_domain')` — if empty redirect to `route('setup')`.

**Setup flow (SetupController):**
1. `show()` → render setup form
2. `store()` → call `JiraApiService::validateCredentials()`, on success store domain/email/token/accountId in `Settings`, redirect to project selection
3. `selectProject()` → show project picker (projects from Jira API)
4. `storeProject()` → save `selected_project_key` in `Settings`, trigger initial sync, redirect to dashboard

**Setup view** (`setup/index.blade.php`):
- Clean centered form: Domain, Email, API Token fields
- "How to get an API token" helper link (shell::openExternal)
- Validation error display

**Layout** (`layouts/app.blade.php`):
- Left sidebar: logo, nav links (Dashboard, My Issues, Worklogs, Settings)
- Top bar: project name, last synced timestamp, Sync button
- Main content area
- Alpine.js via CDN for interactive bits

---

### Phase 2 — Database Migrations + Models

**New migrations:**
```php
// create_jira_issues_table
Schema::create('jira_issues', function (Blueprint $table) {
    $table->id();
    $table->string('issue_key')->unique();   // e.g. PROJ-123
    $table->string('summary');
    $table->string('status');
    $table->string('project_key');
    $table->string('assignee_account_id')->nullable();
    $table->string('assignee_display_name')->nullable();
    $table->string('priority')->nullable();
    $table->string('issue_type');
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();
});

// create_jira_worklogs_table
Schema::create('jira_worklogs', function (Blueprint $table) {
    $table->id();
    $table->string('jira_worklog_id')->unique();
    $table->string('issue_key');
    $table->string('author_account_id');
    $table->string('author_display_name');
    $table->integer('time_spent_seconds');
    $table->timestamp('started_at');
    $table->text('comment')->nullable();
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();
    $table->index(['author_account_id', 'started_at']);
    $table->index('issue_key');
});
```

**New models:**
- `app/Models/JiraIssue.php` — fillable, casts, `scopeAssignedToMe()`, `scopeForProject()`
- `app/Models/JiraWorklog.php` — fillable, casts, `scopeForCurrentUser()`, `scopeInDateRange()`

---

### Phase 3 — Sync Engine

**New file:** `app/Services/JiraSyncService.php`

Orchestrates the sync flow:
```php
syncProject(string $projectKey): void
  1. fetchAndCacheIssues($projectKey)    // JQL: project=KEY ORDER BY updated DESC, maxResults=200
  2. fetchAndCacheWorklogs($issues)      // For each issue, GET worklogs, upsert by jira_worklog_id
  3. Settings::set('last_synced_at', now()->toISOString())
```

Uses `upsert()` for efficient bulk insert-or-update.

**New controller:** `app/Http/Controllers/SyncController.php`
- `POST /sync` → call `JiraSyncService::syncProject()`, return JSON `{success, message, last_synced_at}`
- Frontend shows loading state via Alpine.js during sync

---

### Phase 4 — Dashboard

**New file:** `app/Http/Controllers/DashboardController.php`

Queries local SQLite for:
- Today's total hours logged (by current user)
- This week's total hours logged (by current user)
- Recent worklogs (last 5 entries by current user)
- Active issues assigned to current user (open status)
- Team's total hours this week (all authors)

**View:** `resources/views/dashboard/index.blade.php`

Layout:
```
┌─────────────────────────────────────────────┐
│  Today: 2h 30m    Week: 14h    Active: 7    │  ← Stats row
├───────────────┬─────────────────────────────┤
│  My Recent    │  Team This Week             │
│  Worklogs     │  (per person bar chart      │
│               │   using Tailwind bars)      │
├───────────────┴─────────────────────────────┤
│  My Open Issues (quick log button each row) │
└─────────────────────────────────────────────┘
```

---

### Phase 5 — Worklog Management

**New file:** `app/Http/Controllers/WorklogController.php`

**List view** (`/worklogs`):
- Default: All worklogs for the project, last 30 days, grouped by date
- Filter bar: Author (dropdown of unique authors), date range picker, issue search
- Each row: Issue key, Summary, Author, Time, Started, Comment
- "Mine only" toggle

**Create view** (`/worklogs/create`):
- Issue picker: searchable dropdown from cached `jira_issues` (assigned to me, open)
- Date: date input defaulting to today
- Time spent: text input accepting `1h 30m`, `2h`, `30m` — parsed in controller
- Comment: textarea (optional)
- On submit: call `JiraApiService::createWorklog()`, then refresh local cache for that issue

**Time parsing helper** in `JiraApiService`:
```php
static parseTimeToSeconds(string $input): int
// "1h 30m" → 5400, "2h" → 7200, "30m" → 1800
```

---

### Phase 6 — Issues View

**New file:** `app/Http/Controllers/IssueController.php`

**View** (`/issues`):
- List issues assigned to current user (from local cache, project filter applied)
- Status badge, priority, issue type icon (text label), issue key
- "Log Time" button per row → links to `/worklogs/create?issue=PROJ-123`
- Filter: status (Open/In Progress/All)

---

### Phase 7 — NativePHP Integration

**Modify:** `app/Providers/NativeAppServiceProvider.php`

```php
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
            Menu::label('Sync with Jira', 'CmdOrCtrl+R')->event('jira.sync'),
            Menu::separator(),
            Menu::route('setup', 'Reconnect Jira', null),
        )->label('Worklog'),
        Menu::view(),
        Menu::window(),
    );
}
```

Notification on worklog created:
```php
Notification::new()
    ->title('Worklog Created')
    ->message("{$timeSpent} logged to {$issueKey}")
    ->show();
```

---

## Routes (web.php — full replacement)

```php
Route::get('/', fn() => redirect()->route('dashboard'))->name('home');

// Setup (no auth middleware)
Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
Route::get('/setup/project', [SetupController::class, 'selectProject'])->name('setup.project');
Route::post('/setup/project', [SetupController::class, 'storeProject'])->name('setup.project.store');

// Protected by EnsureJiraConnected
Route::middleware(EnsureJiraConnected::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/worklogs', [WorklogController::class, 'index'])->name('worklogs.index');
    Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');
    Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
    Route::post('/sync', [SyncController::class, 'sync'])->name('sync');
    Route::get('/settings', [SetupController::class, 'settings'])->name('settings');
});
```

---

## Critical Files to Create/Modify

| File | Action |
|------|--------|
| `app/Services/JiraApiService.php` | Create |
| `app/Services/JiraSyncService.php` | Create |
| `app/Http/Controllers/SetupController.php` | Create |
| `app/Http/Controllers/DashboardController.php` | Create |
| `app/Http/Controllers/WorklogController.php` | Create |
| `app/Http/Controllers/IssueController.php` | Create |
| `app/Http/Controllers/SyncController.php` | Create |
| `app/Http/Middleware/EnsureJiraConnected.php` | Create |
| `app/Models/JiraIssue.php` | Create |
| `app/Models/JiraWorklog.php` | Create |
| `database/migrations/*_create_jira_issues_table.php` | Create |
| `database/migrations/*_create_jira_worklogs_table.php` | Create |
| `resources/views/layouts/app.blade.php` | Create |
| `resources/views/setup/index.blade.php` | Create |
| `resources/views/setup/project.blade.php` | Create |
| `resources/views/dashboard/index.blade.php` | Create |
| `resources/views/issues/index.blade.php` | Create |
| `resources/views/worklogs/index.blade.php` | Create |
| `resources/views/worklogs/create.blade.php` | Create |
| `routes/web.php` | Replace |
| `app/Providers/NativeAppServiceProvider.php` | Modify |
| `bootstrap/app.php` | Modify (register middleware alias) |

---

## Key Reuse

- NativePHP `Settings` facade (already installed) — store credentials
- `Http` facade (Laravel built-in) — Jira API calls
- `DB::upsert()` — bulk cache sync
- NativePHP `Notification` facade — worklog created feedback
- NativePHP `Shell::openExternal()` — open Atlassian token help page
- Tailwind CSS v4 (already installed) — all styling
- Alpine.js (add via CDN in layout) — dropdowns, loading states, toggles

---

## Verification

1. **Setup flow**: Run `composer run native:dev` → app opens → redirects to `/setup` → enter real Jira credentials → connects → project list appears → select project → initial sync runs → redirects to dashboard
2. **Dashboard**: Shows today's hours, week total, recent worklogs
3. **Create worklog**: Select an issue, enter `1h 30m`, submit → worklog appears in Jira and in local list
4. **Worklogs list**: Shows all worklogs for project, filter by author works
5. **Issues list**: Shows only issues assigned to me, "Log Time" button pre-fills issue
6. **Menu shortcut**: `Cmd+N` opens create worklog view, `Cmd+R` triggers sync
7. **Notification**: Creating a worklog fires a macOS/Windows notification
