<?php

namespace App\Services\Catalog\Feeds;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelItemState;
use App\Models\CatalogChannelSyncConflict;
use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogChannelSyncRunItem;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\CatalogProductValidator;
use App\Services\Catalog\Pricing\CatalogChannelPriceResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CatalogCommerceFeedBuilder
{
    public function __construct(
        private readonly CatalogProductProjectionService $projection,
        private readonly CatalogProductValidator $validator,
        private readonly CatalogChannelPriceResolver $prices,
    ) {}

    public function build(
        string $channel,
        string $artifact,
        CatalogFeedRenderer $renderer,
        CatalogFeedValidator $feedValidator,
        bool $dryRun = false,
        ?int $requestedBy = null,
        ?CatalogFeedItemStrategy $itemStrategy = null,
    ): array {
        $lock = Cache::lock("catalog:feed:{$channel}", (int) config('catalog.sync_lock_seconds', 1800));
        if (! $lock->get()) {
            throw new CatalogChannelException('SYNC_ALREADY_RUNNING', 'Một lần build feed khác đang chạy.', 409);
        }

        $run = CatalogChannelSyncRun::create([
            'channel' => $channel,
            'mode' => $dryRun ? 'dry_run' : 'build',
            'status' => 'running',
            'started_at' => now(),
            'requested_by' => $requestedBy,
        ]);
        $connection = CatalogChannelConnection::firstOrCreate(
            ['channel' => $channel],
            ['status' => 'not_configured', 'is_enabled' => false, 'configuration_encrypted' => []],
        );
        $disk = Storage::disk((string) config('catalog.feed_disk', 'local'));
        $directory = trim((string) config('catalog.feed_directory', 'catalog-feeds'), '/');
        $disk->makeDirectory($directory);
        $finalPath = $disk->path($directory.'/'.$artifact);
        $temporaryPath = $disk->path($directory.'/.'.$artifact.'.'.Str::uuid().'.tmp');
        $summary = [
            'TOTAL_PRODUCTS' => 0,
            'TOTAL_ROWS' => 0,
            'VALID_PRODUCTS' => 0,
            'INVALID_PRODUCTS' => 0,
            'VALID_ROWS' => 0,
            'INVALID_ROWS' => 0,
            'CREATE_CANDIDATES' => 0,
            'UPDATE_CANDIDATES' => 0,
            'UNCHANGED' => 0,
            'SKIPPED' => 0,
            'WARNING_COUNT' => 0,
            'ERROR_COUNT' => 0,
            'ERRORS_BY_CODE' => [],
            'PRODUCTS_MISSING_BRAND' => 0,
            'PRODUCTS_MISSING_DESCRIPTION' => 0,
            'PRODUCTS_MISSING_PRICE' => 0,
            'PRODUCTS_MISSING_URL' => 0,
            'PRODUCTS_MISSING_IMAGE' => 0,
            'PRODUCTS_WITHOUT_SELLABLE_INVENTORY' => 0,
            'PRODUCTS_WITH_VARIANTS' => 0,
            'PRODUCTS_WITH_ITEM_GROUP_ID' => 0,
        ];
        $states = [];
        $runItems = [];
        $seen = [];
        $existingStates = CatalogChannelItemState::where('channel', $channel)->get()->keyBy('product_id');

        try {
            $products = function () use (
                &$summary,
                &$states,
                &$runItems,
                &$seen,
                $channel,
                $existingStates,
                $itemStrategy,
                $run,
                $dryRun,
            ): \Generator {
                foreach ($this->projection->projected() as [$product]) {
                    $resolvedPrice = $this->prices->resolveData($product, $channel);
                    $product = $product->withPrice($resolvedPrice['value']);
                    $summary['TOTAL_PRODUCTS']++;
                    $items = $itemStrategy?->items($product) ?? [$product];
                    $summary['TOTAL_ROWS'] += count($items);
                    if ($itemStrategy !== null) {
                        foreach ($itemStrategy->summaryCounters($product, $items) as $key => $increment) {
                            $summary[$key] = ($summary[$key] ?? 0) + $increment;
                        }
                    }

                    $productErrors = [];
                    $validRows = 0;
                    $invalidRows = 0;
                    $duplicateExternalIds = [];
                    $duplicateCode = $itemStrategy?->duplicateIdErrorCode() ?? 'DUPLICATE_EXTERNAL_ID';
                    foreach ($items as $item) {
                        $validation = $itemStrategy !== null
                            ? $itemStrategy->validate($item, $product)
                            : $this->validator->validate($product);
                        $errors = $validation->errors;
                        if ($resolvedPrice['issue']) {
                            $errors[] = $resolvedPrice['issue'];
                        }

                        $externalId = $itemStrategy !== null
                            ? $itemStrategy->externalId($item)
                            : $product->externalId;
                        if ($externalId !== '' && isset($seen[$externalId])) {
                            $errors[] = $duplicateCode;
                            $duplicateExternalIds[] = $externalId;
                        }
                        if ($externalId !== '') {
                            $seen[$externalId] = true;
                        }

                        $errors = array_values(array_unique($errors));
                        foreach ($errors as $error) {
                            $summary['ERRORS_BY_CODE'][$error] = ($summary['ERRORS_BY_CODE'][$error] ?? 0) + 1;
                        }
                        $summary['ERROR_COUNT'] += count($errors);
                        $summary['WARNING_COUNT'] += count($validation->warnings);
                        $productErrors = array_merge($productErrors, $errors);

                        if ($errors === []) {
                            $validRows++;
                            $summary['VALID_ROWS']++;
                            yield $item;
                        } else {
                            $invalidRows++;
                            $summary['INVALID_ROWS']++;
                            $summary['SKIPPED']++;
                        }
                    }

                    $productErrors = array_values(array_unique($productErrors));
                    $this->countProductDiagnostics($summary, $productErrors);
                    $productIsValid = $items !== [] && $invalidRows === 0;
                    $productIsValid ? $summary['VALID_PRODUCTS']++ : $summary['INVALID_PRODUCTS']++;
                    $hasValidRows = $validRows > 0;
                    $checksum = $itemStrategy !== null
                        ? $itemStrategy->checksum($product, $items)
                        : $product->checksum;
                    $existing = $existingStates->get($product->id);
                    $action = 'skip';
                    if ($hasValidRows) {
                        if ($existing?->checksum === $checksum) {
                            $summary['UNCHANGED']++;
                            $action = 'unchanged';
                        } elseif ($existing) {
                            $summary['UPDATE_CANDIDATES']++;
                            $action = 'update';
                        } else {
                            $summary['CREATE_CANDIDATES']++;
                            $action = 'create';
                        }
                    }
                    $status = match (true) {
                        $validRows > 0 && $invalidRows > 0 => 'PARTIAL',
                        $validRows > 0 => 'ACTIVE',
                        default => 'INVALID',
                    };
                    $states[] = $this->state($product, $productErrors, $status, $checksum);
                    $runItems[] = $this->runItem(
                        $run->id,
                        $product,
                        $productErrors,
                        $action,
                        $status,
                        (string) ($resolvedPrice['source'] ?? ''),
                        $dryRun,
                    );

                    foreach (array_unique($duplicateExternalIds) as $duplicateExternalId) {
                        CatalogChannelSyncConflict::firstOrCreate([
                            'channel' => $channel,
                            'product_id' => $product->id,
                            'external_id' => $duplicateExternalId,
                            'conflict_type' => $duplicateCode,
                            'status' => 'open',
                        ], ['details_json' => ['safe' => true]]);
                    }
                }
            };

            $renderer->render($products(), $temporaryPath);
            $this->persistRunItems($runItems);
            $validation = $feedValidator->validate($temporaryPath);
            if (($validation['items'] ?? 0) === 0 && ! $dryRun) {
                throw new CatalogChannelException('FEED_EMPTY', 'Feed không có sản phẩm hợp lệ.');
            }

            if (! $dryRun) {
                if (! @rename($temporaryPath, $finalPath)) {
                    throw new CatalogChannelException('FEED_BUILD_FAILED', 'Không thể thay thế feed artifact một cách an toàn.');
                }
                $this->persistStates($channel, $states);
                $connection->update([
                    'status' => 'connected',
                    'last_success_at' => now(),
                    'last_error_at' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);
                CatalogChannelSyncRunItem::where('sync_run_id', $run->id)
                    ->whereIn('eligibility_status', ['eligible', 'partial'])
                    ->update([
                        'result_status' => 'built',
                        'updated_at' => now(),
                    ]);
            } else {
                @unlink($temporaryPath);
            }

            $run->update($this->completedRun($summary));

            return $summary + [
                'run_id' => $run->id,
                'artifact' => $dryRun ? null : $directory.'/'.$artifact,
                'etag' => $dryRun ? null : hash_file('sha256', $finalPath),
            ];
        } catch (Throwable $exception) {
            @unlink($temporaryPath);
            $code = $exception instanceof CatalogChannelException ? $exception->errorCode : 'FEED_BUILD_FAILED';
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_code' => $code,
                'error_message' => 'Catalog feed build failed.',
                'summary_json' => $summary,
            ]);
            if (! $dryRun) {
                $connection->update([
                    'status' => 'error',
                    'last_error_at' => now(),
                    'last_error_code' => $code,
                    'last_error_message' => 'Catalog feed build failed.',
                ]);
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function validateExisting(string $artifact, CatalogFeedValidator $validator): array
    {
        $path = Storage::disk((string) config('catalog.feed_disk', 'local'))
            ->path(trim((string) config('catalog.feed_directory', 'catalog-feeds'), '/').'/'.$artifact);
        if (! is_file($path)) {
            throw new CatalogChannelException('FEED_EMPTY', 'Feed artifact chưa được build.', 404);
        }

        return $validator->validate($path);
    }

    private function state(CatalogProductData $product, array $errors, string $status, string $checksum): array
    {
        $externalIdIsUsable = $product->externalId !== ''
            && array_intersect(['DUPLICATE_ID', 'DUPLICATE_EXTERNAL_ID'], $errors) === [];
        $stateExternalId = $externalIdIsUsable
            ? $product->externalId
            : 'invalid:product:'.$product->id;

        return [
            'product_id' => $product->id,
            'external_id' => $stateExternalId,
            'checksum' => $checksum,
            'remote_row_id' => null,
            'remote_item_id' => $externalIdIsUsable ? $product->externalId : null,
            'last_status' => $status,
            'last_error_code' => $errors[0] ?? null,
            'last_error_message' => $errors === [] ? null : implode('|', $errors),
        ];
    }

    private function countProductDiagnostics(array &$summary, array $errors): void
    {
        $counters = [
            'PRODUCTS_MISSING_BRAND' => ['BRAND_MISSING'],
            'PRODUCTS_MISSING_DESCRIPTION' => ['DESCRIPTION_MISSING'],
            'PRODUCTS_MISSING_PRICE' => [
                'PRICE_MISSING',
                'PRICE_INVALID',
                'PRICE_BOOK_VALUE_MISSING',
                'PRICE_SOURCE_UNAVAILABLE',
            ],
            'PRODUCTS_MISSING_URL' => ['PRODUCT_URL_MISSING'],
            'PRODUCTS_MISSING_IMAGE' => ['IMAGE_MISSING', 'IMAGE_URL_INVALID'],
        ];

        foreach ($counters as $counter => $codes) {
            if (array_intersect($codes, $errors) !== []) {
                $summary[$counter]++;
            }
        }
    }

    private function persistStates(string $channel, array $states): void
    {
        $now = now();
        foreach (array_chunk($states, max(1, (int) config('catalog.sync_chunk_size', 250))) as $chunk) {
            $rows = array_map(fn (array $state): array => $state + [
                'channel' => $channel,
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);
            CatalogChannelItemState::upsert(
                $rows,
                ['channel', 'product_id'],
                [
                    'external_id', 'checksum', 'remote_row_id', 'remote_item_id', 'last_synced_at',
                    'last_status', 'last_error_code', 'last_error_message', 'updated_at',
                ],
            );
        }
    }

    private function runItem(
        int $runId,
        CatalogProductData $product,
        array $errors,
        string $action,
        string $status,
        string $priceSource,
        bool $dryRun,
    ): array {
        return [
            'sync_run_id' => $runId,
            'product_id' => $product->id,
            'external_id' => $product->externalId,
            'action' => $action,
            'eligibility_status' => match ($status) {
                'ACTIVE' => 'eligible',
                'PARTIAL' => 'partial',
                default => 'invalid',
            },
            'validation_errors_json' => json_encode($errors, JSON_THROW_ON_ERROR),
            'selected_price' => $product->price > 0 ? $product->price : null,
            'price_source' => $priceSource,
            'image_status' => $product->imageStatus,
            'result_status' => $dryRun ? 'dry_run' : 'evaluated',
            'error_code' => $errors[0] ?? null,
            'error_message' => $errors === [] ? null : implode('|', $errors),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function persistRunItems(array $items): void
    {
        foreach (array_chunk($items, max(1, (int) config('catalog.sync_chunk_size', 250))) as $chunk) {
            CatalogChannelSyncRunItem::upsert($chunk, ['sync_run_id', 'product_id'], [
                'external_id', 'action', 'eligibility_status', 'validation_errors_json', 'selected_price',
                'price_source', 'image_status', 'result_status', 'error_code', 'error_message', 'updated_at',
            ]);
        }
    }

    private function completedRun(array $summary): array
    {
        return [
            'status' => 'completed',
            'completed_at' => now(),
            'items_total' => $summary['TOTAL_PRODUCTS'],
            'items_valid' => $summary['VALID_PRODUCTS'],
            'items_invalid' => $summary['INVALID_PRODUCTS'],
            'items_created' => $summary['CREATE_CANDIDATES'],
            'items_updated' => $summary['UPDATE_CANDIDATES'],
            'items_skipped' => $summary['SKIPPED'] + $summary['UNCHANGED'],
            'warnings' => $summary['WARNING_COUNT'],
            'errors' => $summary['ERROR_COUNT'],
            'summary_json' => $summary,
        ];
    }
}
