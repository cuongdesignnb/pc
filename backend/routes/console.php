<?php

use App\Jobs\Catalog\BuildCatalogFeedCacheJob;
use App\Jobs\Catalog\CleanupCatalogSyncRunsJob;
use App\Jobs\Catalog\SyncGoogleSheetsCatalogJob;
use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new \App\Jobs\Integrations\Kiot\SyncKiotProducts)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => app(KiotConfigurationResolver::class)->resolve()->productSyncEnabled);

Schedule::command('kiot:retry-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => app(KiotConfigurationResolver::class)->resolve()->orderSyncEnabled);

Schedule::job(new SyncGoogleSheetsCatalogJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer()
    ->when(fn () => app(CatalogChannelManager::class)->isEnabled(CatalogChannelConnection::GOOGLE_SHEETS));

Schedule::job(new BuildCatalogFeedCacheJob(CatalogChannelConnection::GOOGLE_MERCHANT))
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer()
    ->when(fn () => app(CatalogChannelManager::class)->isEnabled(CatalogChannelConnection::GOOGLE_MERCHANT));

Schedule::job(new BuildCatalogFeedCacheJob(CatalogChannelConnection::META_CATALOG))
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->onOneServer()
    ->when(fn () => app(CatalogChannelManager::class)->isEnabled(CatalogChannelConnection::META_CATALOG));

Schedule::job(new CleanupCatalogSyncRunsJob)
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();
