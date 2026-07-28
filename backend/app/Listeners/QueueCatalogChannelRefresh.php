<?php

namespace App\Listeners;

use App\Events\KiotProductSyncCompleted;
use App\Jobs\Catalog\BuildCatalogFeedCacheJob;
use App\Jobs\Catalog\SyncGoogleSheetsCatalogJob;
use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelManager;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueueCatalogChannelRefresh implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private readonly CatalogChannelManager $channels) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(KiotProductSyncCompleted $event): void
    {
        if ($this->channels->isEnabled(CatalogChannelConnection::GOOGLE_SHEETS)) {
            SyncGoogleSheetsCatalogJob::dispatch();
        }
        foreach ([CatalogChannelConnection::GOOGLE_MERCHANT, CatalogChannelConnection::META_CATALOG] as $channel) {
            if ($this->channels->isEnabled($channel)) {
                BuildCatalogFeedCacheJob::dispatch($channel);
            }
        }
    }
}
