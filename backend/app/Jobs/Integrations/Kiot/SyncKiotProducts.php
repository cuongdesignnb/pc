<?php

namespace App\Jobs\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncKiotProducts implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 3600;

    public function __construct(
        public readonly bool $full = false,
        public readonly bool $dryRun = false,
        public readonly ?int $runId = null,
        public readonly ?int $requestedBy = null,
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(KiotProductSyncService $service): void
    {
        try {
            $service->sync(
                dryRun: $this->dryRun,
                full: $this->full,
                runId: $this->runId,
                requestedBy: $this->requestedBy,
            );
        } catch (KiotIntegrationException $exception) {
            if (! $exception->retryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
