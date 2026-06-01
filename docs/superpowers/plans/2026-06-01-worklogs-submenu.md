# Worklogs Submenu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the Worklogs sidebar entry into two sub-pages — Monitoring Worklog (existing list + Sprint/Epic/Type/Status columns) and Missing Worklog (detect unlogged working days per team member + send SMTP email reminders).

**Architecture:** Three coordinated streams: (1) DB/sync layer adds `sprint`, `epic`, and `email` fields via migrations and Jira API custom fields; (2) Monitoring extends the existing `worklog-filter` Livewire component with a LEFT JOIN to `jira_issues` and four new table columns; (3) Missing Worklog is a new Livewire component with Mon–Fri detection logic and per-user Send Email via NativePHP-stored SMTP settings applied dynamically at send time.

**Tech Stack:** Laravel, Livewire 4 (anonymous class Blade components), TallStackUI v3, NativePHP Settings facade, Laravel Mail (synchronous SMTP), PestPHP, SQLite

---

## File Map

| Action | File |
|---|---|
| Create branch | `feature/worklogs-submenu` |
| Create | `database/migrations/2026_06_01_000001_add_sprint_epic_to_jira_issues.php` |
| Create | `database/migrations/2026_06_01_000002_add_email_to_jira_project_users.php` |
| Modify | `app/Models/JiraIssue.php` — add sprint/epic to `$fillable` |
| Modify | `app/Models/JiraProjectUser.php` — add email to `$fillable` |
| Modify | `app/Services/JiraApiService.php` — add customfield_10020/10014 to fields list |
| Modify | `app/Services/JiraSyncService.php` — map sprint, epic, email; add `extractSprint()` |
| Modify | `routes/web.php` — rename worklogs.index → worklogs.monitoring; add missing + smtp routes |
| Modify | `app/Http/Controllers/WorklogController.php` — rename index() → monitoring() |
| Rename | `resources/views/worklogs/index.blade.php` → `monitoring.blade.php` |
| Modify | `resources/views/dashboard/index.blade.php` — update route ref |
| Modify | `resources/views/issues/show.blade.php` — update route ref |
| Modify | `resources/views/worklogs/create.blade.php` — update route ref |
| Modify | `app/Providers/NativeAppServiceProvider.php` — update menu route ref |
| Modify | `resources/views/components/⚡worklog-filter.blade.php` — JOIN + new columns |
| Modify | `app/Http/Controllers/SetupController.php` — add updateSmtp() |
| Modify | `routes/web.php` — add setup.smtp route |
| Modify | `resources/views/setup/index.blade.php` — SMTP form section |
| Create | `app/Mail/WorklogReminderMail.php` |
| Create | `resources/views/mail/worklog-reminder.blade.php` |
| Create | `resources/views/components/⚡missing-worklog.blade.php` |
| Create | `resources/views/worklogs/missing.blade.php` |
| Modify | `resources/views/layouts/app.blade.php` — sidebar sub-items |
| Create | `tests/Feature/WorklogsRoutesTest.php` |
| Create | `tests/Feature/SmtpSettingsTest.php` |
| Create | `tests/Unit/ExtractSprintTest.php` |

---

### Task 1: Create Feature Branch

- [ ] **Step 1: Create and switch to the feature branch**

```bash
git checkout -b feature/worklogs-submenu
```

Expected: `Switched to a new branch 'feature/worklogs-submenu'`

- [ ] **Step 2: Verify you are on the new branch**

```bash
git branch --show-current
```

Expected: `feature/worklogs-submenu`

---

### Task 2: Database Migrations

**Files:**
- Create: `database/migrations/2026_06_01_000001_add_sprint_epic_to_jira_issues.php`
- Create: `database/migrations/2026_06_01_000002_add_email_to_jira_project_users.php`

- [ ] **Step 1: Create the sprint/epic migration**

```bash
php artisan make:migration add_sprint_epic_to_jira_issues --table=jira_issues
```

Open the generated file and replace its `up()` and `down()` with:

```php
public function up(): void
{
    Schema::table('jira_issues', function (Blueprint $table) {
        $table->string('sprint')->nullable()->after('issue_type');
        $table->string('epic')->nullable()->after('sprint');
    });
}

public function down(): void
{
    Schema::table('jira_issues', function (Blueprint $table) {
        $table->dropColumn(['sprint', 'epic']);
    });
}
```

- [ ] **Step 2: Create the email migration**

```bash
php artisan make:migration add_email_to_jira_project_users --table=jira_project_users
```

Open the generated file and replace its `up()` and `down()` with:

```php
public function up(): void
{
    Schema::table('jira_project_users', function (Blueprint $table) {
        $table->string('email')->nullable()->after('display_name');
    });
}

public function down(): void
{
    Schema::table('jira_project_users', function (Blueprint $table) {
        $table->dropColumn('email');
    });
}
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: Both migrations listed as "Running" then "Done".

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add sprint, epic, and email columns via migrations"
```

---

### Task 3: Update Model Fillable Lists

