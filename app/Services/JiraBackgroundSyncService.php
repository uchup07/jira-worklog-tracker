<?php

namespace App\Services;

use App\Jobs\SyncJiraProjectJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Native\Desktop\Facades\Settings;

class JiraBackgroundSyncService
{
    public const IN_PROGRESS_KEY = 'sync_in_progress';

    public const STARTED_AT_KEY = 'sync_started_at';

    private const STALE_GRACE_SECONDS = 15;

    public function dispatch(string $projectKey): void
    {
        if ($this->isRunning()) {
            throw new \RuntimeException('Sync is already running in the background.');
        }

        $this->markStarted();

        try {
            SyncJiraProjectJob::dispatch($projectKey);
        } catch (\Throwable $e) {
            $this->markFinished();

            throw new \RuntimeException('Unable to queue background sync.', previous: $e);
        }
    }

    public function isRunning(): bool
    {
        if (! (bool) Settings::get(self::IN_PROGRESS_KEY, false)) {
            return false;
        }

        if ($this->shouldClearStaleState()) {
            $this->markFinished();

            return false;
        }

        return true;
    }

    public function markStarted(): void
    {
        Settings::set(self::IN_PROGRESS_KEY, true);
        Settings::set(self::STARTED_AT_KEY, now()->toISOString());
    }

    public function markFinished(): void
    {
        Settings::set(self::IN_PROGRESS_KEY, false);
        Settings::forget(self::STARTED_AT_KEY);
    }

    protected function shouldClearStaleState(): bool
    {
        if ($this->hasActiveSyncJob()) {
            return false;
        }

        $startedAt = Settings::get(self::STARTED_AT_KEY);

        if (blank($startedAt)) {
            return true;
        }

        try {
            $startedAt = CarbonImmutable::parse($startedAt);
        } catch (\Throwable) {
            return true;
        }

        if ($this->hasFailedSyncJobSince($startedAt)) {
            return true;
        }

        return now()->diffInSeconds($startedAt) >= self::STALE_GRACE_SECONDS;
    }

    protected function hasActiveSyncJob(): bool
    {
        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->where('queue', config('queue.connections.database.queue', 'default'))
                ->where('payload', 'like', '%SyncJiraProjectJob%')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function hasFailedSyncJobSince(CarbonImmutable $startedAt): bool
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))
                ->where('exception', 'like', '%SyncJiraProjectJob%')
                ->where('failed_at', '>=', $startedAt->toDateTimeString())
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
