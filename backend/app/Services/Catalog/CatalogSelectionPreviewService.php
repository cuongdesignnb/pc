<?php

namespace App\Services\Catalog;

use App\Data\Catalog\CatalogProductData;
use App\Models\CatalogChannelConnection;
use App\Models\Category;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CatalogSelectionPreviewService
{
    public function __construct(
        private readonly CatalogProductSelectionService $selection,
        private readonly CatalogProductProjectionService $projection,
        private readonly CatalogChannelEligibilityService $eligibility,
        private readonly CatalogChannelPriceSettingsService $settings,
    ) {}

    public function preview(array $payload): array
    {
        $channel = (string) ($payload['channel'] ?? '');
        $this->assertChannel($channel);
        $selection = $this->normalizeSelection((array) ($payload['selection'] ?? []));
        $source = trim((string) ($payload['price_source'] ?? $this->eligibility->sourceFor($channel)));
        $fallback = trim((string) ($payload['fallback_policy'] ?? $this->eligibility->fallbackFor($channel)));
        $this->validatePricing($channel, $source, $fallback);

        $filters = $this->selection->normalizeFilters((array) ($selection['filters'] ?? []));
        $derivedFilters = in_array($filters['image_status'], ['invalid', 'not_mirrored'], true)
            || $filters['google_eligible'] !== null
            || $filters['meta_eligible'] !== null
            || $filters['validation_error'] !== ''
            || $filters['sync_status'] !== null;
        $selectedCount = $derivedFilters ? 0 : $this->selection->count($selection);
        $items = [];
        $counts = $this->emptyCounts();
        $seenExternal = [];
        $categories = Category::query()->get()->keyBy('id');

        $this->selection->selectionQuery($selection)->chunkById(
            max(1, (int) config('catalog.sync_chunk_size', 250)),
            function (Collection $products) use (&$selectedCount, &$items, &$counts, &$seenExternal, $categories, $channel, $source, $fallback, $filters, $derivedFilters): void {
                $states = $this->selection->states($channel, $products->pluck('id')->map(fn ($id): int => (int) $id)->all());
                foreach ($products as $product) {
                    $projection = $this->projection->project($product, $categories);
                    $selected = $this->eligibility->evaluate($projection, $channel, $source, $fallback);
                    $google = $this->eligibility->evaluate($projection, CatalogChannelConnection::GOOGLE_MERCHANT);
                    $meta = $this->eligibility->evaluate($projection, CatalogChannelConnection::META_CATALOG);
                    $state = $states[$projection->id] ?? null;
                    $externalDuplicate = isset($seenExternal[$projection->externalId]);
                    $seenExternal[$projection->externalId] = true;
                    if (! $this->passesDerivedFilters($projection, $selected, $google, $meta, $state, $filters)) {
                        continue;
                    }
                    if ($derivedFilters) {
                        $selectedCount++;
                    }
                    $errors = $selected['errors'];
                    if ($externalDuplicate) {
                        $errors[] = 'DUPLICATE_EXTERNAL_ID';
                    }
                    $errors = array_values(array_unique($errors));
                    $valid = $errors === [];
                    $eligible = $channel === CatalogChannelConnection::GOOGLE_SHEETS ? true : $valid;
                    $action = $this->action($projection, $state, $eligible, $valid, $channel);
                    $counts['VALID_ROWS'] += $valid ? 1 : 0;
                    $counts['INVALID_ROWS'] += $valid ? 0 : 1;
                    $counts['ELIGIBLE_COUNT'] += $eligible ? 1 : 0;
                    $counts['INVALID_COUNT'] += $valid ? 0 : 1;
                    $counts['IMAGE_MISSING_COUNT'] += in_array($selected['image_status'], ['missing', 'not_mirrored'], true) ? 1 : 0;
                    $counts['PRICE_ZERO_COUNT'] += $selected['price'] === 0 ? 1 : 0;
                    $counts['PRICE_MISSING_COUNT'] += $selected['price'] === null ? 1 : 0;
                    $counts['HIDDEN_COUNT'] += $projection->isVisible ? 0 : 1;
                    $counts['INACTIVE_COUNT'] += $projection->isActive ? 0 : 1;
                    $counts['UNDER_REPAIR_COUNT'] += $projection->isUnderRepair ? 1 : 0;
                    $counts['OUT_OF_STOCK_COUNT'] += $projection->inventory > 0 ? 0 : 1;
                    $counts['DUPLICATE_EXTERNAL_ID_COUNT'] += $externalDuplicate ? 1 : 0;
                    if (isset($counts[$action.'_COUNT'])) {
                        $counts[$action.'_COUNT']++;
                    }
                    if (count($items) < 1000) {
                        $items[] = $this->item($projection, $selected, $google, $meta, $errors, $action, $state);
                    }
                }
            },
            'id',
        );

        $summary = [
            'CHANNEL' => $channel,
            'PRICE_SOURCE' => $source,
            'FALLBACK_POLICY' => $fallback,
            'SELECTION_SCOPE' => $selection['mode'],
            'SELECTED_COUNT' => $selectedCount,
            'ELIGIBLE_COUNT' => $counts['ELIGIBLE_COUNT'],
            'INVALID_COUNT' => $counts['INVALID_COUNT'],
            'IMAGE_MISSING_COUNT' => $counts['IMAGE_MISSING_COUNT'],
            'PRICE_ZERO_COUNT' => $counts['PRICE_ZERO_COUNT'],
            'PRICE_MISSING_COUNT' => $counts['PRICE_MISSING_COUNT'],
            'HIDDEN_COUNT' => $counts['HIDDEN_COUNT'],
            'INACTIVE_COUNT' => $counts['INACTIVE_COUNT'],
            'UNDER_REPAIR_COUNT' => $counts['UNDER_REPAIR_COUNT'],
            'OUT_OF_STOCK_COUNT' => $counts['OUT_OF_STOCK_COUNT'],
            'DUPLICATE_EXTERNAL_ID_COUNT' => $counts['DUPLICATE_EXTERNAL_ID_COUNT'],
            'CREATE_COUNT' => $counts['CREATE_COUNT'],
            'UPDATE_COUNT' => $counts['UPDATE_COUNT'],
            'UNCHANGED_COUNT' => $counts['UNCHANGED_COUNT'],
            'SKIPPED_COUNT' => $counts['SKIP_COUNT'] + $counts['INVALID_COUNT'],
            'VALID_ROWS' => $counts['VALID_ROWS'],
            'INVALID_ROWS' => $counts['INVALID_ROWS'],
            'filter_snapshot' => $this->selection->filtersSummary($selection['filters'] ?? []),
            'excluded_ids_count' => count((array) ($selection['excluded_product_ids'] ?? [])),
        ];

        return [
            'summary' => $summary,
            'items' => $items,
            'preview_token' => hash('sha256', json_encode([$channel, $selection, $source, $fallback, $summary], JSON_THROW_ON_ERROR)),
        ];
    }

    public function normalizeSelection(array $selection): array
    {
        $mode = (string) ($selection['mode'] ?? 'filtered');
        if (! in_array($mode, CatalogProductSelectionService::MODES, true)) {
            throw ValidationException::withMessages(['selection.mode' => 'Selection mode không hợp lệ.']);
        }
        $ids = static function (mixed $value): array {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                fn (mixed $id): ?int => is_numeric($id) && (int) $id > 0 ? (int) $id : null,
                array_slice($value, 0, 10000),
            ))));
        };

        return [
            'mode' => $mode,
            'filters' => (array) ($selection['filters'] ?? []),
            'product_ids' => $ids($selection['product_ids'] ?? []),
            'included_product_ids' => $ids($selection['included_product_ids'] ?? []),
            'excluded_product_ids' => $ids($selection['excluded_product_ids'] ?? []),
        ];
    }

    private function item(CatalogProductData $product, array $selected, array $google, array $meta, array $errors, string $action, mixed $state): array
    {
        return [
            'id' => $product->id,
            'external_id' => $product->externalId,
            'sku' => $product->sku,
            'name' => $product->title,
            'category' => $product->categoryName,
            'image_url' => $product->imageUrl,
            'image_status' => $selected['image_status'],
            'retail_price' => $product->price,
            'selected_price' => $selected['price'],
            'price_source' => $selected['price_source'],
            'stock' => $product->inventory,
            'repair_status' => $product->isUnderRepair ? 'repairing' : 'ready',
            'is_visible' => $product->isVisible,
            'is_active' => $product->isActive,
            'google_eligible' => $google['eligible'],
            'meta_eligible' => $meta['eligible'],
            'eligible' => $selected['eligible'],
            'validation_errors' => $errors,
            'last_sync' => $state?->last_synced_at,
            'action' => $action,
        ];
    }

    private function action(CatalogProductData $product, mixed $state, bool $eligible, bool $valid, string $channel): string
    {
        if (! $eligible) {
            return 'INVALID';
        }
        if (! $valid && $channel !== CatalogChannelConnection::GOOGLE_SHEETS) {
            return 'INVALID';
        }
        if (! $state) {
            return 'CREATE';
        }
        if ((string) $state->checksum === $product->checksum) {
            return 'UNCHANGED';
        }

        return 'UPDATE';
    }

    private function emptyCounts(): array
    {
        return array_fill_keys([
            'ELIGIBLE_COUNT', 'INVALID_COUNT', 'IMAGE_MISSING_COUNT', 'PRICE_ZERO_COUNT', 'PRICE_MISSING_COUNT',
            'HIDDEN_COUNT', 'INACTIVE_COUNT', 'UNDER_REPAIR_COUNT', 'OUT_OF_STOCK_COUNT', 'DUPLICATE_EXTERNAL_ID_COUNT',
            'CREATE_COUNT', 'UPDATE_COUNT', 'UNCHANGED_COUNT', 'SKIP_COUNT', 'VALID_ROWS', 'INVALID_ROWS',
        ], 0);
    }

    private function passesDerivedFilters(CatalogProductData $product, array $selected, array $google, array $meta, mixed $state, array $filters): bool
    {
        if ($filters['image_status'] === 'invalid' && $selected['image_status'] !== 'invalid') {
            return false;
        }
        if ($filters['image_status'] === 'not_mirrored' && $selected['image_status'] !== 'not_mirrored') {
            return false;
        }
        if ($filters['google_eligible'] !== null && $google['eligible'] !== $filters['google_eligible']) {
            return false;
        }
        if ($filters['meta_eligible'] !== null && $meta['eligible'] !== $filters['meta_eligible']) {
            return false;
        }
        if ($filters['validation_error'] !== '' && ! in_array($filters['validation_error'], $selected['errors'], true)) {
            return false;
        }
        if ($filters['sync_status'] === 'synced' && ! $state) {
            return false;
        }
        if ($filters['sync_status'] === 'not_synced' && $state) {
            return false;
        }

        return true;
    }

    private function validatePricing(string $channel, string $source, string $fallback): void
    {
        $this->settings->validateFallback($fallback);
        if ($channel === CatalogChannelConnection::GOOGLE_SHEETS && $fallback !== 'none') {
            throw ValidationException::withMessages(['fallback_policy' => 'Google Sheets không hỗ trợ fallback trong cột đã chọn.']);
        }
        $bookId = preg_match('/^price_book:(\d+)$/', $source, $matches) === 1 ? (int) $matches[1] : null;
        if (app(\App\Services\Catalog\Pricing\CatalogPriceValidationService::class)->validateSource($source, $bookId) !== []) {
            throw ValidationException::withMessages(['price_source' => 'PRICE_SOURCE_UNAVAILABLE']);
        }
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, [CatalogChannelConnection::GOOGLE_SHEETS, CatalogChannelConnection::GOOGLE_MERCHANT, CatalogChannelConnection::META_CATALOG], true)) {
            throw ValidationException::withMessages(['channel' => 'Catalog channel không hỗ trợ preview.']);
        }
    }
}