**Files:**
- Modify: `app/Models/JiraIssue.php`
- Modify: `app/Models/JiraProjectUser.php`

- [ ] **Step 1: Add sprint and epic to JiraIssue**

In `app/Models/JiraIssue.php`, replace the `$fillable` array with:

```php
protected $fillable = [
    'issue_key',
    'summary',
    'status',
    'project_key',
    'assignee_account_id',
    'assignee_display_name',
    'priority',
    'issue_type',
    'sprint',
    'epic',
    'synced_at',
];
```

- [ ] **Step 2: Add email to JiraProjectUser**

In `app/Models/JiraProjectUser.php`, replace the `$fillable` array with:

```php
protected $fillable = [
    'project_key',
    'account_id',
    'display_name',
    'email',
    'active',
    'source',
    'synced_at',
];
```

- [ ] **Step 3: Run the test suite to confirm no regressions**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add app/Models/JiraIssue.php app/Models/JiraProjectUser.php
git commit -m "feat: add sprint, epic, email to model fillable lists"
```

---

### Task 4: Sync Layer — Sprint, Epic, Email

**Files:**
- Modify: `app/Services/JiraApiService.php`
- Modify: `app/Services/JiraSyncService.php`
- Create: `tests/Unit/ExtractSprintTest.php`

- [ ] **Step 1: Write the failing extractSprint unit test**

Create `tests/Unit/ExtractSprintTest.php`:

```php
<?php

use App\Services\JiraSyncService;
use App\Services\JiraApiService;
use Mockery;

function makeSyncService(): JiraSyncService
{
    $api = Mockery::mock(JiraApiService::class);
    return new JiraSyncService($api);
}

test('extractSprint returns null for empty input', function () {
    expect(makeSyncService()->extractSprint(null))->toBeNull();
    expect(makeSyncService()->extractSprint([]))->toBeNull();
});

test('extractSprint returns name of the active sprint', function () {
    $sprints = [
        ['name' => 'Sprint 1', 'state' => 'closed'],
        ['name' => 'Sprint 2', 'state' => 'active'],
        ['name' => 'Sprint 3', 'state' => 'future'],
    ];
    expect(makeSyncService()->extractSprint($sprints))->toBe('Sprint 2');
});

