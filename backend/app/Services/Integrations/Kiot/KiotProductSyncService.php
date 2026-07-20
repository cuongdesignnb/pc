<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationSyncState;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Throwable;

class KiotProductSyncService
{
    public function __construct(private readonly KiotClient $client) {}

    public function sync(bool $dryRun = true, bool $full = false, ?string $sku = null): array
    {
        if (! config('integrations.kiot.product_sync_enabled')) {
            throw new KiotIntegrationException('INTEGRATION_DISABLED', 'Đồng bộ sản phẩm KIOT đang tắt.');
        }

        $state = IntegrationSyncState::firstOrNew(['integration' => 'kiot', 'resource' => 'products']);
        $oldWatermark = $state->last_successful_watermark;
        $report = $this->emptyReport($dryRun, $full, $sku);
        $remoteSkus = [];
        $maxUpdatedAt = $oldWatermark ? CarbonImmutable::parse($oldWatermark) : null;

        if (! $dryRun) {
            $state->fill(['status' => 'running', 'last_started_at' => now(), 'last_error_code' => null, 'last_error_message' => null])->save();
        }

        try {
            if ($sku !== null) {
                $response = $this->client->product(trim($sku));
                if (! $response->successful()) {
                    if ($response->errorCode() === 'UNKNOWN_SKU') {
                        $local = Product::where('sku', trim($sku))->get()
                            ->first(fn (Product $candidate) => $candidate->sku === trim($sku));
                        if ($local) {
                            $report['local_unmatched'][] = $local->sku;
                            if (! $dryRun) {
                                $updates = [
                                    'kiot_sync_status' => 'unmatched',
                                    'kiot_sync_error_code' => 'UNKNOWN_SKU',
                                    'kiot_sync_error_message' => $response->errorMessage(),
                                ];
                                if ($local->inventory_source === 'kiot') {
                                    $updates += ['kiot_sellable' => false, 'stock_quantity' => 0, 'kiot_available_quantity' => 0];
                                }
                                $local->update($updates);
                            }
                        }
                    } else {
                        throw $this->responseException($response);
                    }
                } else {
                    $this->processBatch([$response->data()], $dryRun, $report, $remoteSkus, $maxUpdatedAt);
                }
            } else {
                $cursor = null;
                do {
                    $query = [
                        'limit' => min(100, max(1, (int) config('integrations.kiot.product_sync_limit'))),
                        'include_inactive' => 1,
                    ];
                    if ($cursor) {
                        $query['cursor'] = $cursor;
                    }
                    if (! $full && $oldWatermark) {
                        $query['updated_since'] = CarbonImmutable::parse($oldWatermark)
                            ->subSeconds((int) config('integrations.kiot.product_sync_overlap_seconds'))
                            ->toRfc3339String();
                    }

                    $response = $this->client->products($query);
                    if (! $response->successful()) {
                        throw $this->responseException($response);
                    }
                    $this->processBatch($response->data(), $dryRun, $report, $remoteSkus, $maxUpdatedAt);
                    $cursor = $response->meta()['next_cursor'] ?? null;
                    if (! $dryRun) {
                        $state->update(['last_cursor' => $cursor]);
                    }
                } while ($cursor);
            }

            if ($full && $sku === null) {
                Product::query()->select(['id', 'sku', 'inventory_source'])->chunkById(200, function ($products) use (&$report, $remoteSkus, $dryRun) {
                    foreach ($products as $product) {
                        if (! isset($remoteSkus[$product->sku])) {
                            $report['local_unmatched'][] = $product->sku;
                            if (! $dryRun) {
                                $updates = [
                                    'kiot_sync_status' => 'unmatched',
                                    'kiot_sync_error_code' => 'UNKNOWN_SKU',
                                    'kiot_sync_error_message' => 'SKU không tồn tại trong dữ liệu sản phẩm KIOT.',
                                ];
                                if ($product->inventory_source === 'kiot') {
                                    $updates += ['kiot_sellable' => false, 'stock_quantity' => 0, 'kiot_available_quantity' => 0];
                                }
                                $product->update($updates);
                            }
                        }
                    }
                });
            }

            $report['local_unmatched_count'] = count($report['local_unmatched']);
            if (! $dryRun) {
                $state->update([
                    'status' => 'completed',
                    'last_cursor' => null,
                    'last_successful_watermark' => $maxUpdatedAt ?? now(),
                    'last_completed_at' => now(),
                    'items_processed' => $report['total_remote'],
                    'items_matched' => $report['matched'],
                    'items_unmatched' => $report['remote_unmatched_count'] + $report['local_unmatched_count'],
                ]);
            }

            return $report;
        } catch (Throwable $exception) {
            if (! $dryRun) {
                $state->update([
                    'status' => 'failed',
                    'last_error_code' => $exception instanceof KiotIntegrationException ? $exception->errorCode : 'SYNC_FAILED',
                    'last_error_message' => $exception->getMessage(),
                    'last_completed_at' => now(),
                ]);
            }
            throw $exception;
        }
    }

