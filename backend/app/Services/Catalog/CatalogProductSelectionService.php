<?php

namespace App\Services\Catalog;

use App\Models\CatalogChannelItemState;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Builds bounded, repeatable product selections for catalog operations.
 * Selection mode is kept as a filter snapshot instead of expanding every id
 * in the browser when an administrator selects all filtered products.
 */
class CatalogProductSelectionService
{
    public const MODES = ['page', 'ids', 'filtered'];

    public function __construct(private readonly CatalogProductProjectionService $projection) {}

    public function query(array $filters = []): Builder
    {
        $query = $this->projection->query();
        $filters = $this->normalizeFilters($filters);

        if ($filters['keyword'] !== '') {
            $keyword = '%'.$filters['keyword'].'%';
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('sku', 'like', $keyword)
                    ->orWhere('name', 'like', $keyword);
            });
        }
        if ($filters['sku'] !== '') {
            $query->where('sku', 'like', '%'.$filters['sku'].'%');
        }
        if ($filters['name'] !== '') {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }
        if ($filters['category_id'] !== null) {
            $query->where('category_id', $filters['category_id']);
        }
        if ($filters['brand_id'] !== null) {
            $query->where('brand_id', $filters['brand_id']);
        }
        if ($filters['price_status'] === 'positive') {
            $query->where('price', '>', 0);
        } elseif ($filters['price_status'] === 'zero') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('price')->orWhere('price', '<=', 0);
            });
        }
        if ($filters['under_repair'] !== null) {
            $query->where('kiot_is_under_repair', $filters['under_repair']);
        }
        if ($filters['stock_status'] === 'in_stock') {
            $query->where('kiot_available_quantity', '>', 0);
        } elseif ($filters['stock_status'] === 'out_of_stock') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('kiot_available_quantity')->orWhere('kiot_available_quantity', '<=', 0);
            });
        }
        if ($filters['visibility'] === 'visible') {
            $query->where('is_active', true)->where('show_on_pc_website', true)->where('kiot_sync_status', 'active');
        } elseif ($filters['visibility'] === 'hidden') {
            $query->where(function (Builder $builder): void {
                $builder->where('is_active', false)
                    ->orWhere('show_on_pc_website', false)
                    ->orWhere('kiot_sync_status', '!=', 'active')
                    ->orWhereNull('kiot_sync_status');
            });
        }
        if ($filters['image_status'] === 'has_image') {
            $query->whereHas('images', fn (Builder $builder) => $builder->whereNotNull('url')->where('url', '!=', ''));
        } elseif ($filters['image_status'] === 'missing') {
            $query->whereDoesntHave('images', fn (Builder $builder) => $builder->whereNotNull('url')->where('url', '!=', ''));
        }
        if ($filters['price_book_id'] !== null) {
            $query->whereHas('catalogPrices', function (Builder $builder) use ($filters): void {
                $builder->where('price_book_id', $filters['price_book_id']);
                if ($filters['price_book_status'] === 'positive') {
                    $builder->where('price', '>', 0);
                } elseif ($filters['price_book_status'] === 'zero_or_missing') {
                    $builder->where(function (Builder $nested): void {
                        $nested->whereNull('price')->orWhere('price', '<=', 0);
                    });
                }
            });
            if ($filters['price_book_status'] === 'missing') {
                $query->whereDoesntHave('catalogPrices', fn (Builder $builder) => $builder->where('price_book_id', $filters['price_book_id']));
            }
        }
        if ($filters['updated_since'] !== null) {
            $query->where('updated_at', '>=', $filters['updated_since']);
        }

        return $query->orderBy('id');
    }

    public function selectionQuery(array $selection): Builder
    {
        $mode = (string) ($selection['mode'] ?? 'filtered');
        $query = $mode === 'filtered'
            ? $this->query((array) ($selection['filters'] ?? []))
            : $this->query();

        if (in_array($mode, ['page', 'ids'], true)) {
            $ids = $this->ids($selection['product_ids'] ?? $selection['included_product_ids'] ?? []);
            $query->whereIntegerInRaw('products.id', $ids ?: [0]);
        }
        if ($mode === 'filtered') {
            $excluded = $this->ids($selection['excluded_product_ids'] ?? []);
            if ($excluded !== []) {
                $query->whereNotIn('products.id', $excluded);
            }
            $included = $this->ids($selection['included_product_ids'] ?? []);
            if ($included !== []) {
                $query->whereIntegerInRaw('products.id', $included);
            }
        }

        return $query;
    }

    public function stream(array $selection): LazyCollection
    {
        return LazyCollection::make(function () use ($selection): \Generator {
            foreach ($this->selectionQuery($selection)->lazyById((int) config('catalog.sync_chunk_size', 250), 'id') as $product) {
                yield $product;
            }
        });
    }

    /** @return array{products:\Illuminate\Support\Collection,next_cursor:?int} */
    public function page(array $selection, int $perPage = 25, ?int $cursor = null): array
    {
        $perPage = min(100, max(1, $perPage));
        $query = $this->selectionQuery($selection);
        if ($cursor !== null && $cursor > 0) {
            $query->where('products.id', '>', $cursor);
        }
        $products = $query->limit($perPage + 1)->get();
        $next = $products->count() > $perPage ? (int) $products->get($perPage - 1)->id : null;

        return ['products' => $products->take($perPage)->values(), 'next_cursor' => $next];
    }

    public function count(array $selection): int
    {
        return (clone $this->selectionQuery($selection))->toBase()->getCountForPagination();
    }

    public function states(string $channel, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return CatalogChannelItemState::query()
            ->where('channel', $channel)
            ->whereIntegerInRaw('product_id', $productIds)
            ->get()
            ->keyBy('product_id')
            ->all();
    }

    public function normalizeFilters(array $filters): array
    {
        $boolean = static function (mixed $value): ?bool {
            if ($value === null || $value === '') {
                return null;
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        return [
            'keyword' => trim((string) ($filters['keyword'] ?? $filters['q'] ?? '')),
            'sku' => trim((string) ($filters['sku'] ?? '')),
            'name' => trim((string) ($filters['name'] ?? '')),
            'category_id' => $this->positiveInt($filters['category_id'] ?? null),
            'brand_id' => $this->positiveInt($filters['brand_id'] ?? null),
            'image_status' => in_array(($filters['image_status'] ?? ''), ['has_image', 'missing', 'invalid', 'not_mirrored'], true)
                ? $filters['image_status'] : null,
            'price_status' => in_array(($filters['price_status'] ?? ''), ['positive', 'zero'], true)
                ? $filters['price_status'] : null,
            'price_book_id' => $this->positiveInt($filters['price_book_id'] ?? null),
            'price_book_status' => in_array(($filters['price_book_status'] ?? ''), ['positive', 'zero_or_missing', 'missing'], true)
                ? $filters['price_book_status'] : null,
            'under_repair' => $boolean($filters['under_repair'] ?? null),
            'stock_status' => in_array(($filters['stock_status'] ?? ''), ['in_stock', 'out_of_stock'], true)
                ? $filters['stock_status'] : null,
            'visibility' => in_array(($filters['visibility'] ?? ''), ['visible', 'hidden'], true)
                ? $filters['visibility'] : null,
            'updated_since' => filled($filters['updated_since'] ?? null) ? $filters['updated_since'] : null,
            'google_eligible' => $boolean($filters['google_eligible'] ?? null),
            'meta_eligible' => $boolean($filters['meta_eligible'] ?? null),
            'validation_error' => trim((string) ($filters['validation_error'] ?? '')),
            'sync_status' => in_array(($filters['sync_status'] ?? ''), ['synced', 'not_synced'], true)
                ? $filters['sync_status'] : null,
        ];
    }

    public function filtersSummary(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        return array_filter($normalized, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function ids(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $id): ?int => $this->positiveInt($id),
            array_slice($ids, 0, 10000),
        ))));
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
