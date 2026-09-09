<?php

namespace App\Http\Resources;

use App\Models\ComponentType;
use App\Models\Product;
use App\Models\SavedBuild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin SavedBuild */
class AccountSavedBuildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Collection<string, Product> $products */
        $products = $this->relationLoaded('accountProducts')
            ? $this->getRelation('accountProducts')
            : collect();
        /** @var Collection<int, ComponentType> $types */
        $types = $this->relationLoaded('accountComponentTypes')
            ? $this->getRelation('accountComponentTypes')
            : collect();

        $parts = $products->map(function (Product $product, string|int $typeId) use ($types, $request): array {
            $type = $types->get((int) $typeId) ?: ($product->relationLoaded('componentType') ? $product->componentType : null);

            return [
                'component_type' => $type ? [
                    'id' => (int) $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                ] : null,
                'product' => BuilderProductResource::make($product)->resolve($request),
            ];
        })->values();

        $priority = [
            'case' => 0,
            'gpu' => 1,
            'vga' => 1,
            'cpu' => 2,
            'mainboard' => 3,
        ];
        $previewImages = $parts
            ->sortBy(fn (array $part): int => $priority[data_get($part, 'component_type.slug')] ?? 99)
            ->map(fn (array $part): ?string => data_get($part, 'product.image.url'))
            ->filter(fn (?string $url): bool => filled($url))
            ->unique()
            ->take(3)
            ->values();

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'build' => (object) collect($this->products ?? [])->mapWithKeys(
                fn ($productId, $componentTypeId) => [(string) $componentTypeId => (int) $productId]
            )->all(),
            'total_price' => (int) $this->total_price,
            'total_tdp' => (int) $this->total_tdp,
            'created_at' => $this->created_at?->toISOString(),
            'parts' => $parts->all(),
            'preview_images' => $previewImages->all(),
        ];
    }
}
