<?php

namespace App\Jobs\Catalog;

use App\Models\CatalogChannelConnection;
use App\Services\Catalog\CatalogChannelAuditService;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGoogleSheetsCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public readonly ?int $requestedBy = null) {}

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(
        GoogleSheetsExporter $exporter,
        CatalogChannelManager $channels,
        CatalogChannelAuditService $audit,
    ): void {
        if (! $channels->isEnabled(CatalogChannelConnection::GOOGLE_SHEETS)) {
            return;
        }

        $connection = $channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);
        try {
            $result = $exporter->sync($this->requestedBy);
            $audit->record($connection->fresh(), 'SYNC_COMPLETED', metadata: [
                'run_id' => $result['run_id'],
                'items_total' => $result['TOTAL_PRODUCTS'],
            ]);
        } catch (\Throwable $exception) {
            $audit->record($connection->fresh(), 'SYNC_FAILED', metadata: [
                'error_code' => $exception instanceof \App\Exceptions\CatalogChannelException
                    ? $exception->errorCode
                    : 'GOOGLE_WRITE_FAILED',
            ]);
            throw $exception;
        }
    }
}