test('extractSprint falls back to first sprint when none is active', function () {
    $sprints = [
        ['name' => 'Sprint 1', 'state' => 'closed'],
        ['name' => 'Sprint 2', 'state' => 'closed'],
    ];
    expect(makeSyncService()->extractSprint($sprints))->toBe('Sprint 1');
});
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
php artisan test --filter=ExtractSprintTest
```

Expected: FAIL — method `extractSprint` not found.

- [ ] **Step 3: Update JiraApiService to fetch Sprint and Epic custom fields**

In `app/Services/JiraApiService.php`, find the `searchIssuesPage()` method. Replace:

```php
$fields = ['summary', 'status', 'priority', 'issuetype', 'assignee', 'project'];
```

With:

```php
$fields = [
    'summary', 'status', 'priority', 'issuetype', 'assignee', 'project',
    'customfield_10020',   // Sprint
    'customfield_10014',   // Epic Link
];
```

- [ ] **Step 4: Update JiraSyncService::syncIssues() to map sprint, epic, and add extractSprint()**

In `app/Services/JiraSyncService.php`:

**4a.** In `syncIssues()`, find the `$rows = array_map(function ($issue) {` block. Add `sprint` and `epic` to the returned array:

```php
$rows = array_map(function ($issue) {
    $fields = $issue['fields'];

    return [
        'issue_key'               => $issue['key'],
        'summary'                 => $fields['summary'] ?? '',
        'status'                  => $fields['status']['name'] ?? 'Unknown',
        'project_key'             => $fields['project']['key'] ?? '',
        'assignee_account_id'     => $fields['assignee']['accountId'] ?? null,
        'assignee_display_name'   => $fields['assignee']['displayName'] ?? null,
        'priority'                => $fields['priority']['name'] ?? null,
        'issue_type'              => $fields['issuetype']['name'] ?? 'Task',
        'sprint'                  => $this->extractSprint($fields['customfield_10020'] ?? null),
        'epic'                    => $fields['customfield_10014'] ?? null,
        'synced_at'               => now()->toDateTimeString(),
        'created_at'              => now()->toDateTimeString(),
        'updated_at'              => now()->toDateTimeString(),
    ];
}, $issues);
```

**4b.** In the same method, update the `JiraIssue::upsert()` call to include `sprint` and `epic` in the update columns:

```php
JiraIssue::upsert($rows, ['issue_key'], [
    'summary', 'status', 'project_key', 'assignee_account_id',
    'assignee_display_name', 'priority', 'issue_type', 'sprint', 'epic',
    'synced_at', 'updated_at',
]);
```

**4c.** Add the public `extractSprint()` helper method to `JiraSyncService` (at the bottom of the class, before the closing `}`):

```php
public function extractSprint(?array $sprints): ?string
{
    if (empty($sprints)) {
        return null;
    }
    $active = collect($sprints)->firstWhere('state', 'active');

    return ($active ?? $sprints[0])['name'] ?? null;
}
```

- [ ] **Step 5: Update JiraSyncService::syncProjectUsers() to map email**

In `syncProjectUsers()`, find the `->map(fn (array $user) => [` block for `$assignableUsers`. Update it to include email:

```php
$users = collect($assignableUsers)
    ->map(fn (array $user) => [
        'project_key'  => $projectKey,
        'account_id'   => $user['accountId'] ?? null,
        'display_name' => $user['displayName'] ?? null,
        'email'        => $user['emailAddress'] ?? null,
        'active'       => $user['active'] ?? true,
        'source'       => 'assignable',
    ])
    ->filter(fn (array $user) => filled($user['account_id']) && filled($user['display_name']));
```

Also update the `JiraProjectUser::upsert()` call update columns to include `email`:

```php
JiraProjectUser::upsert($rows, ['project_key', 'account_id'], [
    'display_name', 'active', 'source', 'email', 'synced_at', 'updated_at',
]);
```

Note: `$issueAssignees` and `$worklogAuthors` rows don't have email — the `upsert` will leave the `email` column untouched for those sources since they're not in the update list. The `email` column will only be set from the `assignable` source rows.

- [ ] **Step 6: Run the extractSprint tests**

```bash
php artisan test --filter=ExtractSprintTest
```

Expected: 3 PASS.

- [ ] **Step 7: Run the full suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Services/JiraApiService.php app/Services/JiraSyncService.php tests/Unit/ExtractSprintTest.php
git commit -m "feat: sync Sprint, Epic, and email from Jira API"
```

---

### Task 5: Route Rename and Internal Reference Updates

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/WorklogController.php`
- Rename: `resources/views/worklogs/index.blade.php` → `monitoring.blade.php`
- Modify: `resources/views/dashboard/index.blade.php`
- Modify: `resources/views/issues/show.blade.php`
- Modify: `resources/views/worklogs/create.blade.php`
- Modify: `app/Providers/NativeAppServiceProvider.php`

- [ ] **Step 1: Update routes/web.php**

Replace the entire `EnsureJiraConnected` middleware group with:

```php
Route::middleware(EnsureJiraConnected::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Volt::route('/team-overview', 'team-overview')->name('team-overview');

    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');

    Route::get('/worklogs/monitoring', [WorklogController::class, 'monitoring'])->name('worklogs.monitoring');
    Route::get('/worklogs/missing', fn () => view('worklogs.missing'))->name('worklogs.missing');
    Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');
    Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');

    Route::post('/sync', [SyncController::class, 'sync'])->name('sync');

    Route::get('/settings', [SetupController::class, 'settings'])->name('settings');
    Route::post('/setup/smtp', [SetupController::class, 'updateSmtp'])->name('setup.smtp');
});
```

Note: The `Volt::route` line requires `use Livewire\Volt\Volt;` which does NOT exist in this project (livewire/volt is not installed). Remove that line — it was already removed in the previous session. The team-overview route is `Route::get('/team-overview', fn () => view('team-overview.index'))->name('team-overview');`

The actual correct group is:

```php
Route::middleware(EnsureJiraConnected::class)->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/team-overview', fn () => view('team-overview.index'))->name('team-overview');

    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');

    Route::get('/worklogs/monitoring', [WorklogController::class, 'monitoring'])->name('worklogs.monitoring');
    Route::get('/worklogs/missing', fn () => view('worklogs.missing'))->name('worklogs.missing');
    Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');
    Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');

    Route::post('/sync', [SyncController::class, 'sync'])->name('sync');

    Route::get('/settings', [SetupController::class, 'settings'])->name('settings');
    Route::post('/setup/smtp', [SetupController::class, 'updateSmtp'])->name('setup.smtp');
});
```

Read `routes/web.php` first to see its exact current state before editing.

- [ ] **Step 2: Rename WorklogController::index() to monitoring()**

In `app/Http/Controllers/WorklogController.php`:

Replace the entire `index()` method with a minimal `monitoring()` method (the old index() has dead query logic since the view uses a Livewire component):

```php
public function monitoring(): \Illuminate\View\View
{
    return view('worklogs.monitoring');
}
```

Also update the `store()` method — change the redirect at the bottom:

```php
// Change:
$redirectRoute = ! empty($validated['return_to_issue']) ? 'issues.show' : 'worklogs.index';
// To:
$redirectRoute = ! empty($validated['return_to_issue']) ? 'issues.show' : 'worklogs.monitoring';
```

- [ ] **Step 3: Rename the view file**

```bash
mv /path/to/project/resources/views/worklogs/index.blade.php \
   /path/to/project/resources/views/worklogs/monitoring.blade.php
