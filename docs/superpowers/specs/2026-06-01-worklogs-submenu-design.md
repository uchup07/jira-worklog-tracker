# Worklogs Submenu — Design Spec

**Date**: 2026-06-01  
**Status**: Approved  
**Branch**: `feature/worklogs-submenu`

---

## Summary

Split the Worklogs sidebar entry into two sub-pages:

1. **Monitoring Worklog** — the existing worklog list enhanced with Issue Type, Status, Sprint, and Epic columns.
2. **Missing Worklog** — detects project members who have not logged time on working days (Mon–Fri) in the last N days, with a per-user "Send Email" reminder action.

---

## Architecture

### New Branch

`feature/worklogs-submenu` is created before any implementation begins.

### Route Changes

Old `/worklogs` (GET) is renamed to `/worklogs/monitoring`. All internal references to `route('worklogs.index')` are updated to `route('worklogs.monitoring')`.

```php
// Inside EnsureJiraConnected middleware group:
Route::get('/worklogs/monitoring', [WorklogController::class, 'monitoring'])->name('worklogs.monitoring');
Route::get('/worklogs/missing',    fn () => view('worklogs.missing'))->name('worklogs.missing');
Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');   // unchanged
Route::post('/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');            // unchanged
Route::post('/setup/smtp', [SetupController::class, 'updateSmtp'])->name('setup.smtp');
```

### File Map

| Action | File |
|---|---|
| Rename | `resources/views/worklogs/index.blade.php` → `monitoring.blade.php` |
| Create | `resources/views/worklogs/missing.blade.php` |
| Create | `resources/views/components/⚡missing-worklog.blade.php` |
| Create | `app/Mail/WorklogReminderMail.php` |
| Create | `database/migrations/YYYY_add_sprint_epic_to_jira_issues.php` |
| Create | `database/migrations/YYYY_add_email_to_jira_project_users.php` |
| Modify | `app/Http/Controllers/WorklogController.php` — rename `index()` → `monitoring()` |
| Modify | `app/Http/Controllers/SetupController.php` — add `updateSmtp()` |
| Modify | `app/Services/JiraApiService.php` — add custom fields to fetch |
| Modify | `app/Services/JiraSyncService.php` — map Sprint, Epic, email |
| Modify | `app/Models/JiraIssue.php` — add sprint/epic to fillable |
| Modify | `app/Models/JiraProjectUser.php` — add email to fillable |
| Modify | `resources/views/components/⚡worklog-filter.blade.php` — new columns |
| Modify | `resources/views/layouts/app.blade.php` — sidebar submenu |
| Modify | `resources/views/setup/index.blade.php` — SMTP settings form |
| Modify | `routes/web.php` |

---

## Section 1: Database & Sync

### Migration 1 — `jira_issues`

```php
$table->string('sprint')->nullable();  // active sprint name, e.g. "Sprint 12"
$table->string('epic')->nullable();    // epic link key, e.g. "EB-5"
```

No indexes — display columns only.

### Migration 2 — `jira_project_users`

```php
$table->string('email')->nullable();   // Jira emailAddress
```

### JiraApiService

`searchIssuesPage()` fields list extended:

```php
$fields = [
    'summary', 'status', 'priority', 'issuetype', 'assignee', 'project',
    'customfield_10020',   // Sprint
    'customfield_10014',   // Epic Link
];
```

### JiraSyncService — `syncIssues()`

```php
'sprint' => $this->extractSprint($fields['customfield_10020'] ?? null),
'epic'   => $fields['customfield_10014'] ?? null,
```

New private helper:

```php
private function extractSprint(?array $sprints): ?string
{
    if (empty($sprints)) return null;
    $active = collect($sprints)->firstWhere('state', 'active');
    return ($active ?? $sprints[0])['name'] ?? null;
}
```

`upsert()` update columns include `sprint` and `epic`.

### JiraSyncService — `syncProjectUsers()`

`emailAddress` field mapped to `email` column. Upsert update columns include `email`.

---

## Section 2: Monitoring Worklog

### Route

`GET /worklogs/monitoring` → `WorklogController::monitoring()` → `view('worklogs.monitoring')`

`WorklogController::monitoring()` is a rename of the existing `index()` method with no logic changes.

### worklog-filter Component Changes

The existing `⚡worklog-filter.blade.php` Livewire component is extended:

**Query change** — switch from plain `JiraWorklog::query()` to a left-join:

```php
$query = JiraWorklog::query()
    ->leftJoin('jira_issues', 'jira_worklogs.issue_key', '=', 'jira_issues.issue_key')
    ->select([
        'jira_worklogs.*',
        'jira_issues.summary',
        'jira_issues.issue_type',
        'jira_issues.status as issue_status',
        'jira_issues.sprint',
        'jira_issues.epic',
    ])
    ->forProject($projectKey);
```

**Table columns (final):**

| Column | Source |
|---|---|
| Issue | `issue_key` badge + `summary` text |
| Issue Type | `issue_type` — styled badge |
| Status | `issue_status` — `badge-status` class |
| Sprint | `sprint` — plain text, `—` if null |
| Epic | `epic` — plain text, `—` if null |
| Author | `author_display_name` |
| Date | `started_at` |
| Time | `time_spent_seconds` formatted |
| Comment | `comment` |

