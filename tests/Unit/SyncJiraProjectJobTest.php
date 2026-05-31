<?php

namespace Tests\Unit;

use App\Jobs\SyncJiraProjectJob;
use App\Services\JiraBackgroundSyncService;
use App\Services\JiraSyncService;
use App\Services\JiraSyncServiceFactory;
use Mockery;
use Native\Desktop\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SyncJiraProjectJobTest extends TestCase
{
    public function test_job_shows_success_notification_and_clears_sync_state(): void
    {
        $syncService = Mockery::mock(JiraSyncService::class);
        $syncService->shouldReceive('syncProject')
            ->once()
            ->with('EB')
            ->andReturn(['issues' => 12, 'worklogs' => 34]);

        $syncServiceFactory = Mockery::mock(JiraSyncServiceFactory::class);
        $syncServiceFactory->shouldReceive('make')
            ->once()
            ->andReturn($syncService);

        $notification = Mockery::mock();
        $notification->shouldReceive('title')->once()->with('Jira Sync Complete')->andReturnSelf();
        $notification->shouldReceive('message')->once()->with('Synced 12 issues and 34 worklogs for EB.')->andReturnSelf();
        $notification->shouldReceive('show')->once()->andReturnSelf();

        Notification::shouldReceive('new')->once()->andReturn($notification);

        $backgroundSyncService = Mockery::mock(JiraBackgroundSyncService::class);
        $backgroundSyncService->shouldReceive('markFinished')->once();

        (new SyncJiraProjectJob('EB'))->handle($backgroundSyncService, $syncServiceFactory);
    }

    public function test_job_shows_failure_notification_and_rethrows(): void
    {
        $syncService = Mockery::mock(JiraSyncService::class);
        $syncService->shouldReceive('syncProject')
            ->once()
            ->with('EB')
            ->andThrow(new RuntimeException('Jira API error 500: upstream failed'));

        $syncServiceFactory = Mockery::mock(JiraSyncServiceFactory::class);
        $syncServiceFactory->shouldReceive('make')
            ->once()
            ->andReturn($syncService);

        $notification = Mockery::mock();
        $notification->shouldReceive('title')->once()->with('Jira Sync Failed')->andReturnSelf();
        $notification->shouldReceive('message')->once()->with('Project EB: Jira API error 500: upstream failed')->andReturnSelf();
        $notification->shouldReceive('show')->once()->andReturnSelf();

        Notification::shouldReceive('new')->once()->andReturn($notification);

        $backgroundSyncService = Mockery::mock(JiraBackgroundSyncService::class);
        $backgroundSyncService->shouldReceive('markFinished')->once();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jira API error 500: upstream failed');

        (new SyncJiraProjectJob('EB'))->handle($backgroundSyncService, $syncServiceFactory);
    }
}