    private function processBatch(array $items, bool $dryRun, array &$report, array &$remoteSkus, ?CarbonImmutable &$maxUpdatedAt): void
    {
        $candidateSkus = array_values(array_filter(array_map(fn ($item) => trim((string) ($item['sku'] ?? '')), $items)));
        $locals = Product::whereIn('sku', $candidateSkus)->get()->groupBy('sku');

        foreach ($items as $remote) {
            $sku = trim((string) ($remote['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $report['total_remote']++;
            $remoteSkus[$sku] = true;
            $product = ($locals->get($sku) ?? collect())->first(fn (Product $candidate) => $candidate->sku === $sku);
            if (! $product) {
                $report['remote_unmatched'][] = $sku;
                $report['remote_unmatched_count']++;

                continue;
            }

            $report['matched']++;
            if ((string) $product->price !== (string) ($remote['retail_price'] ?? '')) {
                $report['price_differences'][] = $sku;
            }
            if ((int) $product->stock_quantity !== (int) ($remote['available_quantity'] ?? 0)) {
                $report['stock_differences'][] = $sku;
            }
            if (! ($remote['is_active'] ?? false)) {
                $report['inactive'][] = $sku;
            }
            if (($remote['sync_status'] ?? null) === 'deleted') {
                $report['deleted'][] = $sku;
            }
            if (! ($remote['sell_directly'] ?? false)) {
                $report['not_sell_directly'][] = $sku;
            }

            $remoteUpdatedAt = isset($remote['updated_at']) ? CarbonImmutable::parse($remote['updated_at']) : null;
            if ($remoteUpdatedAt && (! $maxUpdatedAt || $remoteUpdatedAt->greaterThan($maxUpdatedAt))) {
                $maxUpdatedAt = $remoteUpdatedAt;
            }

            if (! $dryRun) {
                $sellable = ($remote['sync_status'] ?? null) === 'active'
                    && (bool) ($remote['is_active'] ?? false)
                    && (bool) ($remote['sell_directly'] ?? false);
                $availableQuantity = max(0, (int) ($remote['available_quantity'] ?? 0));
                $product->update([
                    'inventory_source' => 'kiot',
                    'kiot_product_id' => $remote['id'] ?? null,
                    'barcode' => $remote['barcode'] ?? null,
                    'price' => $remote['retail_price'],
                    'kiot_retail_price' => $remote['retail_price'],
                    'kiot_physical_quantity' => $remote['stock_quantity'] ?? 0,
                    'kiot_reserved_quantity' => $remote['reserved_quantity'] ?? 0,
                    'stock_quantity' => $sellable ? $availableQuantity : 0,
                    'kiot_available_quantity' => $availableQuantity,
                    'kiot_has_serial' => (bool) ($remote['has_serial'] ?? false),
                    'kiot_sellable' => $sellable,
                    'weight' => $remote['weight'] ?? null,
                    'warranty_months' => $remote['warranty_months'] ?? null,
                    'kiot_sync_status' => $remote['sync_status'] ?? 'active',
                    'kiot_remote_updated_at' => $remoteUpdatedAt,
                    'kiot_synced_at' => now(),
                    'kiot_sync_error_code' => null,
                    'kiot_sync_error_message' => null,
                ]);
            }
        }
    }

    private function emptyReport(bool $dryRun, bool $full, ?string $sku): array
    {
        return [
            'mode' => $dryRun ? 'dry-run' : 'apply', 'full' => $full, 'sku' => $sku,
            'total_remote' => 0, 'matched' => 0, 'remote_unmatched_count' => 0,
            'local_unmatched_count' => 0, 'remote_unmatched' => [], 'local_unmatched' => [],
            'price_differences' => [], 'stock_differences' => [], 'inactive' => [],
            'deleted' => [], 'not_sell_directly' => [],
        ];
    }

    private function responseException(KiotResponse $response): KiotIntegrationException
    {
        return new KiotIntegrationException(
            $response->errorCode() ?? 'INTERNAL_INTEGRATION_ERROR',
            $response->errorMessage(),
            $response->status >= 500 || $response->status === 429 ? 'retryable' : 'business_rejection',
            $response->status,
            $response->body,
        );
    }
}