```

Or use the full absolute path:
```bash
mv resources/views/worklogs/index.blade.php resources/views/worklogs/monitoring.blade.php
```

Run from the project root: `/Users/yusufmulhajat/Public/www/laravel-nativephp`

- [ ] **Step 4: Update dashboard/index.blade.php**

In `resources/views/dashboard/index.blade.php`, replace:

```php
href="{{ route('worklogs.index') }}"
```

With:

```php
href="{{ route('worklogs.monitoring') }}"
```

- [ ] **Step 5: Update issues/show.blade.php**

In `resources/views/issues/show.blade.php`, replace:

```php
href="{{ route('worklogs.index') }}"
```

With:

```php
href="{{ route('worklogs.monitoring') }}"
```

- [ ] **Step 6: Update worklogs/create.blade.php**

In `resources/views/worklogs/create.blade.php`, replace:

```php
href="{{ route('worklogs.index') }}"
```

With:

```php
href="{{ route('worklogs.monitoring') }}"
```

- [ ] **Step 7: Update NativeAppServiceProvider.php**

In `app/Providers/NativeAppServiceProvider.php`, replace:

```php
Menu::route('worklogs.index', 'View Worklogs', null),
```

With:

```php
Menu::route('worklogs.monitoring', 'View Worklogs', null),
```

- [ ] **Step 8: Run the test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add routes/web.php app/Http/Controllers/WorklogController.php \
        resources/views/worklogs/monitoring.blade.php \
        resources/views/dashboard/index.blade.php \
        resources/views/issues/show.blade.php \
        resources/views/worklogs/create.blade.php \
        app/Providers/NativeAppServiceProvider.php
git commit -m "feat: rename worklogs.index to worklogs.monitoring and add missing/smtp routes"
```

---

### Task 6: Extend worklog-filter with JOIN and New Columns

**Files:**
- Modify: `resources/views/components/⚡worklog-filter.blade.php`

- [ ] **Step 1: Read the current component**

Read `resources/views/components/⚡worklog-filter.blade.php` in full to understand its current structure before editing.

- [ ] **Step 2: Update the query in with() to LEFT JOIN jira_issues**

In the `with()` method, replace:

```php
$query = JiraWorklog::query()->forProject($projectKey);
```

With:

```php
$query = JiraWorklog::query()
    ->leftJoin('jira_issues', 'jira_worklogs.issue_key', '=', 'jira_issues.issue_key')
    ->select([
        'jira_worklogs.id',
        'jira_worklogs.jira_worklog_id',
        'jira_worklogs.issue_key',
        'jira_worklogs.author_account_id',
        'jira_worklogs.author_display_name',
        'jira_worklogs.time_spent_seconds',
        'jira_worklogs.started_at',
        'jira_worklogs.comment',
        'jira_issues.summary',
        'jira_issues.issue_type',
        'jira_issues.status as issue_status',
        'jira_issues.sprint',
        'jira_issues.epic',
    ])
    ->forProject($projectKey);
```

Also qualify the date filter columns and the orderBy to avoid ambiguity:

```php
if ($this->from) {
    $query->where('jira_worklogs.started_at', '>=', $this->from);
}
if ($this->to) {
    $query->where('jira_worklogs.started_at', '<=', $this->to.' 23:59:59');
}

return [
    'worklogs' => $query->orderByDesc('jira_worklogs.started_at')->paginate(30),
    ...
];
```

- [ ] **Step 3: Update the table template**

In the Blade template section, replace the `<table>` block with:

```html
<table class="data-table">
    <thead>
        <tr>
            <th>Issue</th>
            <th>Type</th>
            <th>Status</th>
            <th>Sprint</th>
            <th>Epic</th>
            <th>Author</th>
            <th>Date</th>
            <th>Time</th>
            <th>Comment</th>
        </tr>
    </thead>
    <tbody>
        @foreach($worklogs as $wl)
            @php
                $h  = floor($wl->time_spent_seconds / 3600);
                $m  = floor(($wl->time_spent_seconds % 3600) / 60);
                $t  = $h > 0 ? "{$h}h" . ($m > 0 ? " {$m}m" : '') : "{$m}m";
                $me = $wl->author_account_id === $accountId;
            @endphp
            <tr>
                <td>
                    <span class="badge-key">{{ $wl->issue_key }}</span>
                    @if($wl->summary)
                        <div style="font-size:11.5px; color:var(--text-muted); margin-top:2px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->summary }}</div>
                    @endif
                </td>
                <td>
                    <span style="font-size:11.5px; color:var(--text-muted);">{{ $wl->issue_type ?: '—' }}</span>
                </td>
                <td>
                    @if($wl->issue_status)
                        <span class="badge-status">{{ $wl->issue_status }}</span>
                    @else
                        <span style="font-size:11.5px; color:var(--text-muted);">—</span>
                    @endif
                </td>
                <td style="font-size:11.5px; color:var(--text-muted); max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->sprint ?: '—' }}</td>
                <td style="font-size:11.5px; color:var(--text-muted);">{{ $wl->epic ?: '—' }}</td>
                <td style="font-size:13px; color:{{ $me ? 'var(--text)' : 'var(--text-muted)' }}; font-weight:{{ $me ? '500' : '400' }};">
                    {{ $wl->author_display_name }}
                    @if($me)<x-badge text="you" color="yellow" style="margin-left:4px;" />@endif
                </td>
                <td>
                    <span class="mono" style="font-size:12px; color:var(--text-muted);">{{ $wl->started_at?->format('M j, Y') }}</span>
                </td>
                <td>
                    <span style="font-size:14px; font-weight:700; letter-spacing:-0.03em; color:var(--text);">{{ $t }}</span>
                </td>
                <td style="font-size:12.5px; color:var(--text-muted); max-width:200px;">
                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $wl->comment ?: '—' }}</div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
```

