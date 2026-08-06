<?php

namespace App\Services\Catalog;

use App\Data\Catalog\CatalogProductData;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\Pricing\CatalogChannelPriceResolver;
use App\Services\Catalog\Pricing\CatalogPriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogProductProjectionService
{
    public function __construct(
        private readonly CatalogProductChecksum $checksum,
        private readonly CatalogPriceResolver $prices,
        private readonly CatalogChannelPriceResolver $channelPrices,
    ) {}

    public function query(): Builder
    {
        return Product::query()
            ->withTrashed()
            ->where('provider', 'kiot')
            ->with([
                'category:id,parent_id,name,slug,is_active,provider,show_on_pc_website,provider_sync_status',
                'brand:id,name',
                'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'catalogPrices',
            ]);
    }

    public function each(callable $callback): void
    {
        foreach ($this->projected() as [$projection, $product]) {
            $callback($projection, $product);
        }
    }

    public function projected(): \Generator
    {
        $categories = Category::query()->get([
            'id', 'parent_id', 'name', 'slug', 'is_active', 'provider',
            'show_on_pc_website', 'provider_sync_status',
        ])->keyBy('id');

        foreach ($this->query()->orderBy('id')->lazyById(max(1, (int) config('catalog.sync_chunk_size', 250))) as $product) {
            yield [$this->project($product, $categories), $product];
        }
    }

    public function project(Product $product, ?Collection $categories = null): CatalogProductData
    {
        $product->loadMissing(['category', 'brand', 'images', 'catalogPrices']);
        $categories ??= Category::query()->get()->keyBy('id');
        $category = $product->category;
        $categoryVisible = $category?->isVisibleOnStorefront() === true;
        $isDeleted = $product->trashed() || $product->kiot_sync_status === 'deleted';
        $isVisible = ! $isDeleted
            && (bool) $product->show_on_pc_website
            && (bool) $product->is_active
            && $product->kiot_sync_status === 'active'
            && $categoryVisible;
        $underRepair = (bool) $product->kiot_is_under_repair
            || $product->kiot_availability_status === 'repairing';
        $inventory = $underRepair ? 0 : max(0, (int) ($product->kiot_available_quantity ?? 0));
        $images = $product->images
            ->pluck('url')
            ->filter()
            ->map(fn (string $url): string => $this->absolutePublicUrl($url))
            ->filter()
            ->unique()
            ->values();
        $primaryImage = (string) ($images->first() ?? '');
        $imageStatus = $primaryImage === ''
            ? 'missing'
            : ($product->images->contains(fn ($image): bool => $image->provider === 'kiot' && blank($image->storage_path)
            ) ? 'not_mirrored' : 'has_image');
        $priceData = $this->prices->forProduct($product);
        $channelPrices = $this->channelPrices->resolveAll($product);
        $selectedChannelPrices = [];
        $priceIssues = [];
        foreach ($channelPrices as $channel => $resolved) {
            $selectedChannelPrices[$channel] = $resolved['value'];
            if ($resolved['issue']) {
                $priceIssues[$channel] = $resolved['issue'];
            }
        }
        $payload = [
            'id' => (int) $product->id,
            'external_id' => $this->externalId($product),
            'sku' => trim((string) $product->sku),
            'title' => trim((string) $product->name),
            'description' => $this->plainText((string) ($product->description ?: $product->short_description)),
            'category_id' => $category?->id ? (int) $category->id : null,
            'category_name' => trim((string) ($category?->name ?? '')),
            'category_path' => $this->categoryPath($category?->id, $categories),
            'category_visible' => $categoryVisible,
            'brand' => trim((string) ($product->brand?->name ?? '')),
            'condition' => 'new',
            'availability' => $inventory > 0 && ! $underRepair ? 'in_stock' : 'out_of_stock',
            'inventory' => $inventory,
            'price' => $priceData['retail_price'],
            'currency' => 'VND',
            'sale_price' => $product->sale_price !== null ? max(0, (int) $product->sale_price) : null,
            'product_url' => $this->productUrl($product, $category),
            'image_url' => $primaryImage,
            'additional_image_urls' => $images->skip(1)->values()->all(),
            'is_active' => (bool) $product->is_active && ! $isDeleted,
            'is_visible' => $isVisible,
            'is_under_repair' => $underRepair,
            'is_deleted' => $isDeleted,
            'updated_at' => CarbonImmutable::instance($product->updated_at ?? now()),
            'price_books' => $priceData['price_books'],
            'selected_channel_prices' => $selectedChannelPrices,
            'price_issues' => $priceIssues,
        ];

        return new CatalogProductData(
            id: $payload['id'],
            externalId: $payload['external_id'],
            sku: $payload['sku'],
            title: $payload['title'],
            description: $payload['description'],
            categoryId: $payload['category_id'],
            categoryName: $payload['category_name'],
            categoryPath: $payload['category_path'],
            categoryVisible: $payload['category_visible'],
            brand: $payload['brand'],
            condition: $payload['condition'],
            availability: $payload['availability'],
            inventory: $payload['inventory'],
            price: $payload['price'],
            currency: $payload['currency'],
            salePrice: $payload['sale_price'],
            productUrl: $payload['product_url'],
            imageUrl: $payload['image_url'],
            additionalImageUrls: $payload['additional_image_urls'],
            isActive: $payload['is_active'],
            isVisible: $payload['is_visible'],
            isUnderRepair: $payload['is_under_repair'],
            isDeleted: $payload['is_deleted'],
            updatedAt: $payload['updated_at'],
            checksum: $this->checksum->make($payload),
            priceBooks: $payload['price_books'],
            selectedChannelPrices: $payload['selected_channel_prices'],
            priceIssues: $payload['price_issues'],
            selectedPrice: $priceData['selected_price'],
            imageStatus: $imageStatus,
        );
    }

    private function externalId(Product $product): string
    {
        if ($product->remote_product_id !== null) {
            return 'kiot:'.(int) $product->remote_product_id;
        }

        return 'sku:'.Str::lower(trim((string) $product->sku));
    }

    private function productUrl(Product $product, ?Category $category): string
    {
        $base = rtrim((string) config('catalog.storefront_url'), '/');
        $categorySlug = trim((string) ($category?->slug ?: 'san-pham'), '/');

        return $base.'/'.$categorySlug.'/'.rawurlencode((string) $product->slug);
    }

    private function absolutePublicUrl(string $url): string
    {
        if (Str::startsWith($url, ['https://', 'http://'])) {
            return $url;
        }

        return rtrim((string) config('catalog.storefront_url'), '/').'/'.ltrim($url, '/');
    }

    private function categoryPath(?int $categoryId, Collection $categories): string
    {
        $names = [];
        $seen = [];

        while ($categoryId && ! isset($seen[$categoryId])) {
            $seen[$categoryId] = true;
            $category = $categories->get($categoryId);
            if (! $category) {
                break;
            }
            array_unshift($names, trim((string) $category->name));
            $categoryId = $category->parent_id ? (int) $category->parent_id : null;
        }

        return implode(' > ', array_filter($names));
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
