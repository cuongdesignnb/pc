<?php

namespace App\Services\Catalog\GoogleSheets;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelItemState;
use App\Models\CatalogChannelSyncConflict;
use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogPriceBook;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\CatalogProductValidator;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GoogleSheetsExporter
{
    public const HEADERS = [
        'external_id', 'sku', 'name', 'category', 'brand', 'price', 'currency', 'inventory',
        'availability', 'is_visible', 'is_active', 'is_under_repair', 'product_url', 'image_url',
        'validation_status', 'validation_errors', 'provider_updated_at', 'synced_at', 'checksum',
        'condition', 'stock_quantity', 'available_quantity', 'repair_status', 'retail_price',
        'selected_website_price', 'selected_google_price', 'selected_meta_price', 'google_eligible',
        'meta_eligible', 'last_synced_at',
    ];

    public function __construct(
        private readonly GoogleSheetsClient $client,
        private readonly CatalogProductProjectionService $projection,
        private readonly CatalogProductValidator $validator,
        private readonly CatalogChannelManager $channels,
    ) {}

    public function dryRun(?int $requestedBy = null): array
    {
        return $this->run(true, $requestedBy);
    }

    public function sync(?int $requestedBy = null): array
    {
        return $this->run(false, $requestedBy);
    }

    private function run(bool $dryRun, ?int $requestedBy): array
    {
        $connection = $this->channels->connection(CatalogChannelConnection::GOOGLE_SHEETS);
        if (! $dryRun && ! $connection->is_enabled) {
            throw new CatalogChannelException('CHANNEL_DISABLED', 'Google Sheets channel đang tắt.');
        }

        $lock = Cache::lock('catalog:google-sheets:sync', (int) config('catalog.sync_lock_seconds', 1800));
        if (! $lock->get()) {
            throw new CatalogChannelException('SYNC_ALREADY_RUNNING', 'Một lần đồng bộ Google Sheets khác đang chạy.', 409);
        }

        $run = CatalogChannelSyncRun::create([
            'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
            'mode' => $dryRun ? 'dry_run' : 'sync',
            'status' => 'running',
            'started_at' => now(),
            'requested_by' => $requestedBy,
        ]);

        try {
            $configuration = (array) $connection->configuration_encrypted;
            $existingRows = $dryRun && ! $this->remoteReadConfigured($configuration)
                ? []
                : $this->client->readRows($configuration);
            $report = $this->prepare($existingRows, $configuration, $dryRun);
            if (! $dryRun) {
                $this->client->writeRows($configuration, $report['ranges']);
                $this->persistStates($report['states']);
                $connection->update([
                    'status' => 'connected',
                    'last_success_at' => now(),
                    'last_error_at' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                ]);
            }

            $summary = $report['summary'];
            $run->update($this->completedRun($summary));

            return $summary + ['run_id' => $run->id];
        } catch (Throwable $exception) {
            $code = $exception instanceof CatalogChannelException ? $exception->errorCode : 'GOOGLE_WRITE_FAILED';
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_code' => $code,
                'error_message' => 'Catalog channel operation failed.',
            ]);
            $connection->update([
                'status' => 'error',
                'last_error_at' => now(),
                'last_error_code' => $code,
                'last_error_message' => 'Google Sheets operation failed.',
            ]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function prepare(array $existingRows, array $configuration, bool $dryRun): array
    {
        $headers = $this->headers();
        $existingHeaders = isset($existingRows[0]) ? array_map('strval', $existingRows[0]) : [];
        $headerMatches = $existingHeaders === $headers;
        $headerIndex = array_flip($headers);
        $existing = [];
        $existingSkus = [];
        $duplicateSheetIds = [];
        if ($headerMatches) {
            foreach (array_slice($existingRows, 1, null, true) as $offset => $row) {
                $externalId = trim((string) ($row[$headerIndex['external_id']] ?? ''));
                if ($externalId === '') {
                    continue;
                }
                if (isset($existing[$externalId])) {
                    $duplicateSheetIds[$externalId] = true;

                    continue;
                }
                $existing[$externalId] = ['row' => $offset + 1, 'checksum' => (string) ($row[$headerIndex['checksum']] ?? '')];
                $sheetSku = mb_strtolower(trim((string) ($row[$headerIndex['sku']] ?? '')));
                if ($sheetSku !== '') {
                    $existingSkus[$sheetSku] = $externalId;
                }
            }
        }

        $ranges = $headerMatches ? [] : [[
            'range' => $this->client->rowRange($configuration, 1),
            'majorDimension' => 'ROWS',
            'values' => [$headers],
        ]];
        $nextRow = $headerMatches ? max(2, count($existingRows) + 1) : 2;
        $seenExternal = [];
        $seenSku = [];
        $states = [];
        $counts = [
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

        $this->projection->each(function (CatalogProductData $product) use (
            &$counts, &$ranges, &$nextRow, &$seenExternal, &$seenSku, &$states,
            $existing, $existingSkus, $duplicateSheetIds, $configuration, $dryRun, $headers,
        ): void {
            $counts['TOTAL_PRODUCTS']++;
            $validation = $this->validator->validate($product);
            $errors = $validation->errors;
            foreach ($product->priceIssues as $issue) {
                if ($issue !== null) {
                    $errors[] = $issue;
                }
            }
            $skuKey = mb_strtolower($product->sku);
            if (isset($seenExternal[$product->externalId]) || isset($duplicateSheetIds[$product->externalId])) {
                $errors[] = 'DUPLICATE_EXTERNAL_ID';
            }
            if ($skuKey !== '' && isset($seenSku[$skuKey])) {
                $errors[] = 'DUPLICATE_SKU';
            }
            if ($skuKey !== '' && isset($existingSkus[$skuKey]) && $existingSkus[$skuKey] !== $product->externalId) {
                $errors[] = 'DUPLICATE_SKU';
            }
            $seenExternal[$product->externalId] = true;
            if ($skuKey !== '') {
                $seenSku[$skuKey] = true;
            }

            $errors = array_values(array_unique($errors));
            foreach ($errors as $error) {
                $counts['ERRORS_BY_CODE'][$error] = ($counts['ERRORS_BY_CODE'][$error] ?? 0) + 1;
            }
            $counts['ERROR_COUNT'] += count($errors);
            $counts['WARNING_COUNT'] += count($validation->warnings);
            $errors === [] ? $counts['VALID_PRODUCTS']++ : $counts['INVALID_PRODUCTS']++;

            $current = $existing[$product->externalId] ?? null;
            if ($current && hash_equals((string) $current['checksum'], $product->checksum)) {
                $counts['UNCHANGED']++;

                return;
            }
            if (array_intersect(['DUPLICATE_EXTERNAL_ID', 'DUPLICATE_SKU'], $errors) !== []) {
                $counts['SKIPPED']++;
                if (! $dryRun) {
                    CatalogChannelSyncConflict::firstOrCreate([
                        'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
                        'product_id' => $product->id,
                        'external_id' => $product->externalId,
                        'conflict_type' => in_array('DUPLICATE_EXTERNAL_ID', $errors, true)
                            ? 'DUPLICATE_EXTERNAL_ID'
                            : 'DUPLICATE_SKU',
                        'status' => 'open',
                    ], ['details_json' => ['safe' => true]]);
                }

                return;
            }

            $rowNumber = $current ? (int) $current['row'] : $nextRow++;
            $current ? $counts['UPDATE_CANDIDATES']++ : $counts['CREATE_CANDIDATES']++;
            $ranges[] = [
                'range' => $this->client->rowRange($configuration, $rowNumber),
                'majorDimension' => 'ROWS',
                'values' => [$this->row($product, $validation->status($product), $errors, $headers)],
            ];
            $states[] = [
                'product_id' => $product->id,
                'external_id' => $product->externalId,
                'checksum' => $product->checksum,
                'remote_row_id' => (string) $rowNumber,
                'remote_item_id' => null,
                'last_status' => $validation->status($product),
                'last_error_code' => $errors[0] ?? null,
                'last_error_message' => $errors === [] ? null : implode('|', $errors),
            ];
        });

        return ['summary' => $counts, 'ranges' => $ranges, 'states' => $states];
    }

    private function row(CatalogProductData $product, string $status, array $errors, array $headers): array
    {
        $google = $product->withPrice($product->priceFor('google_merchant'));
        $meta = $product->withPrice($product->priceFor('meta_catalog'));
        $googleEligible = $this->validator->validate($google)->valid;
        $metaEligible = $this->validator->validate($meta)->valid;
        $values = [
            'external_id' => $this->safeText($product->externalId),
            'sku' => $this->safeText($product->sku),
            'name' => $this->safeText($product->title),
            'category' => $this->safeText($product->categoryPath),
            'brand' => $this->safeText($product->brand),
            'price' => $product->price,
            'inventory' => $product->inventory,
            'availability' => $product->availability,
            'is_under_repair' => $product->isUnderRepair,
            'condition' => $product->condition,
            'stock_quantity' => $product->inventory,
            'available_quantity' => $product->inventory,
            'repair_status' => $product->isUnderRepair ? 'repairing' : 'ready',
            'is_active' => $product->isActive,
            'is_visible' => $product->isVisible,
            'retail_price' => $product->price,
            'selected_website_price' => $product->priceFor('website'),
            'selected_google_price' => $product->priceFor('google_merchant'),
            'selected_meta_price' => $product->priceFor('meta_catalog'),
            'currency' => $product->currency,
            'product_url' => $this->safeText($product->productUrl),
            'image_url' => $this->safeText($product->imageUrl),
            'google_eligible' => $googleEligible,
            'meta_eligible' => $metaEligible,
            'validation_status' => $status,
            'validation_errors' => implode('|', $errors),
            'provider_updated_at' => $product->updatedAt->toIso8601String(),
            'synced_at' => now()->toIso8601String(),
            'last_synced_at' => now()->toIso8601String(),
            'checksum' => $product->checksum,
        ];
        foreach ($product->priceBooks as $bookId => $price) {
            $values['price_book_'.$bookId] = $price;
        }

        return array_map(fn (string $header) => $values[$header] ?? '', $headers);
    }

    private function headers(): array
    {
        $headers = self::HEADERS;
        foreach (CatalogPriceBook::query()->where('provider', 'kiot')->orderBy('id')->get(['id', 'name']) as $book) {
            $headers[] = 'price_book_'.$book->id;
        }

        return $headers;
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function persistStates(array $states): void
    {
        $now = now();
        foreach (array_chunk($states, max(1, (int) config('catalog.sync_chunk_size', 250))) as $chunk) {
            $rows = array_map(fn (array $state): array => $state + [
                'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
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

    private function remoteReadConfigured(array $configuration): bool
    {
        $credentials = $configuration['service_account'] ?? null;

        return is_array($credentials)
            && filled($credentials['client_email'] ?? null)
            && filled($credentials['private_key'] ?? null)
            && filled($configuration['spreadsheet_id'] ?? null);
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
