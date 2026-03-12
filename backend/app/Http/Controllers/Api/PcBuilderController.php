<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use App\Models\Product;
use App\Models\SavedBuild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcBuilderController extends Controller
{
    /**
     * Get all component types for PC Builder
     */
    public function componentTypes(): JsonResponse
    {
        $types = ComponentType::with('specificationKeys')
            ->orderBy('display_order')
            ->get();

        return response()->json($types);
    }

    /**
     * Get compatible products for a component type based on current build
     */
    public function compatibleProducts(Request $request, string $componentTypeSlug): JsonResponse
    {
        $componentType = ComponentType::where('slug', $componentTypeSlug)->firstOrFail();

        // Get current build selections
        $currentBuild = $request->input('build', []);

        // Get all products of this component type
        $products = Product::with(['brand', 'images', 'specifications.specificationKey'])
            ->where('component_type_id', $componentType->id)
            ->where('is_active', true)
            ->get();

        // Check compatibility for each product
        $compatibleProducts = $products->map(function ($product) use ($currentBuild, $componentType) {
            $compatibility = $this->checkProductCompatibility($product, $currentBuild, $componentType);

            return [
                'product' => $product,
                'is_compatible' => $compatibility['compatible'],
                'issues' => $compatibility['issues'],
            ];
        });

        return response()->json([
            'component_type' => $componentType,
            'products' => $compatibleProducts,
        ]);
    }

    /**
     * Check compatibility of entire build
     */
    public function checkBuild(Request $request): JsonResponse
    {
        $build = $request->input('build', []);
        $issues = [];
        $totalPrice = 0;
        $totalTdp = 0;

        // Get all products in build
        $products = Product::with(['componentType', 'specifications.specificationKey'])
            ->whereIn('id', array_values($build))
            ->get()
            ->keyBy('component_type_id');

        // Calculate totals
        foreach ($products as $product) {
            $totalPrice += $product->sale_price ?? $product->price;

            // Get TDP from specifications
            $tdpSpec = $product->specifications->first(fn ($s) => $s->specificationKey?->key === 'tdp');
            if ($tdpSpec) {
                $totalTdp += (int) $tdpSpec->value;
            }
        }

        // Check compatibility rules
        $rules = CompatibilityRule::with(['sourceType', 'targetType'])
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            $sourceProduct = $products->get($rule->source_type_id);
            $targetProduct = $products->get($rule->target_type_id);

            if (!$sourceProduct || !$targetProduct) {
                continue;
            }

            $issue = $this->evaluateRule($rule, $sourceProduct, $targetProduct);
            if ($issue) {
                $issues[] = $issue;
            }
        }

        // Check PSU wattage
        $psu = $products->first(fn ($p) => $p->componentType?->slug === 'psu');
        if ($psu && $totalTdp > 0) {
            $psuWattage = $psu->specifications->first(fn ($s) => $s->specificationKey?->key === 'wattage');
            if ($psuWattage) {
                $recommendedWattage = $totalTdp + 150; // 150W headroom
                if ((int) $psuWattage->value < $recommendedWattage) {
                    $issues[] = [
                        'type' => 'warning',
                        'message' => "Nguồn {$psuWattage->value}W có thể không đủ. Khuyến nghị: {$recommendedWattage}W",
                    ];
                }
            }
        }

        return response()->json([
            'compatible' => count(array_filter($issues, fn ($i) => $i['type'] === 'error')) === 0,
            'issues' => $issues,
            'total_price' => $totalPrice,
            'total_tdp' => $totalTdp,
            'products' => $products->values(),
        ]);
    }

    /**
     * Save a build configuration
     */
    public function saveBuild(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'build' => 'required|array',
        ]);

        // Calculate totals
        $products = Product::with('specifications.specificationKey')
            ->whereIn('id', array_values($validated['build']))
            ->get();

        $totalPrice = $products->sum(fn ($p) => $p->sale_price ?? $p->price);
        $totalTdp = $products->sum(function ($p) {
            $tdp = $p->specifications->first(fn ($s) => $s->specificationKey?->key === 'tdp');
            return $tdp ? (int) $tdp->value : 0;
        });

        $savedBuild = SavedBuild::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'products' => $validated['build'],
            'total_price' => $totalPrice,
            'total_tdp' => $totalTdp,
        ]);

        return response()->json([
            'message' => 'Đã lưu cấu hình',
            'build' => $savedBuild,
        ], 201);
    }

    /**
     * Get user's saved builds
     */
    public function savedBuilds(Request $request): JsonResponse
    {
        $builds = SavedBuild::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($builds);
    }

    /**
     * Check product compatibility against current build
     */
    private function checkProductCompatibility(Product $product, array $currentBuild, ComponentType $componentType): array
    {
        $issues = [];

        // Get rules where this component type is source or target
        $rules = CompatibilityRule::where('is_active', true)
            ->where(function ($q) use ($componentType) {
                $q->where('source_type_id', $componentType->id)
                    ->orWhere('target_type_id', $componentType->id);
            })
            ->get();

        foreach ($rules as $rule) {
            // Get the other product in the build
            $otherTypeId = $rule->source_type_id === $componentType->id
                ? $rule->target_type_id
                : $rule->source_type_id;

            $otherProductId = $currentBuild[$otherTypeId] ?? null;

            if (!$otherProductId) {
                continue;
            }

            $otherProduct = Product::with('specifications.specificationKey')
                ->find($otherProductId);

            if (!$otherProduct) {
                continue;
            }

            // Determine source and target
            $sourceProduct = $rule->source_type_id === $componentType->id ? $product : $otherProduct;
            $targetProduct = $rule->target_type_id === $componentType->id ? $product : $otherProduct;

            $issue = $this->evaluateRule($rule, $sourceProduct, $targetProduct);

            if ($issue) {
                $issues[] = $issue;
            }
        }

        return [
            'compatible' => count(array_filter($issues, fn ($i) => $i['type'] === 'error')) === 0,
            'issues' => $issues,
        ];
    }

    /**
     * Evaluate a compatibility rule
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
                    return [
                        'type' => 'error',
                        'message' => $rule->message,
                    ];
                }
                break;

            case 'must_fit':
                $allowedValues = $rule->allowed_values[$sourceValue] ?? [];
                if (!in_array($targetValue, $allowedValues)) {
                    return [
                        'type' => 'error',
                        'message' => $rule->message,
                    ];
                }
                break;

            case 'must_fit_dimension':
                if ((int) $sourceValue > (int) $targetValue) {
                    return [
                        'type' => 'error',
                        'message' => $rule->message,
                    ];
                }
                break;

            case 'must_contain':
                if (stripos($sourceValue, $targetValue) === false) {
                    return [
                        'type' => 'error',
                        'message' => $rule->message,
                    ];
                }
                break;

            case 'power_check':
                // This is handled separately in checkBuild
                break;
        }

        return null;
    }
}
