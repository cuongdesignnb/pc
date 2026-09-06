<?php

namespace App\Services\PcBuilder;

use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PcBuilderService
{
    /** Commercial PSU sizes used by the store's builder domain. */
    private const PSU_SIZES = [550, 650, 750, 850, 1000, 1200];

    public function normalizeBuild(array $build): array
    {
        $typeIds = ComponentType::query()->pluck('id', 'slug');
        $normalized = [];

        foreach ($build as $typeKey => $productId) {
            if (! is_scalar($productId) || ! is_numeric($productId) || (int) $productId < 1) {
                continue;
            }

            $typeId = is_numeric($typeKey)
                ? (int) $typeKey
                : (int) ($typeIds->get((string) $typeKey) ?? 0);

            if ($typeId > 0) {
                $normalized[$typeId] = (int) $productId;
            }
        }

        return $normalized;
    }

    /**
     * Resolve a build and calculate every value shown by the builder summary.
     * Prices, sellability, power and compatibility all come from this method.
     *
     * @return array{build: array<int, int>, products: Collection<int, Product>, issues: array<int, array<string, mixed>>, totals: array<string, int|null>, completion: array<string, int|bool>}
     */
    public function checkBuild(array $build): array
    {
        $normalized = $this->normalizeBuild($build);
        $resolved = $this->resolveBuild($normalized);
        $issues = $resolved['issues'];
        $products = $resolved['products'];
        $rules = $this->activeRules();

        foreach ($rules as $rule) {
            $sourceProduct = $products->get((int) $rule->source_type_id);
            $targetProduct = $products->get((int) $rule->target_type_id);

            if (! $sourceProduct || ! $targetProduct || $rule->rule_type === 'power_check') {
                continue;
            }

            $issue = $this->evaluateRule($rule, $sourceProduct, $targetProduct);
            if ($issue) {
                $issues[] = $issue;
            }
        }

        $totals = $this->calculateTotals($products, $rules);
        $issues = array_merge($issues, $this->powerIssues($products, $totals));
        $completion = $this->completion($products);

        foreach ($completion['missing'] as $type) {
            $issues[] = [
                'type' => 'warning',
                'code' => 'missing_required',
                'message' => "Chưa chọn {$type['name']}.",
                'source_type_id' => $type['id'],
                'target_type_id' => null,
            ];
        }

        return [
            'build' => $normalized,
            'products' => $products->values(),
            'issues' => array_values($issues),
            'totals' => $totals,
            'completion' => [
                'selected' => $products->count(),
                'required_selected' => $completion['required_selected'],
                'required_total' => $completion['required_total'],
                'complete' => $completion['complete'],
            ],
        ];
    }

    /**
     * Load a server-filtered and server-sorted page of products for one slot.
     * Compatibility is evaluated before pagination so the compatibility sort
     * and only-compatible filter never depend on frontend data.
     *
     * @return array{products: Collection<int, array{product: Product, is_compatible: bool, issues: array<int, array<string, mixed>} }>, meta: array<string, int>, filters: array<string, mixed>}
     */
    public function compatibleProducts(ComponentType $componentType, array $build, array $filters = []): array
    {
        $normalized = $this->normalizeBuild($build);
        $resolved = $this->resolveBuild($normalized);
        $currentProducts = $resolved['products'];
        $rules = $this->activeRules();

        $query = $this->builderProductQuery()
            ->where('component_type_id', $componentType->id);

        $this->applyFilters($query, $filters);
        $candidates = $query->get();
        $decorated = $candidates->map(function (Product $candidate) use ($componentType, $currentProducts, $rules): array {
            $candidateBuild = $currentProducts->except([(int) $componentType->id]);
            $candidateBuild->put((int) $componentType->id, $candidate);
            $issues = [];

            foreach ($rules as $rule) {
                $sourceProduct = $candidateBuild->get((int) $rule->source_type_id);
                $targetProduct = $candidateBuild->get((int) $rule->target_type_id);

                if (! $sourceProduct || ! $targetProduct || $rule->rule_type === 'power_check') {
                    continue;
                }

                $issue = $this->evaluateRule($rule, $sourceProduct, $targetProduct);
                if ($issue) {
                    $issues[] = $issue;
                }
            }

            $totals = $this->calculateTotals($candidateBuild, $rules);
            $issues = array_merge($issues, $this->powerIssues($candidateBuild, $totals));

            return [
                'product' => $candidate,
                'is_compatible' => ! collect($issues)->contains(fn (array $issue) => $issue['type'] === 'error'),
                'issues' => array_values($issues),
            ];
        });

        if (($filters['only_compatible'] ?? true) === true) {
            $decorated = $decorated->filter(fn (array $item) => $item['is_compatible'])->values();
        }

        $decorated = $this->sortCandidates($decorated, (string) ($filters['sort'] ?? 'popular'));
        $total = $decorated->count();
        $perPage = min(48, max(1, (int) ($filters['per_page'] ?? 24)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return [
            'products' => $decorated->forPage($page, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'filters' => $this->filterOptions($candidates, $componentType),
        ];
    }

    /** @return Collection<int, CompatibilityRule> */
    private function activeRules(): Collection
    {
        return CompatibilityRule::query()
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param  array<int, int>  $build
     * @return array{products: Collection<int, Product>, issues: array<int, array<string, mixed>>}
     */
    private function resolveBuild(array $build): array
    {
        $productsById = $this->builderProductQuery()
            ->whereIn('id', array_values($build))
            ->get()
            ->keyBy('id');
        $products = collect();
        $issues = [];

        foreach ($build as $typeId => $productId) {
            $product = $productsById->get($productId);
            if (! $product) {
                $issues[] = [
                    'type' => 'error',
                    'code' => 'product_unavailable',
                    'message' => 'Một linh kiện trong cấu hình không còn sẵn sàng để bán.',
                    'source_type_id' => (int) $typeId,
                    'target_type_id' => null,
                ];

                continue;
            }

            if ((int) $product->component_type_id !== (int) $typeId) {
                $issues[] = [
                    'type' => 'error',
                    'code' => 'product_wrong_component_type',
                    'message' => 'Linh kiện không thuộc đúng nhóm cấu hình.',
                    'source_type_id' => (int) $typeId,
                    'target_type_id' => (int) $product->component_type_id,
                ];

                continue;
            }

            $products->put((int) $typeId, $product);
        }

        return ['products' => $products, 'issues' => $issues];
    }

    private function builderProductQuery(): Builder
    {
        return Product::query()
            ->with([
                'brand',
                'category',
                'componentType',
                'images',
                'specifications.specificationKey',
                'powerRequirement',
            ])
            ->withAvg('approvedReviews', 'rating')
            ->withCount([
                'approvedReviews',
                'variants' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->sellableOnline();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $queryString = trim((string) ($filters['query'] ?? ''));
        if ($queryString !== '') {
            $like = '%'.addcslashes($queryString, '%_').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like));
            });
        }

        $brandIds = collect($filters['brand_ids'] ?? $filters['brands'] ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();
        if ($brandIds->isNotEmpty()) {
            $query->whereIn('brand_id', $brandIds);
        }

        $priceExpression = "CASE WHEN inventory_source = 'kiot' THEN price ELSE COALESCE(sale_price, price) END";
        if (is_numeric($filters['price_min'] ?? null)) {
            $query->whereRaw("{$priceExpression} >= ?", [(int) $filters['price_min']]);
        }
        if (is_numeric($filters['price_max'] ?? null)) {
            $query->whereRaw("{$priceExpression} <= ?", [(int) $filters['price_max']]);
        }

        if (($filters['on_sale'] ?? false) === true) {
            $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price');
        }

        foreach (($filters['specs'] ?? []) as $key => $values) {
            $values = collect(is_array($values) ? $values : [$values])
                ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
                ->map(fn ($value) => (string) $value)
                ->values();
            if ($values->isEmpty()) {
                continue;
            }

            $query->whereHas('specifications', function (Builder $specification) use ($key, $values): void {
                $specification
                    ->whereIn('value', $values)
                    ->whereHas('specificationKey', fn (Builder $specificationKey) => $specificationKey->where('key', (string) $key));
            });
        }
    }

    /** @param Collection<int, array{product: Product, is_compatible: bool, issues: array<int, array<string, mixed>>}> $candidates */
    private function sortCandidates(Collection $candidates, string $sort): Collection
    {
        return match ($sort) {
            'price_asc' => $candidates->sortBy(fn (array $item) => $item['product']->purchasableUnitPrice())->values(),
            'price_desc' => $candidates->sortByDesc(fn (array $item) => $item['product']->purchasableUnitPrice())->values(),
            'rating' => $candidates->sortByDesc(fn (array $item) => (float) ($item['product']->approved_reviews_avg_rating ?? 0))->values(),
            'compatibility' => $candidates->sortByDesc(fn (array $item) => $item['is_compatible'])->sortByDesc(fn (array $item) => (int) $item['product']->sold_count)->values(),
            default => $candidates->sortByDesc(fn (array $item) => (int) $item['product']->sold_count)->sortByDesc(fn (array $item) => (float) ($item['product']->approved_reviews_avg_rating ?? 0))->values(),
        };
    }

    /** @return array{brands: array<int, array{id: int, name: string, count: int}>, specifications: array<string, array<int, string>>} */
    private function filterOptions(Collection $products, ComponentType $componentType): array
    {
        $brands = $products->filter(fn (Product $product) => $product->brand)
            ->groupBy('brand_id')
            ->map(fn (Collection $items, $brandId) => [
                'id' => (int) $brandId,
                'name' => (string) $items->first()->brand->name,
                'count' => $items->count(),
            ])->values()->all();
        $filterKeys = $componentType->relationLoaded('specificationKeys')
            ? $componentType->specificationKeys->where('is_filterable', true)
            : $componentType->specificationKeys()->where('is_filterable', true)->get();
        $specifications = [];

        foreach ($filterKeys as $key) {
            $values = $products->flatMap(fn (Product $product) => $product->specifications
                ->filter(fn ($specification) => $specification->specification_key_id === $key->id)
                ->map(fn ($specification) => (string) $specification->value))
                ->filter()
                ->unique()
                ->values()
                ->all();
            if ($values !== []) {
                $specifications[$key->key] = $values;
            }
        }

        return ['brands' => $brands, 'specifications' => $specifications];
    }

    /** @return array{price: int, tdp: int, recommended_psu_wattage: int|null} */
    private function calculateTotals(Collection $products, Collection $rules): array
    {
        $price = (int) $products->sum(fn (Product $product) => $product->purchasableUnitPrice());
        $tdp = (int) $products->sum(fn (Product $product) => $this->productTdp($product));
        $headroom = (int) $rules
            ->filter(fn (CompatibilityRule $rule) => $rule->rule_type === 'power_check')
            ->max(fn (CompatibilityRule $rule) => (int) $rule->power_headroom);
        $headroom = max(0, $headroom);

        return [
            'price' => $price,
            'tdp' => $tdp,
            'recommended_psu_wattage' => $tdp > 0 ? $this->recommendedPsuWattage($tdp, $headroom) : null,
        ];
    }

    private function productTdp(Product $product): int
    {
        if ($product->powerRequirement?->typical_tdp !== null) {
            return max(0, (int) $product->powerRequirement->typical_tdp);
        }

        $specification = $product->specifications->first(
            fn ($specification) => $specification->specificationKey?->key === 'tdp'
        );

        return $specification ? max(0, (int) $specification->value) : 0;
    }

    private function recommendedPsuWattage(int $tdp, int $headroom): int
    {
        $required = $tdp + $headroom;
        foreach (self::PSU_SIZES as $size) {
            if ($required <= $size) {
                return $size;
            }
        }

        return (int) (ceil($required / 100) * 100);
    }

    /** @return array<int, array<string, mixed>> */
    private function powerIssues(Collection $products, array $totals): array
    {
        $psu = $products->first(fn (Product $product) => $product->componentType?->slug === 'psu');
        $recommended = $totals['recommended_psu_wattage'];
        if (! $psu || ! $recommended) {
            return [];
        }

        $wattage = $psu->specifications->first(
            fn ($specification) => $specification->specificationKey?->key === 'wattage'
        );
        if (! $wattage || (int) $wattage->value >= $recommended) {
            return [];
        }

        return [[
            'type' => 'warning',
            'code' => 'psu_insufficient',
            'message' => "Nguồn {$wattage->value}W thấp hơn mức khuyến nghị {$recommended}W cho cấu hình này.",
            'source_type_id' => (int) $psu->component_type_id,
            'target_type_id' => null,
        ]];
    }

    /** @return array{required_selected: int, required_total: int, complete: bool, missing: array<int, array{id: int, name: string}>} */
    private function completion(Collection $products): array
    {
        $types = ComponentType::query()->orderBy('display_order')->get();
        $required = $types->where('is_required', true)->values();
        $missing = $required->filter(fn (ComponentType $type) => ! $products->has((int) $type->id))
            ->map(fn (ComponentType $type) => ['id' => (int) $type->id, 'name' => $type->name])
            ->values()
            ->all();

        return [
            'required_selected' => $required->count() - count($missing),
            'required_total' => $required->count(),
            'complete' => $missing === [],
            'missing' => $missing,
        ];
    }

    private function evaluateRule(CompatibilityRule $rule, Product $sourceProduct, Product $targetProduct): ?array
    {
        $sourceSpec = $sourceProduct->specifications
            ->first(fn ($specification) => $specification->specificationKey?->key === $rule->source_spec_key);
        $targetSpec = $targetProduct->specifications
            ->first(fn ($specification) => $specification->specificationKey?->key === $rule->target_spec_key);

        if (! $sourceSpec || ! $targetSpec) {
            return null;
        }

        $sourceValue = trim((string) $sourceSpec->value);
        $targetValue = trim((string) $targetSpec->value);
        $failed = match ($rule->rule_type) {
            'must_match' => $sourceValue !== $targetValue,
            'must_fit' => ! in_array($targetValue, ((array) $rule->allowed_values)[$sourceValue] ?? [], true),
            'must_fit_dimension' => (int) $sourceValue > (int) $targetValue,
            'must_contain' => stripos($sourceValue, $targetValue) === false,
            default => false,
        };

        return $failed ? [
            'type' => 'error',
            'code' => 'compatibility_rule_'.$rule->rule_type,
            'message' => $rule->message,
            'source_type_id' => (int) $rule->source_type_id,
            'target_type_id' => (int) $rule->target_type_id,
        ] : null;
    }
}
