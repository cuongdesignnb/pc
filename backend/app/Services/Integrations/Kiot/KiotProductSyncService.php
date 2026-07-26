<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\Category;
use App\Models\IntegrationSyncConflict;
use App\Models\IntegrationSyncRun;
use App\Models\IntegrationSyncState;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class KiotProductSyncService
{
    public function __construct(
        private readonly KiotClient $client,
        private readonly KiotConfigurationResolver $resolver,
        private readonly KiotCategorySyncService $categories,
        private readonly KiotImageMirrorService $images,
    ) {}

    public function sync(
        bool $dryRun = true,
        bool $full = false,
        ?string $sku = null,
        ?int $runId = null,
        ?int $requestedBy = null,
    ): array {
        $runtime = $this->resolver->resolve();
        $dryRun
            ? $this->client->assertConnected($runtime)
            : $this->client->assertProductSyncEnabled($runtime);

        $mode = $sku !== null ? 'targeted' : ($dryRun ? 'dry-run' : ($full ? 'full' : 'incremental'));
        $run = $runId ? IntegrationSyncRun::findOrFail($runId) : IntegrationSyncRun::create([
            'provider' => 'kiot',
            'resource' => 'products',
            'mode' => $mode,
            'status' => 'queued',
            'requested_by' => $requestedBy,
        ]);
        $state = IntegrationSyncState::firstOrNew(['integration' => 'kiot', 'resource' => 'products']);
        $oldWatermark = $state->last_successful_watermark;
        $report = $this->emptyReport($mode, $full, $sku);
        $v2Contract = (bool) ($runtime->capabilities['categories'] ?? false);
        $run->update([
            'mode' => $mode,
            'status' => 'running',
            'started_at' => now(),
            'cursor_before' => $oldWatermark?->toIso8601String(),
            'error_code' => null,
            'error_message' => null,
        ]);

        $lock = Cache::lock('integrations:kiot:product-sync', max(60, (int) config('integrations.kiot.sync_lock_seconds', 3600)));
        if (! $lock->get()) {
            $this->failRun($run, 'SYNC_ALREADY_RUNNING', 'Another KIOT product synchronization is running.');
            throw new KiotIntegrationException('SYNC_ALREADY_RUNNING', 'Một tiến trình đồng bộ sản phẩm KIOT khác đang chạy.', 'business_rejection', 409);
        }

        if (! $dryRun) {
            $state->fill([
                'status' => 'running',
                'last_started_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();
        }

        try {
            $maxUpdatedAt = $oldWatermark ? CarbonImmutable::parse($oldWatermark) : null;
            $updatedSince = ! $full && $sku === null && $oldWatermark
                ? CarbonImmutable::parse($oldWatermark)
                    ->subSeconds($runtime->productSyncOverlapSeconds)
                    ->toRfc3339String()
                : null;

            if (($runtime->capabilities['categories'] ?? false) && $sku === null) {
                $categoryItems = $this->fetchAll('categories', $updatedSince, $dryRun, $run, $report);
                $this->categories->sync($categoryItems, $dryRun, $report);
                $this->captureMaxUpdatedAt($categoryItems, $maxUpdatedAt);
            }

            if ($runtime->capabilities['price_books'] ?? false) {
                $books = $this->fetchAll('price-books', $updatedSince, $dryRun, $run, $report);
                $report['price_books_available'] = count(array_filter(
                    $books,
                    fn (array $book): bool => ($book['sync_status'] ?? null) === 'active' && (bool) ($book['is_active'] ?? false),
                ));
            }

            $remoteIds = [];
            $remoteSkus = [];
            if ($sku !== null) {
                $response = $this->client->product(trim($sku), requireProductSync: ! $dryRun);
                if (! $response->successful()) {
                    if ($response->errorCode() === 'UNKNOWN_SKU') {
                        $this->markUnknownTargetedSku(trim($sku), $response, $dryRun, $report);
                    } else {
                        throw $this->responseException($response);
                    }
                } elseif ($v2Contract) {
                    $this->processProducts([$response->data()], $dryRun, $report, $remoteIds, $maxUpdatedAt, $run);
                } else {
                    $this->processLegacyProducts([$response->data()], $dryRun, $report, $remoteSkus, $maxUpdatedAt, $run);
                }
            } else {
                $this->fetchAndProcessProducts(
                    $updatedSince,
                    $dryRun,
                    $run,
                    $report,
                    $remoteIds,
                    $remoteSkus,
                    $maxUpdatedAt,
                    $v2Contract,
                );
            }

            if ($full && $sku === null) {
                if ($v2Contract) {
                    $this->hideMissingMappedProducts($remoteIds, $dryRun, $report);
                } else {
                    $this->hideMissingLegacyProducts($remoteSkus, $dryRun, $report);
                }
            }

            $report['remote_unmatched_count'] = count($report['remote_unmatched']);
            $report['local_unmatched_count'] = count($report['local_unmatched']);

            $status = $report['warnings'] > 0 || $report['errors'] > 0
                ? 'completed_with_warnings'
                : 'completed';
            $run->update([
                'status' => $status,
                'completed_at' => now(),
                'cursor_after' => $dryRun ? $run->cursor_before : $maxUpdatedAt?->toIso8601String(),
                'totals_json' => $this->reportTotals($report),
                'warnings_json' => $report['warning_details'],
                'remote_processed' => $report['total_remote'],
                'created' => $report['created'],
                'updated' => $report['updated'],
                'unchanged' => $report['unchanged'],
                'images_downloaded' => $report['images_downloaded'],
                'warnings' => $report['warnings'],
                'errors' => $report['errors'],
            ]);

            if (! $dryRun) {
                $state->update([
                    'status' => $status,
                    'last_cursor' => null,
                    'last_successful_watermark' => $maxUpdatedAt ?? now(),
                    'last_completed_at' => now(),
                    'items_processed' => $report['total_remote'],
                    'items_matched' => $report['updated'] + $report['unchanged'],
                    'items_unmatched' => $report['conflicts'] + $report['remote_unmatched_count'] + $report['local_unmatched_count'],
                ]);
            }

            return $report + ['run_id' => $run->id, 'status' => $status];
        } catch (Throwable $exception) {
            $code = $exception instanceof KiotIntegrationException ? $exception->errorCode : 'SYNC_FAILED';
            $this->failRun($run, $code, $exception->getMessage());
            if (! $dryRun) {
                $state->update([
                    'status' => 'failed',
                    'last_error_code' => $code,
                    'last_error_message' => $exception->getMessage(),
                    'last_completed_at' => now(),
                ]);
            }
            throw $exception;
        } finally {
            $this->release($lock);
        }
    }

    private function fetchAll(
        string $resource,
        ?string $updatedSince,
        bool $dryRun,
        IntegrationSyncRun $run,
        array &$report,
    ): array {
        $runtime = $this->resolver->resolve();
        $cursor = null;
        $items = [];
        do {
            $query = ['limit' => $runtime->productSyncLimit, 'include_inactive' => 1];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }
            if ($updatedSince) {
                $query['updated_since'] = $updatedSince;
            }
            $response = match ($resource) {
                'categories' => $this->client->categories($query, requireProductSync: ! $dryRun),
                'price-books' => $this->client->priceBooks($query, requireProductSync: ! $dryRun),
                default => $this->client->products($query, requireProductSync: ! $dryRun),
            };
            if (! $response->successful()) {
                throw $this->responseException($response);
            }
            array_push($items, ...$response->data());
            $cursor = $response->meta()['next_cursor'] ?? null;
            $report['pages_processed']++;
            $run->update(['pages_processed' => $report['pages_processed']]);
        } while ($cursor);

        return $items;
    }

    private function fetchAndProcessProducts(
        ?string $updatedSince,
        bool $dryRun,
        IntegrationSyncRun $run,
        array &$report,
        array &$remoteIds,
        array &$remoteSkus,
        ?CarbonImmutable &$maxUpdatedAt,
        bool $v2Contract,
    ): void {
        $runtime = $this->resolver->resolve();
        $cursor = null;
        do {
            $query = ['limit' => $runtime->productSyncLimit, 'include_inactive' => 1];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }
            if ($updatedSince) {
                $query['updated_since'] = $updatedSince;
            }
            $response = $this->client->products($query, requireProductSync: ! $dryRun);
            if (! $response->successful()) {
                throw $this->responseException($response);
            }
            if ($v2Contract) {
                $this->processProducts($response->data(), $dryRun, $report, $remoteIds, $maxUpdatedAt, $run);
            } else {
                $this->processLegacyProducts($response->data(), $dryRun, $report, $remoteSkus, $maxUpdatedAt, $run);
            }
            $cursor = $response->meta()['next_cursor'] ?? null;
            $report['pages_processed']++;
            $run->update(['pages_processed' => $report['pages_processed']]);
        } while ($cursor);
    }

    private function processLegacyProducts(
        array $items,
        bool $dryRun,
        array &$report,
        array &$remoteSkus,
        ?CarbonImmutable &$maxUpdatedAt,
        IntegrationSyncRun $run,
    ): void {
        foreach ($items as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $sku = trim((string) ($remote['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $report['total_remote']++;
            $remoteSkus[$sku] = true;
            $product = Product::query()->where('sku', $sku)->get()
                ->first(fn (Product $candidate): bool => $candidate->sku === $sku);
            if (! $product) {
                $report['remote_unmatched'][] = $sku;
                $report['remote_unmatched_count']++;
                $this->updateRunProgress($run, $report);

                continue;
            }

            $report['matched']++;
            $remoteUpdatedAt = isset($remote['updated_at']) ? CarbonImmutable::parse($remote['updated_at']) : null;
            if ($remoteUpdatedAt && (! $maxUpdatedAt || $remoteUpdatedAt->greaterThan($maxUpdatedAt))) {
                $maxUpdatedAt = $remoteUpdatedAt;
            }
            $sellable = ($remote['sync_status'] ?? null) === 'active'
                && (bool) ($remote['is_active'] ?? false)
                && (bool) ($remote['sell_directly'] ?? false);
            $availableQuantity = max(0, (int) ($remote['available_quantity'] ?? 0));
            $attributes = [
                'inventory_source' => 'kiot',
                'kiot_product_id' => isset($remote['id']) ? (int) $remote['id'] : null,
                'barcode' => $remote['barcode'] ?? null,
                'price' => $remote['retail_price'] ?? 0,
                'kiot_retail_price' => $remote['retail_price'] ?? 0,
                'kiot_physical_quantity' => max(0, (int) ($remote['stock_quantity'] ?? 0)),
                'kiot_reserved_quantity' => max(0, (int) ($remote['reserved_quantity'] ?? 0)),
                'stock_quantity' => $sellable ? $availableQuantity : 0,
                'kiot_available_quantity' => $availableQuantity,
                'kiot_has_serial' => (bool) ($remote['has_serial'] ?? false),
                'kiot_sellable' => $sellable,
                'kiot_sync_status' => $remote['sync_status'] ?? 'active',
                'kiot_remote_updated_at' => $remoteUpdatedAt,
                'kiot_synced_at' => now(),
                'kiot_sync_error_code' => null,
                'kiot_sync_error_message' => null,
            ];
            if (isset($remote['weight'])) {
                $attributes['weight'] = (int) $remote['weight'];
            }
            if (isset($remote['warranty_months'])) {
                $attributes['warranty_months'] = (int) $remote['warranty_months'];
            }
            if ((string) $product->price !== (string) $attributes['price']) {
                $report['price_changes']++;
                $report['price_differences'][] = $sku;
            }
            if ((int) $product->stock_quantity !== (int) $attributes['stock_quantity']) {
                $report['stock_changes']++;
                $report['stock_differences'][] = $sku;
            }
            if (! $dryRun) {
                $product->update($attributes);
                $report['updated']++;
            }
            $this->updateRunProgress($run, $report);
        }
    }

    private function markUnknownTargetedSku(
        string $sku,
        KiotResponse $response,
        bool $dryRun,
        array &$report,
    ): void {
        $local = Product::query()->where('sku', $sku)->get()
            ->first(fn (Product $candidate): bool => $candidate->sku === $sku);
        if (! $local) {
            return;
        }
        $report['local_unmatched'][] = $local->sku;
        if ($dryRun) {
            return;
        }
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

    private function processProducts(
        array $items,
        bool $dryRun,
        array &$report,
        array &$remoteIds,
        ?CarbonImmutable &$maxUpdatedAt,
        IntegrationSyncRun $run,
    ): void {
        foreach ($items as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $remoteId = (int) ($remote['id'] ?? 0);
            $sku = trim((string) ($remote['sku'] ?? ''));
            if ($remoteId <= 0 || $sku === '') {
                $report['warnings']++;
                $report['errors']++;
                $report['warning_details'][] = ['code' => 'INVALID_PRODUCT_IDENTITY', 'remote_id' => $remoteId, 'sku' => $sku];

                continue;
            }

            $report['total_remote']++;
            $remoteIds[$remoteId] = true;
            $remoteUpdatedAt = isset($remote['updated_at']) ? CarbonImmutable::parse($remote['updated_at']) : null;
            if ($remoteUpdatedAt && (! $maxUpdatedAt || $remoteUpdatedAt->greaterThan($maxUpdatedAt))) {
                $maxUpdatedAt = $remoteUpdatedAt;
            }

            $product = Product::query()
                ->where('provider', 'kiot')
                ->where('remote_product_id', $remoteId)
                ->first();
            if (! $product) {
                $product = Product::query()->where('kiot_product_id', $remoteId)->first();
            }
            if (! $product) {
                $skuConflict = $this->skuConflict($sku);
                if ($skuConflict) {
                    $this->recordSkuConflict($remote, $skuConflict, $dryRun, $report);
                    $this->updateRunProgress($run, $report);

                    continue;
                }
            }

            $checksum = $this->checksum($remote);
            $category = $this->mappedCategory($remote);
            $selectedPrice = $this->money($remote['pricing']['selected_price'] ?? null, $remoteId, $report);
            if ($report['selected_price_book'] === null && isset($remote['pricing']['selected_price_book_id'])) {
                $report['selected_price_book'] = [
                    'id' => (int) $remote['pricing']['selected_price_book_id'],
                    'code' => $remote['pricing']['selected_price_book_code'] ?? null,
                    'name' => $remote['pricing']['selected_price_book_name'] ?? null,
                ];
            }
            $status = (string) ($remote['sync_status'] ?? 'inactive');
            $availabilityStatus = (string) ($remote['inventory']['status'] ?? 'inactive');
            $availableQuantity = max(0, (int) ($remote['inventory']['available_quantity'] ?? 0));
            $categoryVisible = $category?->isVisibleOnStorefront()
                ?? (bool) ($remote['category']['show_on_pc_website'] ?? false);
            $providerVisible = (bool) ($remote['publishing']['show_on_pc_website'] ?? false);
            $active = $status === 'active' && (bool) ($remote['is_active'] ?? false);
            $visible = $active && $providerVisible && $categoryVisible;
            $sellable = $visible
                && $availabilityStatus === 'available'
                && (bool) ($remote['availability']['is_available'] ?? false)
                && $availableQuantity > 0
                && $selectedPrice > 0;
            $attributes = [
                'provider' => 'kiot',
                'remote_product_id' => $remoteId,
                'kiot_product_id' => $remoteId,
                'inventory_source' => 'kiot',
                'category_id' => $category?->id,
                'name' => trim((string) ($remote['name'] ?? '')) ?: 'KIOT Product '.$remoteId,
                'sku' => $sku,
                'barcode' => $remote['barcode'] ?? null,
                'price' => $selectedPrice,
                'stock_quantity' => $sellable ? $availableQuantity : 0,
                'is_active' => $active,
                'show_on_pc_website' => $visible,
                'kiot_sync_status' => $status,
                'kiot_availability_status' => $availabilityStatus,
                'kiot_is_under_repair' => (bool) ($remote['availability']['is_under_repair'] ?? false),
                'kiot_sellable' => $sellable,
                'kiot_has_serial' => (bool) ($remote['has_serial'] ?? false),
                'kiot_physical_quantity' => max(0, (int) ($remote['inventory']['stock_quantity'] ?? 0)),
                'kiot_reserved_quantity' => max(0, (int) ($remote['inventory']['reserved_quantity'] ?? 0)),
                'kiot_available_quantity' => $availableQuantity,
                'kiot_retail_price' => $this->money($remote['pricing']['retail_price'] ?? 0, $remoteId, $report),
                'kiot_selected_price' => $selectedPrice,
                'kiot_price_fallback_used' => (bool) ($remote['pricing']['fallback_used'] ?? false),
                'kiot_sync_checksum' => $checksum,
                'kiot_remote_updated_at' => $remoteUpdatedAt,
                'kiot_synced_at' => now(),
                'kiot_sync_error_code' => null,
                'kiot_sync_error_message' => null,
            ];
            if (isset($remote['weight'])) {
                $attributes['weight'] = (int) $remote['weight'];
            }
            if (isset($remote['warranty_months'])) {
                $attributes['warranty_months'] = (int) $remote['warranty_months'];
            }
            if ($product === null) {
                $attributes['slug'] = $this->uniqueProductSlug($attributes['name'], $sku, $remoteId);
                $attributes['description'] = $remote['description'] ?? null;
                $attributes['short_description'] = null;
                $attributes['sale_price'] = null;
                $attributes['cost_price'] = null;
                $attributes['is_featured'] = false;
                $report['create_candidates']++;
                $report['remote_unmatched'][] = $sku;
                if (! $dryRun) {
                    $product = Product::create($attributes);
                    $report['created']++;
                }
            } elseif (hash_equals((string) $product->kiot_sync_checksum, $checksum)) {
                $report['unchanged']++;
                $report['matched']++;
            } else {
                if (blank($product->description) && filled($remote['description'] ?? null)) {
                    $attributes['description'] = $remote['description'];
                }
                $this->classifyChanges($product, $attributes, $remote, $report);
                $report['update_candidates']++;
                $report['matched']++;
                if (! $dryRun) {
                    $product->fill($attributes);
                    if ($product->isDirty()) {
                        $product->save();
                        $report['updated']++;
                    } else {
                        $report['unchanged']++;
                    }
                }
            }

            if ($availabilityStatus === 'repairing') {
                $report['repairing']++;
            }
            if (! $categoryVisible) {
                $report['hidden_by_category']++;
            }
            if ($status === 'deleted') {
                $report['deleted']++;
            }
            if ((bool) ($remote['pricing']['fallback_used'] ?? false)) {
                $report['price_fallback']++;
                $report['warnings']++;
                $report['warning_details'][] = ['code' => 'PRICE_FALLBACK_USED', 'remote_product_id' => $remoteId, 'sku' => $sku];
            }

            if ($product) {
                $this->images->sync($product, (array) ($remote['images'] ?? []), $dryRun, $report);
            } else {
                $report['image_downloads'] += count((array) ($remote['images'] ?? []));
            }
            $this->updateRunProgress($run, $report);
        }
    }

    private function mappedCategory(array $remote): ?Category
    {
        $remoteCategoryId = (int) ($remote['category']['id'] ?? 0);

        return $remoteCategoryId > 0
            ? Category::query()->where(['provider' => 'kiot', 'remote_category_id' => $remoteCategoryId])->first()
            : null;
    }

    private function skuConflict(string $sku): ?Product
    {
        return Product::query()
            ->whereRaw('LOWER(sku) = ?', [Str::lower($sku)])
            ->get()
            ->first();
    }

    private function recordSkuConflict(array $remote, Product $local, bool $dryRun, array &$report): void
    {
        $remoteId = (int) $remote['id'];
        $sku = (string) $remote['sku'];
        $report['conflicts']++;
        $report['warnings']++;
        $report['errors']++;
        $report['warning_details'][] = [
            'code' => 'SKU_CONFLICT',
            'remote_product_id' => $remoteId,
            'sku' => $sku,
            'local_product_id' => $local->id,
        ];
        if (! $dryRun) {
            IntegrationSyncConflict::updateOrCreate(
                [
                    'provider' => 'kiot',
                    'resource' => 'products',
                    'conflict_type' => 'sku_unmapped',
                    'remote_id' => $remoteId,
                ],
                [
                    'sku' => $sku,
                    'local_product_id' => $local->id,
                    'status' => 'open',
                    'details_json' => [
                        'remote_name' => $remote['name'] ?? null,
                        'local_name' => $local->name,
                        'exact_case' => $local->sku === $sku,
                    ],
                    'resolved_at' => null,
                ],
            );
        }
    }

    private function classifyChanges(Product $product, array $attributes, array $remote, array &$report): void
    {
        if ((string) $product->price !== (string) $attributes['price']) {
            $report['price_changes']++;
        }
        if ((int) $product->kiot_available_quantity !== (int) $attributes['kiot_available_quantity']) {
            $report['stock_changes']++;
        }
        $report['preview'][] = [
            'remote_product_id' => (int) $remote['id'],
            'sku' => (string) $remote['sku'],
            'local_product_id' => $product->id,
            'checksum' => $attributes['kiot_sync_checksum'],
        ];
    }

    private function hideMissingMappedProducts(array $remoteIds, bool $dryRun, array &$report): void
    {
        Product::query()->where('provider', 'kiot')->select(['id', 'remote_product_id', 'sku'])->chunkById(200, function (Collection $products) use ($remoteIds, $dryRun, &$report) {
            foreach ($products as $product) {
                if (isset($remoteIds[(int) $product->remote_product_id])) {
                    continue;
                }
                $report['local_unmatched'][] = $product->sku;
                if (! $dryRun) {
                    $product->update([
                        'show_on_pc_website' => false,
                        'kiot_sellable' => false,
                        'kiot_sync_status' => 'unmatched',
                        'stock_quantity' => 0,
                        'kiot_sync_error_code' => 'REMOTE_PRODUCT_MISSING',
                        'kiot_sync_error_message' => 'Mapped product was absent from the complete provider response.',
                    ]);
                }
            }
        });
    }

    private function hideMissingLegacyProducts(array $remoteSkus, bool $dryRun, array &$report): void
    {
        Product::query()->select(['id', 'sku', 'inventory_source'])->chunkById(200, function (Collection $products) use ($remoteSkus, $dryRun, &$report) {
            foreach ($products as $product) {
                if (isset($remoteSkus[$product->sku])) {
                    continue;
                }
                $report['local_unmatched'][] = $product->sku;
                if ($dryRun) {
                    continue;
                }
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
        });
    }

    private function money(mixed $value, int $remoteId, array &$report): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        $report['warnings']++;
        $report['errors']++;
        $report['warning_details'][] = ['code' => 'INVALID_SELECTED_PRICE', 'remote_product_id' => $remoteId];

        return 0;
    }

    private function uniqueProductSlug(string $name, string $sku, int $remoteId): string
    {
        $base = Str::slug($name) ?: Str::slug($sku) ?: 'kiot-product-'.$remoteId;
        $slug = $base;
        $suffix = 0;
        while (Product::query()->where('slug', $slug)->exists() || Category::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$remoteId.($suffix > 1 ? '-'.$suffix : '');
        }

        return $slug;
    }

    private function checksum(array $remote): string
    {
        unset($remote['updated_at']);
        $this->sortRecursive($remote);

        return hash('sha256', json_encode($remote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
    }

    private function captureMaxUpdatedAt(array $items, ?CarbonImmutable &$max): void
    {
        foreach ($items as $item) {
            if (! isset($item['updated_at'])) {
                continue;
            }
            $candidate = CarbonImmutable::parse($item['updated_at']);
            if (! $max || $candidate->greaterThan($max)) {
                $max = $candidate;
            }
        }
    }

    private function emptyReport(string $mode, bool $full, ?string $sku): array
    {
        return [
            'mode' => $mode, 'full' => $full, 'sku' => $sku,
            'total_remote' => 0, 'create_candidates' => 0, 'update_candidates' => 0,
            'created' => 0, 'updated' => 0, 'unchanged' => 0,
            'category_create' => 0, 'category_update' => 0, 'category_hidden' => 0,
            'price_changes' => 0, 'stock_changes' => 0, 'repairing' => 0,
            'hidden_by_category' => 0, 'deleted' => 0, 'image_downloads' => 0,
            'image_skips' => 0, 'image_archives' => 0, 'images_downloaded' => 0,
            'price_fallback' => 0, 'price_books_available' => 0, 'conflicts' => 0,
            'selected_price_book' => null,
            'warnings' => 0, 'errors' => 0, 'warning_details' => [],
            'pages_processed' => 0, 'matched' => 0, 'remote_unmatched' => [],
            'remote_unmatched_count' => 0, 'local_unmatched_count' => 0,
            'price_differences' => [], 'stock_differences' => [],
            'inactive' => [], 'not_sell_directly' => [],
            'local_unmatched' => [], 'preview' => [],
        ];
    }

    private function reportTotals(array $report): array
    {
        unset($report['warning_details'], $report['preview']);

        return $report;
    }

    private function updateRunProgress(IntegrationSyncRun $run, array $report): void
    {
        if ($report['total_remote'] % 25 !== 0) {
            return;
        }
        $run->update([
            'remote_processed' => $report['total_remote'],
            'created' => $report['created'],
            'updated' => $report['updated'],
            'unchanged' => $report['unchanged'],
            'images_downloaded' => $report['images_downloaded'],
            'warnings' => $report['warnings'],
            'errors' => $report['errors'],
        ]);
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

    private function failRun(IntegrationSyncRun $run, string $code, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_code' => $code,
            'error_message' => $message,
        ]);
    }

    private function release(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable) {
            // The lock TTL remains the final fail-safe if the backend disappeared.
        }
    }
}