- [ ] **Step 4: Run the test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add "resources/views/components/⚡worklog-filter.blade.php"
git commit -m "feat: extend worklog-filter with issue Type, Status, Sprint, Epic columns"
```

---

### Task 7: SMTP Settings — Controller and Form

**Files:**
- Modify: `app/Http/Controllers/SetupController.php`
- Modify: `resources/views/setup/index.blade.php`

- [ ] **Step 1: Write the failing SMTP settings test**

Create `tests/Feature/SmtpSettingsTest.php`:

```php
<?php

use Native\Desktop\Facades\Settings;

test('smtp settings can be saved', function () {
    Settings::shouldReceive('set')->times(7);

    $response = $this->post(route('setup.smtp'), [
        'smtp_host'         => 'smtp.gmail.com',
        'smtp_port'         => 587,
        'smtp_username'     => 'user@example.com',
        'smtp_password'     => 'secret',
        'smtp_from_address' => 'user@example.com',
        'smtp_from_name'    => 'Worklog Tracker',
        'smtp_encryption'   => 'tls',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'SMTP settings saved.');
});

test('smtp settings requires host and from_address', function () {
    $response = $this->post(route('setup.smtp'), [
        'smtp_host'         => '',
        'smtp_from_address' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['smtp_host', 'smtp_from_address']);
});
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
php artisan test --filter=SmtpSettingsTest
```

Expected: FAIL — `updateSmtp` method not found.

- [ ] **Step 3: Add updateSmtp() to SetupController**

In `app/Http/Controllers/SetupController.php`, add this method (and add `use Illuminate\Http\RedirectResponse;` to imports if not present):

```php
public function updateSmtp(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'smtp_host'         => 'required|string',
        'smtp_port'         => 'required|integer|min:1|max:65535',
        'smtp_username'     => 'nullable|string',
        'smtp_password'     => 'nullable|string',
        'smtp_from_address' => 'required|email',
        'smtp_from_name'    => 'required|string',
        'smtp_encryption'   => 'nullable|in:tls,ssl',
    ]);

    foreach ($validated as $key => $value) {
        Settings::set($key, $value);
    }

    return redirect()->back()->with('success', 'SMTP settings saved.');
}
```

- [ ] **Step 4: Run the SMTP test to confirm it passes**

```bash
php artisan test --filter=SmtpSettingsTest
```

Expected: 2 PASS.

- [ ] **Step 5: Add SMTP settings form to the settings page**

In `resources/views/setup/index.blade.php`, append this block before the closing `</body>` tag (or after the last `</x-card>`):

```html
    {{-- SMTP / Email Settings --}}
    <div style="margin-top:24px;">
        <h2 style="font-size:14px; font-weight:700; color:var(--text); margin-bottom:12px; letter-spacing:-0.02em;">Email / SMTP</h2>
        <x-card style="padding:22px;">
            @if(session('success') && request()->routeIs('settings'))
                <x-alert text="{{ session('success') }}" color="green" class="mb-4" />
            @endif
            <form method="POST" action="{{ route('setup.smtp') }}">
                @csrf
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <x-input label="SMTP Host"
                             name="smtp_host"
                             placeholder="smtp.gmail.com"
                             :value="old('smtp_host', \Native\Desktop\Facades\Settings::get('smtp_host', ''))"
                             :error="$errors->first('smtp_host')" />
                    <x-input label="SMTP Port"
                             name="smtp_port"
                             placeholder="587"
                             :value="old('smtp_port', \Native\Desktop\Facades\Settings::get('smtp_port', '587'))"
                             :error="$errors->first('smtp_port')" />
                    <x-input label="SMTP Username"
                             name="smtp_username"
                             placeholder="you@example.com"
                             :value="old('smtp_username', \Native\Desktop\Facades\Settings::get('smtp_username', ''))"
                             :error="$errors->first('smtp_username')" />
                    <x-input label="SMTP Password"
                             name="smtp_password"
                             type="password"
                             placeholder="App password or SMTP password"
                             :value="old('smtp_password', \Native\Desktop\Facades\Settings::get('smtp_password', ''))"
                             :error="$errors->first('smtp_password')" />
                    <x-input label="From Address"
                             name="smtp_from_address"
                             type="email"
                             placeholder="you@example.com"
                             :value="old('smtp_from_address', \Native\Desktop\Facades\Settings::get('smtp_from_address', ''))"
                             :error="$errors->first('smtp_from_address')" />
                    <x-input label="From Name"
                             name="smtp_from_name"
                             placeholder="Worklog Tracker"
                             :value="old('smtp_from_name', \Native\Desktop\Facades\Settings::get('smtp_from_name', 'Worklog Tracker'))"
                             :error="$errors->first('smtp_from_name')" />
                    <div>
                        <label style="font-size:12px; font-weight:500; color:var(--text-muted); display:block; margin-bottom:6px;">Encryption</label>
                        <select name="smtp_encryption"
                                style="background:var(--surface-2); border:1px solid var(--border); border-radius:var(--radius); padding:7px 10px; font-size:13px; color:var(--text); width:100%; outline:none;">
                            <option value="" {{ \Native\Desktop\Facades\Settings::get('smtp_encryption') === null ? 'selected' : '' }}>None</option>
                            <option value="tls" {{ \Native\Desktop\Facades\Settings::get('smtp_encryption') === 'tls' ? 'selected' : '' }}>TLS</option>
                            <option value="ssl" {{ \Native\Desktop\Facades\Settings::get('smtp_encryption') === 'ssl' ? 'selected' : '' }}>SSL</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <x-button type="submit" color="primary" full>Save SMTP Settings</x-button>
                </div>
            </form>
        </x-card>
    </div>
