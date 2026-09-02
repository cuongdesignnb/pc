<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Category;
use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use App\Models\Product;
use App\Models\ProductRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get all products with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'images'])
            ->visibleOnStorefront();

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        }

        // Filter by category (including children of parent categories)
        if ($request->category) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $categoryIds = [$category->id];
                // If it's a parent category, include all children
                $childIds = Category::where('parent_id', $category->id)->pluck('id')->toArray();
                $categoryIds = array_merge($categoryIds, $childIds);
                $query->whereIn('category_id', $categoryIds);
            }
        }

        // Filter by brand
        if ($request->brand) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('slug', $request->brand);
            });
        }

        // Filter by component type
        if ($request->component_type) {
            $query->whereHas('componentType', function ($q) use ($request) {
                $q->where('slug', $request->component_type);
            });
        }

        // Price range
        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }

        // Featured only
        if ($request->featured) {
            $query->where('is_featured', true);
        }

        // Sorting
        $sortField = in_array($request->sort, ['created_at', 'price', 'name'], true)
            ? $request->sort
            : 'created_at';
        $sortDirection = in_array($request->order, ['asc', 'desc'], true)
            ? $request->order
            : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate($request->per_page ?? 20);

        return ProductCardResource::collection($products)->response();
    }

    /**
     * Get featured products
     */
    public function featured(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->visibleOnStorefront()
            ->where('is_featured', true)
            ->limit(8)
            ->get();

        return response()->json(ProductCardResource::collection($products)->resolve());
    }

    /**
     * Get single product by slug
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::with([
            'category',
            'brand',
            'componentType',
            'images',
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id'),
            'approvedReviews:id,product_id,rating',
            'specifications.specificationKey',
            'highlights' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
            'detailBlocks' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
        ])
            ->where('slug', $slug)
            ->visibleOnStorefront()
            ->firstOrFail();

        return response()->json([
            'product' => new ProductDetailResource($product),
        ]);
    }

    /**
     * Return PDP compatibility facts sourced solely from the compatibility schema.
     */
    public function compatibilitySummary(string $slug): JsonResponse
    {
        $product = Product::with(['componentType', 'specifications.specificationKey', 'powerRequirement'])
            ->where('slug', $slug)
            ->visibleOnStorefront()
            ->firstOrFail();

        $facts = $product->specifications
            ->filter(fn ($spec) => filled($spec->value) && $spec->specificationKey?->label)
            ->map(fn ($spec) => [
                'label' => $spec->specificationKey->label,
                'value' => trim($spec->value.' '.($spec->specificationKey->unit ?? '')),
                'status' => 'ok',
            ])->values();
        $warnings = [];

        if ($product->powerRequirement?->requires_pcie_power) {
            $connectorCount = $product->powerRequirement->pcie_connectors_needed;
            if ($connectorCount > 0) {
                $warnings[] = "Cần kiểm tra nguồn có đủ {$connectorCount} đầu cấp nguồn PCIe phù hợp.";
            }
        }

        return response()->json([
            'component_type' => $product->componentType ? [
                'id' => $product->componentType->id,
                'name' => $product->componentType->name,
                'slug' => $product->componentType->slug,
            ] : null,
            'facts' => $facts,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Return manual merchandising relations. The related/alternative fallback is
     * intentionally limited to published products and never uses PC compatibility.
     */
    public function relations(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:related,accessory,frequently_bought,alternative'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $type = $validated['type'] ?? 'related';
        $limit = $validated['limit'] ?? 8;
        $product = Product::with(['category', 'brand'])
            ->where('slug', $slug)
            ->visibleOnStorefront()
            ->firstOrFail();

        $manualIds = ProductRelation::where('product_id', $product->id)
            ->where('relation_type', $type)
            ->orderBy('sort_order')
            ->pluck('related_product_id');
        $products = Product::with(['category', 'brand', 'images'])
            ->visibleOnStorefront()
            ->where('id', '!=', $product->id)
            ->whereIn('id', $manualIds)
            ->get()
            ->sortBy(fn (Product $item) => $manualIds->search($item->id))
            ->take($limit)
            ->values();

        if ($products->isEmpty() && in_array($type, ['related', 'alternative'], true) && $product->category_id) {
            $fallback = Product::with(['category', 'brand', 'images'])
                ->visibleOnStorefront()
                ->where('id', '!=', $product->id)
                ->where('category_id', $product->category_id);

            if ($type === 'related' && $product->brand_id) {
                $fallback->orderByRaw('brand_id = ? desc', [$product->brand_id]);
            }
            if ($type === 'alternative') {
                $price = max(1, $product->purchasableUnitPrice());
                $fallback->whereBetween('price', [(int) floor($price * 0.7), (int) ceil($price * 1.3)]);
            }
            $products = $fallback->latest()->limit($limit)->get();
        }

        return response()->json([
            'relation_type' => $type,
            'products' => ProductCardResource::collection($products)->resolve(),
        ]);
    }

    /** Lightweight lookup used by the local recently-viewed list. */
    public function cards(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:12'],
            'ids.*' => ['integer'],
            'slugs' => ['nullable', 'array', 'max:12'],
            'slugs.*' => ['string', 'max:255'],
        ]);

        $ids = collect($validated['ids'] ?? [])->filter()->values();
        $slugs = collect($validated['slugs'] ?? [])->filter()->values();
        if ($ids->isEmpty() && $slugs->isEmpty()) {
            return response()->json(['products' => []]);
        }

        $products = Product::with(['category', 'brand', 'images'])
            ->visibleOnStorefront()
            ->where(function ($query) use ($ids, $slugs) {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids);
                }
                if ($slugs->isNotEmpty()) {
                    $ids->isNotEmpty() ? $query->orWhereIn('slug', $slugs) : $query->whereIn('slug', $slugs);
                }
            })->get();

        return response()->json(['products' => ProductCardResource::collection($products)->resolve()]);
    }

    /**
     * Get products by component type (for PC Builder)
     */
    public function byComponentType(string $slug): JsonResponse
    {
        $products = Product::with(['brand', 'images'])
            ->sellableOnline()
            ->whereHas('componentType', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->get();

        return response()->json($products);
    }

    /**
     * Get compatible product suggestions for a given product
     * Groups compatible products by component type
     */
    public function suggestions(string $slug): JsonResponse
    {
        $product = Product::with(['componentType', 'specifications.specificationKey'])
            ->where('slug', $slug)
            ->sellableOnline()
            ->firstOrFail();

        // Product must have a component type for compatibility
        if (! $product->component_type_id) {
            return response()->json(['suggestions' => []]);
        }

        // Get all active compatibility rules involving this component type
        $rules = CompatibilityRule::with(['sourceType', 'targetType'])
            ->where('is_active', true)
            ->where(function ($q) use ($product) {
                $q->where('source_type_id', $product->component_type_id)
                    ->orWhere('target_type_id', $product->component_type_id);
            })
            ->get();

        if ($rules->isEmpty()) {
            return response()->json(['suggestions' => []]);
        }

        // Collect related component type IDs
        $relatedTypeIds = $rules->map(function ($rule) use ($product) {
            return $rule->source_type_id === $product->component_type_id
                ? $rule->target_type_id
                : $rule->source_type_id;
        })->unique()->values();

        // Get component types
        $componentTypes = ComponentType::whereIn('id', $relatedTypeIds)
            ->orderBy('display_order')
            ->get()
            ->keyBy('id');

        // Get candidate products for each related type
        $candidateProducts = Product::with(['brand', 'images', 'specifications.specificationKey'])
            ->sellableOnline()
            ->whereIn('component_type_id', $relatedTypeIds)
            ->get()
            ->groupBy('component_type_id');

        $suggestions = [];

        foreach ($relatedTypeIds as $typeId) {
            $type = $componentTypes->get($typeId);
            if (! $type) {
                continue;
            }

            $candidates = $candidateProducts->get($typeId, collect());
            $compatible = [];

            foreach ($candidates as $candidate) {
                $isCompatible = true;
                $issues = [];

                // Check all rules between our product and this candidate
                $applicableRules = $rules->filter(function ($rule) use ($product, $typeId) {
                    return ($rule->source_type_id === $product->component_type_id && $rule->target_type_id === $typeId)
                        || ($rule->target_type_id === $product->component_type_id && $rule->source_type_id === $typeId);
                });

                foreach ($applicableRules as $rule) {
                    $sourceProduct = $rule->source_type_id === $product->component_type_id ? $product : $candidate;
                    $targetProduct = $rule->target_type_id === $product->component_type_id ? $product : $candidate;

                    $issue = $this->evaluateRule($rule, $sourceProduct, $targetProduct);
                    if ($issue) {
                        $isCompatible = false;
                        $issues[] = $issue;
                    }
                }

                if ($isCompatible) {
                    $compatible[] = ProductCardResource::make($candidate)->resolve();
                }
            }

            if (! empty($compatible)) {
                $suggestions[] = [
                    'component_type' => $type,
                    'products' => $compatible,
                ];
            }
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Evaluate a single compatibility rule between source and target products
     */
    private function evaluateRule(CompatibilityRule $rule, Product $sourceProduct, Product $targetProduct): ?array
    {
        $sourceSpec = $sourceProduct->specifications
            ->first(fn ($s) => $s->specificationKey?->key === $rule->source_spec_key);

        $targetSpec = $targetProduct->specifications
            ->first(fn ($s) => $s->specificationKey?->key === $rule->target_spec_key);

        if (! $sourceSpec || ! $targetSpec) {
            return null;
        }

        $sourceValue = $sourceSpec->value;
        $targetValue = $targetSpec->value;

        switch ($rule->rule_type) {
            case 'must_match':
                if ($sourceValue !== $targetValue) {
                    return ['type' => 'error', 'message' => $rule->message];
                }
                break;
            case 'must_fit':
                $allowedValues = $rule->allowed_values[$sourceValue] ?? [];
                if (! in_array($targetValue, $allowedValues)) {
                    return ['type' => 'error', 'message' => $rule->message];
                }
                break;
            case 'must_fit_dimension':
                if ((int) $sourceValue > (int) $targetValue) {
                    return ['type' => 'error', 'message' => $rule->message];
                }
                break;
            case 'must_contain':
                if (stripos($sourceValue, $targetValue) === false) {
                    return ['type' => 'error', 'message' => $rule->message];
                }
                break;
        }

        return null;
    }
}
