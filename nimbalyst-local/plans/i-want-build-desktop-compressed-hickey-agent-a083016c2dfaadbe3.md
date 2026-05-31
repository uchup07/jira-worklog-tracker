# Task 2: Create JiraApiService

## Goal
Create `app/Services/JiraApiService.php` — a wrapper around the Jira Cloud REST API v3.

## Context

- Project: Laravel 13 + NativePHP desktop app
- `app/Services/` directory does not yet exist — must be created implicitly by placing the file there
- `Native\Desktop\Facades\Settings` facade supports `get(string $key, $default = null)`
- Laravel `Http` facade is available via `Illuminate\Support\Facades\Http`
- No tests should be created (Jira API requires real credentials)
- After creating the file, run `php artisan test` to verify nothing is broken

---

## Steps

### Step 1 — Create `app/Services/JiraApiService.php`

File: `/Users/yusufmulhajat/Public/www/laravel-nativephp/app/Services/JiraApiService.php`

The class must:

1. **Namespace / imports**
   - `namespace App\Services;`
   - `use Illuminate\Support\Facades\Http;`
   - `use Native\Desktop\Facades\Settings;`
   - `use Carbon\Carbon;`

2. **Constructor**
   ```php
   public function __construct(
       private string $domain,
       private string $email,
       private string $apiToken,
   ) {}
   ```

3. **`static fromSettings(): static`**
   - Read `jira_domain`, `jira_email`, `jira_api_token` from `Settings::get()`
   - If any are empty/null, throw `RuntimeException('Jira not configured')`
   - Return `new static($domain, $email, $token)`

4. **Private helper `baseRequest()`**
   Returns a pre-configured `Http` pending request with:
   - `withBasicAuth($this->email, $this->apiToken)`
   - `withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])`
   - `timeout(15)`
   - Base URL: `https://{$this->domain}/rest/api/3`

5. **Private helper `handleResponse()`**
   Accepts a `Response` object. If status >= 400, throws `RuntimeException("Jira API error {$status}: {$body}")`. Returns decoded JSON body as array.

6. **`static validateCredentials(string $domain, string $email, string $token): array`**
   - Build a one-off Http request (same config as baseRequest but with provided credentials)
   - `GET https://{$domain}/rest/api/3/myself`
   - On 200: return `['success' => true, 'user' => $response->json()]`
   - On any failure (including exceptions): return `['success' => false, 'error' => $message]`
   - Wrap in try/catch to catch connection errors

7. **`getCurrentUser(): array`**
   - `GET /myself`
   - Return decoded JSON body via `handleResponse()`

8. **`getProjects(int $maxResults = 50): array`**
   - `GET /project?maxResults={maxResults}&orderBy=name`
   - Map response to array of `['key' => ..., 'name' => ..., 'id' => ...]`

9. **`searchIssues(string $jql, int $maxResults = 200, int $startAt = 0): array`**
   - `POST /issue/search` with JSON body:
     ```json
     {
       "jql": $jql,
       "maxResults": $maxResults,
       "startAt": $startAt,
       "fields": ["summary", "status", "priority", "issuetype", "assignee", "project"]
     }
     ```
   - Return `$response['issues']` array

10. **`getWorklogsForIssue(string $issueKey): array`**
    - `GET /issue/{issueKey}/worklog`
    - Return `$response['worklogs']` array

11. **`createWorklog(string $issueKey, int $timeSpentSeconds, Carbon $started, string $comment = ''): array`**
    - `POST /issue/{issueKey}/worklog` with body:
      ```json
      {
        "timeSpentSeconds": $timeSpentSeconds,
        "started": $started->format('Y-m-d\TH:i:s.000+0000'),
        "comment": {
          "type": "doc",
          "version": 1,
          "content": [{"type": "paragraph", "content": [{"type": "text", "text": $comment}]}]
        }
      }
      ```
    - Return decoded response body

12. **`static parseTimeToSeconds(string $input): int`**
    Parse human time strings:
    - Try pattern `(\d+)h\s*(\d+)m` → hours * 3600 + minutes * 60
    - Try pattern `(\d+)h` → hours * 3600
    - Try pattern `(\d+)m` → minutes * 60
    - Try `ctype_digit($input)` → intval($input)
    - Otherwise throw `InvalidArgumentException`

    Edge cases:
    - `"90m"` → 5400 (minutes only, no hours cap)
    - `"3600"` → 3600 (pure integer as seconds)
    - `"1h30m"` and `"1h 30m"` both work (pattern uses `\s*` between)

---

### Step 2 — Run tests

```bash
php artisan test
```

Verify all existing tests still pass. No new tests are created.

---

## Notes

- The `app/Services/` directory is auto-created by PHP's autoloader — no explicit `mkdir` needed; just placing the file there is sufficient since Laravel uses PSR-4.
- `Settings::get()` returns `null` by default; check with `empty()` to catch both `null` and empty string `""`.
- `validateCredentials` is static and must build its own Http request without `$this`.
- The `handleResponse` helper keeps error handling DRY across all instance methods.