```

- [ ] **Step 6: Run the full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SetupController.php \
        resources/views/setup/index.blade.php \
        tests/Feature/SmtpSettingsTest.php
git commit -m "feat: add SMTP settings form and updateSmtp controller method"
```

---

### Task 8: WorklogReminderMail and Email Template

**Files:**
- Create: `app/Mail/WorklogReminderMail.php`
- Create: `resources/views/mail/worklog-reminder.blade.php`

- [ ] **Step 1: Create the Mailable class**

Create `app/Mail/WorklogReminderMail.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorklogReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $displayName,
        public array  $missingDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: Please fill in your missing worklogs',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.worklog-reminder',
        );
    }
}
```

- [ ] **Step 2: Create the email Blade template**

Create `resources/views/mail/worklog-reminder.blade.php`:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #333; line-height: 1.6; }
        .container { max-width: 520px; margin: 32px auto; padding: 0 16px; }
        h2 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        ul { margin: 12px 0 20px; padding-left: 20px; }
        li { margin-bottom: 4px; }
        .footer { margin-top: 32px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hi {{ $displayName }},</h2>
        <p>This is a reminder that you have not logged any time in Jira on the following working days:</p>
        <ul>
            @foreach($missingDays as $day)
                <li>{{ \Carbon\Carbon::parse($day)->format('l, d M Y') }}</li>
            @endforeach
        </ul>
        <p>Please log your time in Jira at your earliest convenience.</p>
        <div class="footer">
            Sent by Worklog Tracker
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 3: Create the mail directory if it doesn't exist**

```bash
mkdir -p resources/views/mail
```

- [ ] **Step 4: Run the test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Mail/WorklogReminderMail.php resources/views/mail/worklog-reminder.blade.php
git commit -m "feat: add WorklogReminderMail and email template"
```

---

### Task 9: Missing Worklog Livewire Component

**Files:**
- Create: `resources/views/components/⚡missing-worklog.blade.php`

- [ ] **Step 1: Create the component**

Create `resources/views/components/⚡missing-worklog.blade.php`:

```php
<?php

use App\Mail\WorklogReminderMail;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Native\Desktop\Facades\Settings;

new class extends Component
{
    public string $selectedProject = '';
    public int    $lookbackDays    = 7;
    public ?string $sendingTo      = null;
    public ?string $successMessage = null;
    public ?string $errorMessage   = null;

    public function mount(): void
    {
        $this->selectedProject = Settings::get('selected_project_key', '');
        $this->lookbackDays    = (int) Settings::get('missing_worklog_days', 7);
    }

    public function sendReminder(string $accountId): void
    {
        $user = JiraProjectUser::where('account_id', $accountId)->first();

        if (! $user || ! $user->email) {
            $this->errorMessage   = 'No email address on record for this user.';
            $this->successMessage = null;
            return;
        }

        $missing = collect($this->computeMissingUsers())
            ->firstWhere('account_id', $accountId);

        if (! $missing || empty($missing['missing_days'])) {
            return;
        }

        $this->sendingTo = $accountId;

        Config::set('mail.mailers.smtp.host',       Settings::get('smtp_host'));
        Config::set('mail.mailers.smtp.port',       Settings::get('smtp_port'));
        Config::set('mail.mailers.smtp.username',   Settings::get('smtp_username'));
        Config::set('mail.mailers.smtp.password',   Settings::get('smtp_password'));
        Config::set('mail.mailers.smtp.encryption', Settings::get('smtp_encryption'));
        Config::set('mail.from.address',            Settings::get('smtp_from_address'));
        Config::set('mail.from.name',               Settings::get('smtp_from_name'));

        try {
            Mail::to($user->email)->send(
                new WorklogReminderMail($user->display_name, $missing['missing_days'])
            );
            $this->successMessage = "Reminder sent to {$user->display_name}.";
            $this->errorMessage   = null;
        } catch (\Throwable $e) {
            $this->errorMessage   = 'Failed to send email: ' . $e->getMessage();
            $this->successMessage = null;
        } finally {
            $this->sendingTo = null;
        }
    }

    public function with(): array
    {
        return ['missingUsers' => $this->computeMissingUsers()];
    }

    public function computeMissingUsers(): array
    {
        $projectKey = $this->selectedProject;

        if (! $projectKey) {
            return [];
        }

        $workingDays = [];
        for ($i = 1; $i <= $this->lookbackDays; $i++) {
            $date = Carbon::today()->subDays($i);
            if ($date->isWeekday()) {
                $workingDays[] = $date->toDateString();
            }
        }

        if (empty($workingDays)) {
            return [];
        }

        $users = JiraProjectUser::forProject($projectKey)
            ->where('active', true)
            ->get();

        $loggedDays = JiraWorklog::forProject($projectKey)
            ->selectRaw('author_account_id, DATE(jira_worklogs.started_at) as log_date')
            ->whereIn(\Illuminate\Support\Facades\DB::raw('DATE(jira_worklogs.started_at)'), $workingDays)
            ->groupBy('author_account_id', \Illuminate\Support\Facades\DB::raw('DATE(jira_worklogs.started_at)'))
            ->get()
            ->groupBy('author_account_id')
            ->map(fn ($logs) => $logs->pluck('log_date')->toArray());

        return $users->map(function ($user) use ($workingDays, $loggedDays) {
            $logged  = $loggedDays->get($user->account_id, []);
            $missing = array_values(array_diff($workingDays, $logged));
            sort($missing);

            return [
                'account_id'   => $user->account_id,
                'display_name' => $user->display_name,
                'email'        => $user->email,
                'missing_days' => $missing,
                'count'        => count($missing),
            ];
        })
        ->filter(fn ($u) => $u['count'] > 0)
        ->sortByDesc('count')
        ->values()
        ->toArray();
    }
};
?>

<div style="display:flex; flex-direction:column; gap:14px;">

    @if($successMessage)
        <div x-data x-init="setTimeout(() => $el.remove(), 4000)"
             style="padding:9px 13px; background:oklch(0.75 0.17 142 / 0.08); border:1px solid oklch(0.75 0.17 142 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--green); display:flex; align-items:center; gap:8px;">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div style="padding:9px 13px; background:oklch(0.65 0.22 25 / 0.08); border:1px solid oklch(0.65 0.22 25 / 0.2); border-radius:var(--radius); font-size:12.5px; color:var(--red);">
            {{ $errorMessage }}
        </div>
    @endif

    @if(empty($missingUsers))
        <div class="card" style="padding:40px; text-align:center;">
            <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.2"
                 style="color:var(--text-subtle); margin:0 auto 10px; display:block;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="font-size:13px; color:var(--text-muted);">All team members have logged time in the last {{ $lookbackDays }} days.</p>
        </div>
    @else
        <div class="card" style="overflow:hidden;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Missing Days</th>
                        <th style="width:120px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($missingUsers as $u)
                        <tr>
                            <td style="font-size:13px; color:var(--text); font-weight:500;">{{ $u['display_name'] }}</td>
                            <td>
                                <span style="font-size:14px; font-weight:700; color:var(--red);">{{ $u['count'] }}</span>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">
                                    {{ implode(', ', array_map(fn($d) => \Carbon\Carbon::parse($d)->format('M j'), $u['missing_days'])) }}
                                </div>
                            </td>
                            <td>
                                @if($u['email'])
                                    <button type="button"
                                            wire:click="sendReminder('{{ $u['account_id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="sendReminder('{{ $u['account_id'] }}')"
                                            style="font-size:12px; padding:5px 12px; border-radius:5px; cursor:pointer;
                                                   border:1px solid var(--border); background:transparent; color:var(--text-muted);
                                                   transition:all 120ms;"
                                            onmouseover="this.style.borderColor='var(--accent)'; this.style.color='var(--accent)'"
                                            onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-muted)'">
                                        <span wire:loading.remove wire:target="sendReminder('{{ $u['account_id'] }}')">Send Email</span>
                                        <span wire:loading wire:target="sendReminder('{{ $u['account_id'] }}')">Sending…</span>
                                    </button>
                                @else
                                    <span style="font-size:11.5px; color:var(--text-subtle); font-style:italic;"
                                          title="No email on record">No email</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
