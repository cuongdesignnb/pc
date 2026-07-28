<?php

namespace App\Jobs\Catalog;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelAuditService;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildCatalogFeedCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $channel,
        public readonly ?int $requestedBy = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->channel;
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(
        CatalogChannelManager $channels,
        CatalogChannelAuditService $audit,
        GoogleMerchantFeedBuilder $google,
        MetaCatalogFeedBuilder $meta,
    ): void {
        if (! $channels->isEnabled($this->channel)) {
            return;
        }

        $connection = $channels->connection($this->channel);
        try {
            $result = match ($this->channel) {
                CatalogChannelConnection::GOOGLE_MERCHANT => $google->build(requestedBy: $this->requestedBy),
                CatalogChannelConnection::META_CATALOG => $meta->build(requestedBy: $this->requestedBy),
                default => null,
            };
            $audit->record($connection->fresh(), 'FEED_REBUILT', metadata: [
                'run_id' => $result['run_id'] ?? null,
                'items_total' => $result['TOTAL_PRODUCTS'] ?? 0,
            ]);
        } catch (\Throwable $exception) {
            $audit->record($connection->fresh(), 'SYNC_FAILED', metadata: [
                'error_code' => $exception instanceof \App\Exceptions\CatalogChannelException
                    ? $exception->errorCode
                    : 'FEED_BUILD_FAILED',
            ]);
            throw $exception;
        }
    }
}