---

## Section 3: Missing Worklog

### Detection Logic

For each active member of the selected project (`JiraProjectUser::forProject($projectKey)->where('active', true)`):

1. Generate all Mon–Fri dates in the last `$lookbackDays` calendar days (default 7, stored as `Settings::get('missing_worklog_days', 7)`).
2. Query `jira_worklogs` grouped by `author_account_id` and `DATE(started_at)` to get the set of days they logged time.
3. Subtract logged days from expected days → `missing_days` array.
4. Only include users with at least one missing day.

### Livewire Component `⚡missing-worklog.blade.php`

**Properties:**
- `public string $selectedProject` — defaults to `Settings::get('selected_project_key', '')`
- `public int $lookbackDays` — defaults to `Settings::get('missing_worklog_days', 7)`
- `public ?string $sendingTo = null` — tracks which user's email is being sent (for loading state)

**Methods:**
- `with(): array` — runs detection logic, returns `$missingUsers` collection
- `sendReminder(string $accountId): void` — sends `WorklogReminderMail` to that user

**Table layout:**

| Column | Content |
|---|---|
| User | `display_name` |
| Missing Days | Count as number + comma-separated date list below |
| Action | "Send Email" button — disabled/spinner while `$sendingTo === $accountId` |

**Empty state:** "All team members have logged time in the last N days."

**Error state:** If user has no `email` set, the button shows a tooltip "No email on record" and is disabled.

### `WorklogReminderMail`

```php
class WorklogReminderMail extends Mailable
{
    public function __construct(
        public string $displayName,
        public array  $missingDays,   // ['2026-05-27', '2026-05-28']
    ) {}
}
```

- **Subject**: `"Reminder: Please fill in your missing worklogs"`
- **Body**: Greets user by name, lists missing dates, asks them to log time in Jira.
- Rendered via a standard Blade mail template at `resources/views/mail/worklog-reminder.blade.php`.

---

## Section 4: SMTP Settings

### Settings Keys (NativePHP Settings facade)

| Key | Description |
|---|---|
| `smtp_host` | Mail server host |
| `smtp_port` | Port (e.g. 587) |
| `smtp_username` | SMTP auth username |
| `smtp_password` | SMTP auth password |
| `smtp_from_address` | From email address |
| `smtp_from_name` | From display name |
| `smtp_encryption` | `tls`, `ssl`, or `null` |

### SetupController — `updateSmtp()`

Validates and saves all seven keys via `Settings::set()`. Returns redirect back with success flash.

### Dynamic Config at Send Time

Mail is sent synchronously via `Mail::to($email)->send(...)` (not queued — NativePHP desktop app has no persistent queue worker).

Before sending `WorklogReminderMail`:

```php
Config::set('mail.mailers.smtp.host',       Settings::get('smtp_host'));
Config::set('mail.mailers.smtp.port',       Settings::get('smtp_port'));
Config::set('mail.mailers.smtp.username',   Settings::get('smtp_username'));
Config::set('mail.mailers.smtp.password',   Settings::get('smtp_password'));
Config::set('mail.mailers.smtp.encryption', Settings::get('smtp_encryption'));
Config::set('mail.from.address',            Settings::get('smtp_from_address'));
Config::set('mail.from.name',               Settings::get('smtp_from_name'));
```

### Settings Page UI

A new "Email / SMTP" section is appended to `resources/views/setup/index.blade.php` with seven input fields and a Save button posting to `route('setup.smtp')`.

---

## Section 5: Sidebar Navigation

`resources/views/layouts/app.blade.php` sidebar change:

```html
{{-- Replace single Worklogs item with: --}}
<x-side-bar.separator />
<x-side-bar.item text="Monitoring"  route="{{ route('worklogs.monitoring') }}" icon="table-cells" />
<x-side-bar.item text="Missing"     route="{{ route('worklogs.missing') }}"    icon="user-minus" />
<x-side-bar.separator />
<x-side-bar.item text="Log Time" ... />
```

The separator above the two items acts as a visual "Worklogs" section divider. No non-clickable label is needed — the icons make the grouping clear.

---

## Internal References to Update

`route('worklogs.index')` appears in:
- `resources/views/layouts/app.blade.php` (sidebar)
- `resources/views/dashboard/index.blade.php` ("all" link in Recent Logs card)
- `resources/views/issues/show.blade.php` (if present)
- `app/Http/Controllers/WorklogController.php` (redirect after store)

All replaced with `route('worklogs.monitoring')`.

---

## Error Handling

- Sprint/Epic custom field IDs vary by Jira instance. If `customfield_10020` / `customfield_10014` don't exist in the API response, they're silently `null` — no sync failure.
- If SMTP send fails, `WorklogReminderMail` throws a caught exception; `sendReminder()` sets a `$errorMessage` Livewire property shown inline.
- If a project user has no email, the Send Email button is disabled with a tooltip.

---

## Testing

- Unit test: `extractSprint()` helper — empty array, active sprint present, no active sprint fallback.
- Unit test: missing-day detection logic — weekends excluded, correct date arithmetic.
- Feature test: `GET /worklogs/monitoring` returns 200.
- Feature test: `GET /worklogs/missing` returns 200.
- Feature test: `POST /setup/smtp` saves settings correctly.
