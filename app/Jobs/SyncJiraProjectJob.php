<?php

namespace App\Jobs;

use App\Services\JiraBackgroundSyncService;
use App\Services\JiraSyncServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Facades\Notification;

class SyncJiraProjectJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public string $projectKey) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("jira-sync:{$this->projectKey}"))
                ->expireAfter($this->timeout + 60)
                ->dontRelease(),
        ];
    }

    public function handle(
        JiraBackgroundSyncService $backgroundSyncService,
        JiraSyncServiceFactory $jiraSyncServiceFactory,
    ): void
    {
        try {
            $result = $jiraSyncServiceFactory->make()->syncProject($this->projectKey);

            Notification::new()
                ->title('Jira Sync Complete')
                ->message("Synced {$result['issues']} issues and {$result['worklogs']} worklogs for {$this->projectKey}.")
                ->show();
        } catch (\Throwable $e) {
            report($e);

            Notification::new()
                ->title('Jira Sync Failed')
                ->message("Project {$this->projectKey}: {$e->getMessage()}")
                ->show();

            throw $e;
        } finally {
            $backgroundSyncService->markFinished();
        }
    }

    public function failed(\Throwable $e): void
    {
        app(JiraBackgroundSyncService::class)->markFinished();
    }
}
