<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SpecificationKey;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryController extends Controller
{
    /**
     * Get all categories (tree structure).
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->visibleOnStorefront()
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    /**
     * Homepage sections: parent categories with product_count + sample products.
     */
    public function homepageSections(): JsonResponse
    {
        $configuredLimit = Setting::get('homepage_products_per_section');
        $productLimit = is_numeric($configuredLimit)
            ? max(1, min(50, (int) $configuredLimit))
            : 8;

        $parents = Category::with(['children' => function ($q) {
            $q->visibleOnStorefront()->orderBy('sort_order');
        }])
            ->whereNull('parent_id')
            ->visibleOnStorefront()
            ->orderBy('sort_order')
            ->get();

        $sections = [];

        foreach ($parents as $parent) {
            $categoryIds = collect([$parent->id])
                ->merge($parent->children->pluck('id'))
                ->all();

            $productCount = $this->storefrontProductQuery($categoryIds)->count();
            if ($productCount === 0) {
                continue;
            }

            $products = $this->productCardQuery($categoryIds)
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit($productLimit)
                ->get();

            $sections[] = [
                'category' => $this->basicCategoryPayload($parent, $parent->description),
                'children' => $parent->children->map(fn (Category $child) => $this->basicCategoryPayload($child))->values(),
                'product_count' => $productCount,
                'products' => ProductCardResource::collection($products)->resolve(),
            ];
        }

        return response()->json($sections);
    }

    /**
     * Get a category, its filter metadata, a paginated product listing and
     * real recommendations for the current category.
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $category = Category::with([
            'children' => function ($query) {
                $query->visibleOnStorefront()->orderBy('sort_order');
            },
            'parent',
        ])
            ->where('slug', $slug)
            ->visibleOnStorefront()
            ->firstOrFail();

        $categoryIds = collect([$category->id])
            ->merge($category->children->pluck('id'))
            ->values();
        $categoryIdsArray = $categoryIds->all();
        $childProductCounts = $this->childProductCounts($category->children);

        $assignedFilters = $category->filters()
            ->where('is_active', true)
            ->with(['activeValues'])
            ->get();

        if ($assignedFilters->isEmpty() && $category->parent_id) {
            $assignedFilters = Category::find($category->parent_id)?->filters()
                ->where('is_active', true)
                ->with(['activeValues'])
                ->get() ?? collect();
        }

        $query = $this->productCardQuery($categoryIdsArray);

        if ($request->filled('sub_category')) {
            $subCategory = $category->children
                ->firstWhere('slug', (string) $request->input('sub_category'));
            if ($subCategory) {
                $query->where('category_id', $subCategory->id);
            }
        }

        $brandSlugs = $this->csv($request->input('brands'));
        if ($brandSlugs !== []) {
            $query->whereHas('brand', fn (Builder $brandQuery) => $brandQuery->whereIn('slug', $brandSlugs));
        }

        if ($request->boolean('in_stock')) {
            $this->applyInStock($query);
        }

        foreach ($assignedFilters as $filter) {
            $selectedSlugs = $this->csv($request->input('f_'.$filter->slug));
            if ($selectedSlugs === []) {
                continue;
            }

            $selectedValues = $filter->activeValues->whereIn('slug', $selectedSlugs);
            if ($selectedValues->isEmpty()) {
                continue;
            }

            $query->where(function (Builder $groupQuery) use ($filter, $selectedValues): void {
                foreach ($selectedValues as $value) {
                    $groupQuery->orWhere(function (Builder $valueQuery) use ($filter, $value): void {
                        $this->applyFilterValue($valueQuery, $filter->match_field, $value);
                    });
                }
            });
        }

        // Legacy specification filters remain supported for old links.
        foreach ($request->all() as $key => $value) {
            if (! str_starts_with((string) $key, 'spec_')) {
                continue;
            }

            $values = $this->csv($value);
            if ($values === []) {
                continue;
            }

            $specificationKeyId = (int) str_replace('spec_', '', (string) $key);
            $query->whereHas('specifications', function (Builder $specificationQuery) use ($specificationKeyId, $values): void {
                $specificationQuery
                    ->where('specification_key_id', $specificationKeyId)
                    ->whereIn('value_string', $values);
            });
        }

        $minPrice = $this->numeric($request->input('min_price'));
        $maxPrice = $this->numeric($request->input('max_price'));
        if ($minPrice !== null) {
            $query->whereRaw('COALESCE(NULLIF(sale_price, 0), price) >= ?', [$minPrice]);
        }
        if ($maxPrice !== null) {
            $query->whereRaw('COALESCE(NULLIF(sale_price, 0), price) <= ?', [$maxPrice]);
        }

        $sort = (string) $request->input('sort', 'popular');
        if (! in_array($sort, ['popular', 'rating', 'newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'], true)) {
            $sort = 'popular';
        }

        match ($sort) {
            'price_asc' => $query
                ->orderByRaw('COALESCE(NULLIF(sale_price, 0), price) ASC')
                ->orderBy('id'),
            'price_desc' => $query
                ->orderByRaw('COALESCE(NULLIF(sale_price, 0), price) DESC')
                ->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderBy('id'),
            'rating' => $query
                ->orderByRaw('approved_reviews_avg_rating IS NULL ASC')
                ->orderByDesc('approved_reviews_avg_rating')
                ->orderByDesc('approved_reviews_count')
                ->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('sold_count')->orderByDesc('created_at')->orderByDesc('id'),
        };

        $perPage = max(1, min(48, (int) $request->input('per_page', 24)));
        $products = $query->paginate($perPage);
        $productIds = $products->getCollection()->pluck('id')->all();

        $recommendations = $this->productCardQuery($categoryIdsArray)
            ->when($productIds !== [], fn (Builder $recommendationQuery) => $recommendationQuery->whereNotIn('id', $productIds))
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderByRaw('approved_reviews_avg_rating IS NULL ASC')
            ->orderByDesc('approved_reviews_avg_rating')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $brandIds = $this->storefrontProductQuery($categoryIdsArray)
            ->whereNotNull('brand_id')
            ->distinct()
            ->pluck('brand_id');
        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->where('is_active', true)
            ->withCount([
                'products as products_count' => fn (Builder $brandProducts) => $brandProducts
                    ->whereIn('category_id', $categoryIdsArray)
                    ->visibleOnStorefront(),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo']);

        $priceStats = $this->storefrontProductQuery($categoryIdsArray)
            ->selectRaw('MIN(COALESCE(NULLIF(sale_price, 0), price)) as min_price, MAX(COALESCE(NULLIF(sale_price, 0), price)) as max_price')
            ->first();

        $filterGroups = $this->filterGroups($assignedFilters, $categoryIdsArray);
        $specFilters = $this->legacySpecFilters($assignedFilters, $categoryIdsArray, $category->component_type_id);

        return response()->json([
            'category' => $this->categoryPayload($category, $childProductCounts),
            'promo_banner' => $this->categoryBanner($category->slug),
            'products' => [
                'data' => ProductCardResource::collection($products->getCollection())->resolve(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'recommendations' => ProductCardResource::collection($recommendations)->resolve(),
            'filters' => [
                'brands' => $brands,
                'price_range' => [
                    'min' => (int) ($priceStats->min_price ?? 0),
                    'max' => (int) ($priceStats->max_price ?? 0),
                ],
                'price_presets' => $this->pricePresets($assignedFilters),
                'groups' => $filterGroups,
                'specs' => $specFilters,
            ],
        ]);
    }

    /** @param array<int, int> $categoryIds */
    private function storefrontProductQuery(array $categoryIds): Builder
    {
        return Product::query()
            ->whereIn('category_id', $categoryIds)
            ->visibleOnStorefront();
    }

    /** @param array<int, int> $categoryIds */
    private function productCardQuery(array $categoryIds): Builder
    {
        return Product::query()
            ->with(['category', 'brand', 'images'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->withExists([
                'variants as has_variants' => fn (Builder $variantQuery) => $variantQuery->where('is_active', true),
            ])
            ->whereIn('category_id', $categoryIds)
            ->visibleOnStorefront();
    }

    private function applyInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $stockQuery): void {
            $stockQuery
                ->where(function (Builder $localQuery): void {
                    $localQuery->whereNull('provider')->where('stock_quantity', '>', 0);
                })
                ->orWhere(function (Builder $kiotQuery): void {
                    $kiotQuery
                        ->where('provider', 'kiot')
                        ->where('kiot_sellable', true)
                        ->where('kiot_sync_status', 'active')
                        ->where('kiot_availability_status', 'available')
                        ->where('kiot_available_quantity', '>', 0)
                        ->where('price', '>', 0);
                })
                ->orWhereHas('variants', fn (Builder $variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->where('stock_quantity', '>', 0));
        });
    }

    private function applyFilterValue(Builder $query, string $matchField, FilterValue $value): void
    {
        switch ($matchField) {
            case 'specifications_text':
                $query->where('specifications_text', 'LIKE', '%'.$value->match_value.'%');
                break;
            case 'product_name':
                $query->where('name', 'LIKE', '%'.$value->match_value.'%');
                break;
            case 'brand':
                $query->whereHas('brand', fn (Builder $brandQuery) => $brandQuery->where('slug', $value->match_value));
                break;
            case 'price':
                $this->applyPriceValue($query, $value);
                break;
        }
    }

    private function applyPriceValue(Builder $query, FilterValue $value): void
    {
        if ($value->price_min !== null) {
            $query->whereRaw('COALESCE(NULLIF(sale_price, 0), price) >= ?', [$value->price_min]);
        }
        if ($value->price_max !== null) {
            $query->whereRaw('COALESCE(NULLIF(sale_price, 0), price) <= ?', [$value->price_max]);
        }
    }

    /** @param Collection<int, mixed> $filters */
    private function filterGroups(Collection $filters, array $categoryIds): array
    {
        return $filters
            ->reject(fn ($filter): bool => $filter->type === 'price_range'
                || in_array($filter->match_field, ['price', 'brand'], true))
            ->map(function ($filter) use ($categoryIds): array {
                $values = $filter->activeValues->map(function (FilterValue $value) use ($filter, $categoryIds): array {
                    $countQuery = $this->storefrontProductQuery($categoryIds);
                    $this->applyFilterValue($countQuery, $filter->match_field, $value);

                    return [
                        'label' => $value->label,
                        'slug' => $value->slug,
                        'count' => $countQuery->count(),
                    ];
                })->values()->all();

                return [
                    'id' => $filter->id,
                    'name' => $filter->name,
                    'slug' => $filter->slug,
                    'type' => $filter->type,
                    'match_field' => $filter->match_field,
                    'values' => $values,
                ];
            })->values()->all();
    }

    /** @param Collection<int, mixed> $filters */
    private function pricePresets(Collection $filters): array
    {
        $priceFilter = $filters->first(fn ($filter): bool => $filter->type === 'price_range' || $filter->match_field === 'price');
        if (! $priceFilter) {
            return [];
        }

        return $priceFilter->activeValues
            ->map(fn (FilterValue $value): array => [
                'key' => $value->slug,
                'label' => $value->label,
                'min' => $value->price_min === null ? null : (int) $value->price_min,
                'max' => $value->price_max === null ? null : (int) $value->price_max,
            ])
            ->values()
            ->all();
    }

    /** @param Collection<int, mixed> $assignedFilters */
    private function legacySpecFilters(Collection $assignedFilters, array $categoryIds, ?int $componentTypeId): array
    {
        $hasDynamicSpecificationFilter = $assignedFilters->contains(
            fn ($filter): bool => $filter->type !== 'price_range'
                && ! in_array($filter->match_field, ['price', 'brand'], true),
        );
        if ($hasDynamicSpecificationFilter || ! $componentTypeId) {
            return [];
        }

        $visibleProductIds = $this->storefrontProductQuery($categoryIds)->select('products.id');
        $specFilters = [];
        $specKeys = SpecificationKey::query()
            ->where('component_type_id', $componentTypeId)
            ->where('is_filterable', true)
            ->orderBy('display_order')
            ->get();

        foreach ($specKeys as $specKey) {
            $values = \DB::table('product_specifications')
                ->join('products', 'products.id', '=', 'product_specifications.product_id')
                ->whereIn('products.id', $visibleProductIds)
                ->where('product_specifications.specification_key_id', $specKey->id)
                ->whereNotNull('product_specifications.value_string')
                ->distinct()
                ->orderBy('product_specifications.value_string')
                ->pluck('product_specifications.value_string');

            if ($values->isEmpty()) {
                continue;
            }

            $specFilters[] = [
                'key_id' => $specKey->id,
                'label' => $specKey->label,
                'unit' => $specKey->unit,
                'type' => $specKey->data_type,
                'values' => $values->values()->all(),
            ];
        }

        return $specFilters;
    }

    /** @param Collection<int, Category> $children */
    private function childProductCounts(Collection $children): Collection
    {
        if ($children->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('category_id', $children->pluck('id'))
            ->visibleOnStorefront()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');
    }

    private function categoryPayload(Category $category, Collection $childProductCounts): array
    {
        return array_merge(
            $this->basicCategoryPayload($category, $category->description),
            [
                'parent' => $category->parent ? $this->basicCategoryPayload($category->parent, $category->parent->description) : null,
                'children' => $category->children->map(function (Category $child) use ($childProductCounts): array {
                    return array_merge(
                        $this->basicCategoryPayload($child, $child->description),
                        ['product_count' => (int) $childProductCounts->get($child->id, 0)],
                    );
                })->values()->all(),
            ],
        );
    }

    private function basicCategoryPayload(Category $category, ?string $description = null): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $description ?? $category->description,
            'image' => $category->image,
            'icon' => $category->icon,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
        ];
    }

    private function categoryBanner(string $categorySlug): ?array
    {
        $banner = Banner::query()
            ->active()
            ->where('position', 'category')
            ->orderBy('sort_order')
            ->get()
            ->first(function (Banner $candidate) use ($categorySlug): bool {
                $configuredSlug = data_get($candidate->metadata, 'category_slug');
                if (is_array($configuredSlug)) {
                    return in_array($categorySlug, $configuredSlug, true);
                }

                return is_string($configuredSlug) && trim($configuredSlug) === $categorySlug;
            });

        if (! $banner) {
            return null;
        }

        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'badge' => $banner->badge,
            'image' => PublicAssetUrl::normalize($banner->image),
            'link' => $banner->link,
            'position' => $banner->position,
            'sort_order' => $banner->sort_order,
            'metadata' => $banner->metadata,
        ];
    }

    /** @return array<int, string> */
    private function csv(mixed $value): array
    {
        $values = is_array($value) ? $value : (is_string($value) ? explode(',', $value) : []);

        return collect($values)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function numeric(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
