<?php

namespace App\Services\Catalog\GoogleSheets;

use App\Data\Catalog\CatalogProductData;
use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelItemState;
use App\Models\CatalogChannelSyncConflict;
use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogGoogleSheetPriceColumn;
use App\Models\Category;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\CatalogProductSelectionService;
use App\Services\Catalog\CatalogProductValidator;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GoogleSheetsExporter
{
    private const HEADER_LABELS = [
        'external_id' => 'Mã ngoài',
        'sku' => 'SKU',
        'name' => 'Tên sản phẩm',
        'category' => 'Danh mục',
        'brand' => 'Thương hiệu',
        'price' => 'Giá',
        'currency' => 'Tiền tệ',
        'inventory' => 'Tồn kho',
        'availability' => 'Tình trạng tồn kho',
        'is_visible' => 'Hiển thị',
        'is_active' => 'Đang hoạt động',
        'is_under_repair' => 'Đang sửa chữa',
        'product_url' => 'URL sản phẩm',
        'image_url' => 'URL ảnh',
        'validation_status' => 'Trạng thái kiểm tra',
        'validation_errors' => 'Lỗi kiểm tra',
        'provider_updated_at' => 'Cập nhật từ KIOT',
        'synced_at' => 'Đồng bộ lúc',
        'checksum' => 'Mã kiểm tra',
        'condition' => 'Tình trạng sản phẩm',
        'stock_quantity' => 'Số lượng tồn',
        'available_quantity' => 'Số lượng khả dụng',
        'repair_status' => 'Trạng thái sửa chữa',
        'retail_price' => 'Giá bán lẻ',
        'selected_website_price' => 'Giá Website đã chọn',
        'selected_google_price' => 'Giá Google đã chọn',
        'selected_meta_price' => 'Giá Meta đã chọn',
        'google_eligible' => 'Đủ điều kiện Google',
        'meta_eligible' => 'Đủ điều kiện Meta',
        'last_synced_at' => 'Lần đồng bộ gần nhất',
    ];

    private const VALUE_LABELS = [
        'in_stock' => 'Còn hàng',
        'out_of_stock' => 'Hết hàng',
        'repairing' => 'Đang sửa chữa',
        'ready' => 'Sẵn sàng',
        'VALID' => 'Hợp lệ',
        'ACTIVE' => 'Đang hoạt động',
        'INVALID' => 'Không hợp lệ',
        'DELETED' => 'Đã xóa',
        'HIDDEN' => 'Đang ẩn',
        'CREATE' => 'Tạo mới',
        'UPDATE' => 'Cập nhật',
        'UNCHANGED' => 'Không thay đổi',
        'SKIP' => 'Bỏ qua',
    ];

    private const ERROR_LABELS = [
        'PRODUCT_DELETED' => 'Sản phẩm đã bị xóa',
        'PRODUCT_INACTIVE' => 'Sản phẩm đang ngừng hoạt động',
        'PRODUCT_HIDDEN' => 'Sản phẩm đang ẩn',
        'CATEGORY_HIDDEN' => 'Danh mục đang ẩn',
        'PRICE_MISSING' => 'Chưa có giá',
        'PRICE_ZERO' => 'Giá bằng 0',
        'IMAGE_MISSING' => 'Thiếu ảnh sản phẩm',
        'IMAGE_INVALID' => 'URL ảnh không hợp lệ',
        'IMAGE_URL_INVALID' => 'URL ảnh không hợp lệ',
        'SKU_MISSING' => 'Thiếu SKU',
        'TITLE_MISSING' => 'Thiếu tên sản phẩm',
        'PRODUCT_URL_MISSING' => 'Thiếu URL sản phẩm',
        'PRICE_SOURCE_UNAVAILABLE' => 'Nguồn giá không khả dụng',
        'PRICE_BOOK_VALUE_MISSING' => 'Chưa có giá trong bảng giá',
        'OUT_OF_STOCK' => 'Hết hàng',
        'PRICE_FALLBACK_USED' => 'Đã dùng giá dự phòng',
        'UNDER_REPAIR' => 'Sản phẩm đang sửa chữa',
        'DUPLICATE_EXTERNAL_ID' => 'Trùng mã ngoài',
        'DUPLICATE_SKU' => 'Trùng SKU',
    ];

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
        private readonly CatalogProductSelectionService $selection,
        private readonly CatalogChannelManager $channels,
        private readonly CatalogChannelPriceSettingsService $priceSettings,
    ) {}

    public function dryRun(?int $requestedBy = null): array
    {
        return $this->run(true, $requestedBy);
    }

    public function sync(?int $requestedBy = null): array
    {
        return $this->run(false, $requestedBy);
    }

    public function syncSelection(array $selection, ?int $requestedBy = null): array
    {
        return $this->run(false, $requestedBy, $selection);
    }

    private function run(bool $dryRun, ?int $requestedBy, ?array $selection = null): array
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
            'mode' => $selection !== null ? 'bulk_sync' : ($dryRun ? 'dry_run' : 'sync'),
            'status' => 'running',
            'started_at' => now(),
            'requested_by' => $requestedBy,
        ]);

        try {
            $configuration = (array) $connection->configuration_encrypted;
            $selectedColumns = $this->priceSettings->googleSheetsColumns();
            $columnCount = count($this->headers($selectedColumns));
            $existingRows = $dryRun && ! $this->remoteReadConfigured($configuration)
                ? []
                : $this->client->readRows($configuration, $columnCount);
            $report = $this->prepare($existingRows, $configuration, $dryRun, $columnCount, $selection);
            if (! $dryRun) {
                $this->client->writeRows($configuration, $report['ranges'], $columnCount);
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

    private function prepare(
        array $existingRows,
        array $configuration,
        bool $dryRun,
        int $columnCount,
        ?array $selection = null,
    ): array {
        $selectedColumns = $this->priceSettings->googleSheetsColumns();
        $headers = $this->headers($selectedColumns);
        $displayHeaders = $this->displayHeaders($headers, $selectedColumns);
        $existingHeaders = isset($existingRows[0]) ? array_map('strval', $existingRows[0]) : [];
        $headerIndex = $this->existingHeaderIndex($existingHeaders, $headers, $displayHeaders);
        $existing = [];
        $existingSkus = [];
        $duplicateSheetIds = [];
        if ($headerIndex !== null) {
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

        $ranges = $existingHeaders === $displayHeaders ? [] : [[
            'range' => $this->client->rowRange($configuration, 1, $columnCount),
            'majorDimension' => 'ROWS',
            'values' => [$displayHeaders],
        ]];
        if ($headerIndex !== null && $existingHeaders !== $displayHeaders) {
            foreach (array_slice($existingRows, 1, null, true) as $offset => $row) {
                if (trim((string) ($row[$headerIndex['external_id']] ?? '')) === '') {
                    continue;
                }
                $ranges[] = [
                    'range' => $this->client->rowRange($configuration, $offset + 1, $columnCount),
                    'majorDimension' => 'ROWS',
                    'values' => [array_map(
                        fn (string $header): string|int|null => $this->sheetValue($header, $row[$headerIndex[$header]] ?? ''),
                        $headers,
                    )],
                ];
            }
        }
        $nextRow = $headerIndex !== null ? max(2, count($existingRows) + 1) : 2;
        $seenExternal = [];
        $seenSku = [];
        $states = [];
        $counts = [
            'SELECTED_PRICE_COLUMNS' => $selectedColumns->pluck('column_key')->values()->all(),
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

        $consume = function (CatalogProductData $product) use (
            $columnCount, &$counts, &$ranges, &$nextRow, &$seenExternal, &$seenSku, &$states,
            $existing, $existingSkus, $duplicateSheetIds, $configuration, $dryRun, $headers, $selectedColumns,
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
                'range' => $this->client->rowRange($configuration, $rowNumber, $columnCount),
                'majorDimension' => 'ROWS',
                'values' => [$this->row($product, $validation->status($product), $errors, $headers, $selectedColumns)],
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
        };

        if ($selection === null) {
            $this->projection->each($consume);
        } else {
            $categories = Category::query()->get([
                'id', 'parent_id', 'name', 'slug', 'is_active', 'provider',
                'show_on_pc_website', 'provider_sync_status',
            ])->keyBy('id');
            foreach ($this->selection->stream($selection) as $product) {
                $consume($this->projection->project($product, $categories));
            }
        }

        return ['summary' => $counts, 'ranges' => $ranges, 'states' => $states];
    }

    /** @param Collection<int, CatalogGoogleSheetPriceColumn> $selectedColumns */
    private function row(
        CatalogProductData $product,
        string $status,
        array $errors,
        array $headers,
        Collection $selectedColumns,
    ): array {
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
        foreach ($selectedColumns as $column) {
            $values[$column->column_key] = $this->valueForSource($product, $column->price_source, $column->price_book_id);
        }

        return array_map(
            fn (string $header): string|int|null => $this->sheetValue($header, $values[$header] ?? ''),
            $headers,
        );
    }

    /** @param Collection<int, CatalogGoogleSheetPriceColumn> $selectedColumns */
    private function headers(Collection $selectedColumns): array
    {
        $headers = self::HEADERS;
        foreach ($selectedColumns as $column) {
            if (! in_array($column->column_key, $headers, true)) {
                $headers[] = $column->column_key;
            }
        }

        return $headers;
    }

    /** @param list<string> $headers */
    private function displayHeaders(array $headers, Collection $selectedColumns): array
    {
        $dynamic = $selectedColumns->keyBy('column_key');

        return array_map(function (string $header) use ($dynamic): string {
            $column = $dynamic->get($header);
            if ($column !== null) {
                return $this->priceColumnLabel($column);
            }

            return self::HEADER_LABELS[$header] ?? $header;
        }, $headers);
    }

    /** @param list<string> $existingHeaders @param list<string> $headers @param list<string> $displayHeaders */
    private function existingHeaderIndex(array $existingHeaders, array $headers, array $displayHeaders): ?array
    {
        if ($existingHeaders === []) {
            return null;
        }

        $index = [];
        foreach ($headers as $position => $header) {
            $candidate = array_search($header, $existingHeaders, true);
            if ($candidate === false) {
                $candidate = array_search($displayHeaders[$position] ?? '', $existingHeaders, true);
            }
            if ($candidate === false) {
                return null;
            }
            $index[$header] = $candidate;
        }

        return $index;
    }

    private function priceColumnLabel(CatalogGoogleSheetPriceColumn $column): string
    {
        return match ($column->price_source) {
            'retail_price' => 'Giá bán lẻ',
            'selected_price' => 'Giá đã chọn',
            default => 'Giá bảng: '.(string) ($column->priceBook?->name ?: $column->column_label),
        };
    }

    private function sheetValue(string $header, mixed $value): string|int|null
    {
        if (in_array($header, ['is_visible', 'is_active', 'is_under_repair', 'google_eligible', 'meta_eligible'], true)) {
            $boolean = is_string($value)
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : (bool) $value;

            return $boolean === true ? 'Có' : 'Không';
        }
        if ($header === 'validation_errors') {
            $errors = array_filter(explode('|', (string) $value));

            return implode(', ', array_map(fn (string $error): string => self::ERROR_LABELS[$error] ?? $error, $errors));
        }
        if (in_array($header, ['availability', 'repair_status', 'validation_status'], true)) {
            return self::VALUE_LABELS[(string) $value] ?? (string) $value;
        }
        if ($header === 'condition' && (string) $value === 'new') {
            return 'Mới';
        }

        return $value;
    }

    private function valueForSource(CatalogProductData $product, string $source, ?int $priceBookId): ?int
    {
        return match ($source) {
            'retail_price' => $product->price,
            'selected_price' => $product->selectedPrice,
            default => str_starts_with($source, 'price_book:') && $priceBookId !== null
                ? data_get($product->priceBooks, $priceBookId)
                : null,
        };
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
