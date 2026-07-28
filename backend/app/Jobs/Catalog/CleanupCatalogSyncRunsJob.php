<?php

namespace App\Jobs\Catalog;

use App\Models\CatalogChannelSyncRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupCatalogSyncRunsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(): void
    {
        CatalogChannelSyncRun::where('created_at', '<', now()->subDays(90))
            ->whereIn('status', ['completed', 'failed'])
            ->orderBy('id')
            ->chunkById(500, fn ($runs) => CatalogChannelSyncRun::whereKey($runs->modelKeys())->delete());
    }
}
