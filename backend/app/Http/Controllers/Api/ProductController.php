<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use App\Models\Product;
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
            ->where('is_active', true);

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
        $sortField = $request->sort ?? 'created_at';
        $sortDirection = $request->order ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate($request->per_page ?? 20);

        return response()->json($products);
    }

    /**
     * Get featured products
     */
    public function featured(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->limit(8)
            ->get();

        return response()->json($products);
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
            'reviews' => fn ($query) => $query
                ->where('is_approved', true)
                ->latest()
                ->with(['user:id,name']),
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Append parsed specifications
        $product->append('parsed_specifications');

        // Get related products
        $related = Product::with(['images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        return response()->json([
            'product' => $product,
            'related' => $related,
        ]);
    }

    /**
     * Get products by component type (for PC Builder)
     */
    public function byComponentType(string $slug): JsonResponse
    {
        $products = Product::with(['brand', 'images'])
            ->where('is_active', true)
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
            ->where('is_active', true)
            ->firstOrFail();

        // Product must have a component type for compatibility
        if (!$product->component_type_id) {
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
            ->where('is_active', true)
            ->whereIn('component_type_id', $relatedTypeIds)
            ->get()
            ->groupBy('component_type_id');

        $suggestions = [];

        foreach ($relatedTypeIds as $typeId) {
            $type = $componentTypes->get($typeId);
            if (!$type) continue;

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
                    $compatible[] = [
                        'id' => $candidate->id,
                        'name' => $candidate->name,
                        'slug' => $candidate->slug,
                        'price' => $candidate->price,
                        'sale_price' => $candidate->sale_price,
                        'brand' => $candidate->brand,
                        'images' => $candidate->images,
                    ];
                }
            }

            if (!empty($compatible)) {
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

        if (!$sourceSpec || !$targetSpec) {
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
                if (!in_array($targetValue, $allowedValues)) {
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
