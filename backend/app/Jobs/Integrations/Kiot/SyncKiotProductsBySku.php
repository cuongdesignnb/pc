<?php

namespace App\Jobs\Integrations\Kiot;

use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncKiotProductsBySku implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(
        public readonly array $skus,
        public readonly ?int $requestedBy = null,
    ) {}

    public function handle(KiotProductSyncService $service): void
    {
        foreach (array_values(array_unique($this->skus)) as $sku) {
            $service->sync(dryRun: false, sku: trim($sku), requestedBy: $this->requestedBy);
        }
    }
}
