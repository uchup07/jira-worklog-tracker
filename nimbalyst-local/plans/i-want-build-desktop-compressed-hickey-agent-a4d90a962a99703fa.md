# Task 6: WorklogController + Worklog Views

## Goal
Create 3 files:
1. `app/Http/Controllers/WorklogController.php`
2. `resources/views/worklogs/index.blade.php`
3. `resources/views/worklogs/create.blade.php`

Then run `php artisan test`.

---

## Pre-flight Checks

- [x] `JiraWorklog` scopes confirmed: `forProject`, `forAuthor`, `inDateRange`
- [x] `JiraIssue` scopes confirmed: `forProject`, `assignedTo`, `open`
- [x] `JiraApiService::fromSettings()`, `createWorklog()`, `parseTimeToSeconds()` all exist
- [x] `JiraSyncService::make()->syncProject()` exists
- [x] Layout: `resources/views/layouts/app.blade.php` uses `@yield('content')`
- [x] Routes file currently has no worklog routes — **routes must be added to `routes/web.php`**
- [x] Sidebar in `app.blade.php` already references `route('worklogs.index')` and `route('worklogs.create')` and `route('worklogs.store')` — routes are expected
- [x] Flash messages and `$projectKey`/`$lastSynced` are rendered by the layout (passed via view composer or middleware — need to verify)

---

## Steps

### Step 1 — Create `app/Http/Controllers/WorklogController.php`

Use the spec exactly, with the corrected notification code:
```php
$hours = round($seconds / 3600, 1);
Notification::new()
    ->title('Worklog Created')
    ->message("{$hours}h logged to {$request->issue_key}")
    ->show();
```

Key points:
- `index()`: paginate 30, filter by author / mine / date range, list unique authors for the dropdown
- `create()`: load open issues assigned to the current user for the select box
- `store()`: validate → parse time → call API → sync → notify → redirect

### Step 2 — Add routes to `routes/web.php`

The layout sidebar already references `worklogs.index`, `worklogs.create`, and (implicitly) `worklogs.store`. These named routes must exist:

```php
Route::get('/worklogs',        [WorklogController::class, 'index'])->name('worklogs.index');
Route::get('/worklogs/create', [WorklogController::class, 'create'])->name('worklogs.create');
Route::post('/worklogs',       [WorklogController::class, 'store'])->name('worklogs.store');
```

### Step 3 — Create `resources/views/worklogs/index.blade.php`

Use the spec verbatim. Key elements:
- Header row with "Worklogs" title + "+ New Worklog" button
- Filter form: author select, from/to date inputs, "Mine only" checkbox, Clear link
- Empty state card
- Table: Issue (monospace badge), Author, Date (M j, Y), Time (`gmdate('G\h i\m', ...)`), Comment (truncated)
- Pagination with `appends(request()->query())`

### Step 4 — Create `resources/views/worklogs/create.blade.php`

Use the spec verbatim. Key elements:
- Issue select populated from `$issues` (key — summary)
- Time spent text input with placeholder "e.g. 1h 30m, 2h, 45m"
- Date input defaulting to `now()->toDateString()`
- Comment textarea (optional, 3 rows)
- Error messages via `@error` directive
- Cancel link back to `worklogs.index`

### Step 5 — Run `php artisan test`

```bash
php artisan test
```

Report DONE or BLOCKED based on exit code.

---

## Notes

- The `store()` method catches `\RuntimeException` from sync failure silently (by design — sync failure should not block the user after a successful API worklog creation).
- `gmdate('G\h i\m', $seconds)` formats seconds as e.g. `1h 30m` (G = hours without leading zero, i = minutes with leading zero — this is the spec pattern; note i will produce `01` for 1 minute which is acceptable).
- The `$projectKey` and `$lastSynced` variables in the layout header are expected to be supplied by the existing view composer/middleware already in place for other pages (DashboardController does not pass them either, so there must be a view composer — this is not our concern).