```

- [ ] **Step 2: Run the test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add "resources/views/components/⚡missing-worklog.blade.php"
git commit -m "feat: add Missing Worklog Livewire component with detection logic and Send Email action"
```

---

### Task 10: Missing Worklog Wrapper View and Sidebar Navigation

**Files:**
- Create: `resources/views/worklogs/missing.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Create the missing worklog wrapper view**

Create `resources/views/worklogs/missing.blade.php`:

```html
<x-app-layout>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1 style="font-size:18px; font-weight:700; color:var(--text); letter-spacing:-0.025em; line-height:1;">Missing Worklogs</h1>
            <p style="font-size:12px; color:var(--text-muted); margin-top:3px;">Team members with no logged time on working days</p>
        </div>
    </div>

    <livewire:missing-worklog />
</x-app-layout>
```

- [ ] **Step 2: Update the sidebar in app.blade.php**

In `resources/views/layouts/app.blade.php`, replace:

```html
<x-side-bar.item text="Worklogs"   route="{{ route('worklogs.index') }}"  icon="clock" />
```

With:

```html
<x-side-bar.item text="Monitoring" route="{{ route('worklogs.monitoring') }}" icon="table-cells" />
<x-side-bar.item text="Missing"    route="{{ route('worklogs.missing') }}"    icon="user-minus" />
```

- [ ] **Step 3: Run the test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/worklogs/missing.blade.php resources/views/layouts/app.blade.php
git commit -m "feat: add missing worklog wrapper view and update sidebar to show sub-items"
```

