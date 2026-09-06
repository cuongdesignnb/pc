<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuilderComponentTypeResource;
use App\Http\Resources\BuilderProductResource;
use App\Http\Resources\BuildPresetResource;
use App\Http\Resources\SavedBuildResource;
use App\Models\BuildPreset;
use App\Models\ComponentType;
use App\Models\SavedBuild;
use App\Services\PcBuilder\PcBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcBuilderController extends Controller
{
    public function __construct(private readonly PcBuilderService $builder) {}

    public function componentTypes(): JsonResponse
    {
        $types = ComponentType::query()
            ->with(['specificationKeys' => fn ($query) => $query->orderBy('display_order')])
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'component_types' => BuilderComponentTypeResource::collection($types)->resolve(),
        ]);
    }

    public function compatibleProducts(Request $request, string $componentTypeSlug): JsonResponse
    {
        $validated = $request->validate([
            'build' => ['nullable', 'array'],
            'build.*' => ['integer', 'min:1'],
            'filters' => ['nullable', 'array'],
            'filters.query' => ['nullable', 'string', 'max:120'],
            'filters.brand_ids' => ['nullable', 'array', 'max:50'],
            'filters.brand_ids.*' => ['integer', 'min:1'],
            'filters.brands' => ['nullable', 'array', 'max:50'],
            'filters.brands.*' => ['integer', 'min:1'],
            'filters.price_min' => ['nullable', 'integer', 'min:0'],
            'filters.price_max' => ['nullable', 'integer', 'min:0'],
            'filters.specs' => ['nullable', 'array'],
            'filters.only_compatible' => ['nullable', 'boolean'],
            'filters.on_sale' => ['nullable', 'boolean'],
            'filters.sort' => ['nullable', 'in:compatibility,popular,price_asc,price_desc,rating'],
            'filters.page' => ['nullable', 'integer', 'min:1'],
            'filters.per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $componentType = ComponentType::query()
            ->with(['specificationKeys' => fn ($query) => $query->orderBy('display_order')])
            ->where('slug', $componentTypeSlug)
            ->firstOrFail();
        $result = $this->builder->compatibleProducts(
            $componentType,
            $validated['build'] ?? [],
            $validated['filters'] ?? [],
        );

        $products = collect($result['products'])->map(fn (array $item) => [
            'product' => BuilderProductResource::make($item['product'])->resolve(),
            'is_compatible' => $item['is_compatible'],
            'issues' => $item['issues'],
        ])->values();

        return response()->json([
            'component_type' => BuilderComponentTypeResource::make($componentType)->resolve(),
            'products' => $products,
            'meta' => $result['meta'],
            'filters' => $result['filters'],
        ]);
    }

    public function checkBuild(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'build' => ['nullable', 'array'],
            'build.*' => ['integer', 'min:1'],
        ]);
        $result = $this->builder->checkBuild($validated['build'] ?? []);
        $products = BuilderProductResource::collection($result['products'])->resolve();

        return response()->json([
            'compatible' => ! collect($result['issues'])->contains(fn (array $issue) => $issue['type'] === 'error'),
            'completion' => $result['completion'],
            'issues' => $result['issues'],
            'totals' => $result['totals'],
            'products' => $products,
            // Kept for older clients while they migrate to the grouped totals DTO.
            'total_price' => $result['totals']['price'],
            'total_tdp' => $result['totals']['tdp'],
        ]);
    }

    public function presets(): JsonResponse
    {
        $presets = BuildPreset::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'presets' => BuildPresetResource::collection($presets)->resolve(),
        ]);
    }

    public function preset(string $slug): JsonResponse
    {
        $preset = BuildPreset::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['preset' => BuildPresetResource::make($preset)->resolve()]);
    }

    public function saveBuild(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'build' => ['required', 'array'],
            'build.*' => ['integer', 'min:1'],
        ]);
        $result = $this->builder->checkBuild($validated['build']);
        $errors = collect($result['issues'])->where('type', 'error')->values();

        if (! $result['completion']['complete']) {
            $errors->push([
                'type' => 'error',
                'code' => 'incomplete_build',
                'message' => 'Cấu hình chưa đủ các linh kiện bắt buộc.',
                'source_type_id' => null,
                'target_type_id' => null,
            ]);
        }

        if ($errors->isNotEmpty()) {
            return response()->json([
                'message' => 'Không thể lưu cấu hình chưa hợp lệ.',
                'issues' => $errors->values(),
            ], 422);
        }

        $savedBuild = SavedBuild::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            // Keep component type IDs as object keys in JSON. A sequential PHP
            // array would be encoded as a list and lose the type-to-product map.
            'products' => collect($result['build'])->mapWithKeys(
                fn ($productId, $componentTypeId) => [(int) $componentTypeId => (int) $productId]
            ),
            'total_price' => $result['totals']['price'],
            'total_tdp' => $result['totals']['tdp'],
        ]);

        return response()->json([
            'message' => 'Đã lưu cấu hình.',
            'build' => SavedBuildResource::make($savedBuild)->resolve(),
        ], 201);
    }

    public function savedBuilds(Request $request): JsonResponse
    {
        $builds = SavedBuild::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'builds' => SavedBuildResource::collection($builds)->resolve(),
        ]);
    }
}
