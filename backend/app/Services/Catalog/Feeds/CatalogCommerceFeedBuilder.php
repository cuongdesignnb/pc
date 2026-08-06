<?php

namespace App\Services\Catalog\Feeds;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelItemState;
use App\Models\CatalogChannelSyncConflict;
use App\Models\CatalogChannelSyncRun;
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
            'VALID_PRODUCTS' => 0,
            'INVALID_PRODUCTS' => 0,
            'CREATE_CANDIDATES' => 0,
            'UPDATE_CANDIDATES' => 0,
            'UNCHANGED' => 0,
            'SKIPPED' => 0,
            'WARNING_COUNT' => 0,
            'ERROR_COUNT' => 0,
            'ERRORS_BY_CODE' => [],
        ];
        $states = [];
        $seen = [];
        $existingStates = CatalogChannelItemState::where('channel', $channel)->get()->keyBy('product_id');

        try {
            $products = function () use (&$summary, &$states, &$seen, $channel, $existingStates): \Generator {
                foreach ($this->projection->projected() as [$product]) {
                    $resolvedPrice = $this->prices->resolveData($product, $channel);
                    $product = $product->withPrice($resolvedPrice['value']);
                    $summary['TOTAL_PRODUCTS']++;
                    $validation = $this->validator->validate($product);
                    $errors = $validation->errors;
                    if ($resolvedPrice['issue']) {
                        $errors[] = $resolvedPrice['issue'];
                    }
                    if (isset($seen[$product->externalId])) {
                        $errors[] = 'DUPLICATE_EXTERNAL_ID';
                    }
                    $seen[$product->externalId] = true;
                    $errors = array_values(array_unique($errors));
                    foreach ($errors as $error) {
                        $summary['ERRORS_BY_CODE'][$error] = ($summary['ERRORS_BY_CODE'][$error] ?? 0) + 1;
                    }
                    $summary['ERROR_COUNT'] += count($errors);
                    $summary['WARNING_COUNT'] += count($validation->warnings);
                    $valid = $errors === [];
                    $valid ? $summary['VALID_PRODUCTS']++ : $summary['INVALID_PRODUCTS']++;
                    $existing = $existingStates->get($product->id);
                    if ($valid) {
                        if ($existing?->checksum === $product->checksum) {
                            $summary['UNCHANGED']++;
                        } elseif ($existing) {
                            $summary['UPDATE_CANDIDATES']++;
                        } else {
                            $summary['CREATE_CANDIDATES']++;
                        }
                    } else {
                        $summary['SKIPPED']++;
                    }
                    $states[] = $this->state($product, $errors, $valid ? 'ACTIVE' : 'INVALID');

                    if (in_array('DUPLICATE_EXTERNAL_ID', $errors, true)) {
                        CatalogChannelSyncConflict::firstOrCreate([
                            'channel' => $channel,
                            'product_id' => $product->id,
                            'external_id' => $product->externalId,
                            'conflict_type' => 'DUPLICATE_EXTERNAL_ID',
                            'status' => 'open',
                        ], ['details_json' => ['safe' => true]]);
                    }

                    if ($valid) {
                        yield $product;
                    }
                }
            };

            $renderer->render($products(), $temporaryPath);
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

    private function state(CatalogProductData $product, array $errors, string $status): array
    {
        return [
            'product_id' => $product->id,
            'external_id' => $product->externalId,
            'checksum' => $product->checksum,
            'remote_row_id' => null,
            'remote_item_id' => $product->externalId,
            'last_status' => $status,
            'last_error_code' => $errors[0] ?? null,
            'last_error_message' => $errors === [] ? null : implode('|', $errors),
        ];
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
