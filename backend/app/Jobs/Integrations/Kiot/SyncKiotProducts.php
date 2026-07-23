<?php

namespace App\Jobs\Integrations\Kiot;

use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncKiotProducts implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly bool $full = false, public readonly bool $dryRun = false) {}

    public function handle(KiotProductSyncService $service): void
    {
        $service->sync(dryRun: $this->dryRun, full: $this->full);
    }
}