---

### Task 11: Feature Tests

**Files:**
- Create: `tests/Feature/WorklogsRoutesTest.php`

- [ ] **Step 1: Create the feature tests**

Create `tests/Feature/WorklogsRoutesTest.php`:

```php
<?php

use App\Models\JiraIssue;
use App\Models\JiraProjectUser;
use App\Models\JiraWorklog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\Settings;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::shouldReceive('get')
        ->andReturnUsing(function (string $key, mixed $default = null) {
            return match ($key) {
                'jira_domain'             => 'example.atlassian.net',
                'jira_email'              => 'user@example.com',
                'jira_api_token'          => 'token',
                'selected_project_key'    => 'EB',
                'jira_account_id'         => 'user-1',
                'missing_worklog_days'    => 7,
                default                   => $default,
            };
        });
});

test('monitoring worklog page loads', function () {
    $this->get(route('worklogs.monitoring'))->assertOk();
});

test('missing worklog page loads', function () {
    $this->get(route('worklogs.missing'))->assertOk();
});

test('missing worklog component shows users with no worklogs on working days', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-no-logs',
        'display_name' => 'Alice',
        'email'        => 'alice@example.com',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    Livewire::test('missing-worklog')
        ->assertSee('Alice');
});

test('missing worklog component shows empty state when all users have logged', function () {
    $user = JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-logged',
        'display_name' => 'Bob',
        'email'        => 'bob@example.com',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    JiraIssue::create([
        'issue_key'   => 'EB-1',
        'summary'     => 'Test',
        'status'      => 'In Progress',
        'project_key' => 'EB',
        'issue_type'  => 'Task',
        'synced_at'   => now(),
    ]);

    // Create worklogs for all working days in the last 7 days
    for ($i = 1; $i <= 7; $i++) {
        $date = \Carbon\Carbon::today()->subDays($i);
        if ($date->isWeekday()) {
            JiraWorklog::create([
                'jira_worklog_id'    => "wl-{$i}",
                'issue_key'          => 'EB-1',
                'author_account_id'  => 'user-logged',
                'author_display_name'=> 'Bob',
                'time_spent_seconds' => 3600,
                'started_at'         => $date,
                'synced_at'          => now(),
            ]);
        }
    }

    Livewire::test('missing-worklog')
        ->assertSee('All team members have logged time');
});

test('missing day detection excludes weekends', function () {
    JiraProjectUser::create([
        'project_key'  => 'EB',
        'account_id'   => 'user-weekday',
        'display_name' => 'Charlie',
        'email'        => 'charlie@example.com',
        'active'       => true,
        'source'       => 'assignable',
        'synced_at'    => now(),
    ]);

    $component = Livewire::test('missing-worklog');
    $missingUsers = $component->instance()->computeMissingUsers();

    if (!empty($missingUsers)) {
        $missingDays = $missingUsers[0]['missing_days'];
        foreach ($missingDays as $day) {
            $dayOfWeek = \Carbon\Carbon::parse($day)->dayOfWeek;
            expect($dayOfWeek)->not->toBe(0)  // Sunday
                              ->not->toBe(6); // Saturday
        }
    }

    expect(true)->toBeTrue(); // test passes if no exception thrown
});
```

- [ ] **Step 2: Run the new feature tests**

```bash
php artisan test --filter=WorklogsRoutesTest
```

Expected: 4 PASS.

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test
```

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/WorklogsRoutesTest.php
git commit -m "test: add feature tests for monitoring, missing worklog routes and detection logic"
```

---

### Task 12: Self-Check — Run Full Suite on Feature Branch

- [ ] **Step 1: Run the complete test suite**

```bash
php artisan test
```

Expected: All tests pass with no failures or errors.

- [ ] **Step 2: Verify all route references are updated**

```bash
grep -r "worklogs\.index" resources/ app/ routes/ --include="*.php" --include="*.blade.php"
```

Expected: Zero matches. If any matches appear, update them to `worklogs.monitoring`.

- [ ] **Step 3: Confirm the feature branch is ready**

```bash
git log --oneline feature/worklogs-submenu
```

Expected: Shows all commits from this plan.
