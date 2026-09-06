<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class BuilderProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayPrice = $this->purchasableUnitPrice();
        $reviewAverage = $this->approved_reviews_avg_rating;
        $reviewCount = $this->approved_reviews_count;

        if ($this->relationLoaded('approvedReviews')) {
            $reviews = $this->approvedReviews;
            $reviewCount = $reviews->count();
            $reviewAverage = $reviewCount > 0 ? $reviews->avg('rating') : null;
        }

        $image = $this->relationLoaded('images') ? $this->images->first() : null;
        $specifications = $this->relationLoaded('specifications')
            ? $this->specifications->map(fn ($specification) => [
                'key' => $specification->specificationKey?->key,
                'label' => $specification->specificationKey?->label ?? $specification->specificationKey?->key,
                'value' => $specification->value,
                'unit' => $specification->specificationKey?->unit,
            ])->filter(fn (array $specification) => filled($specification['label']) && filled($specification['value']))->values()
            : collect();

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => (int) $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo' => $this->brand->logo,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => (int) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'component_type' => $this->whenLoaded('componentType', fn () => $this->componentType ? [
                'id' => (int) $this->componentType->id,
                'name' => $this->componentType->name,
                'slug' => $this->componentType->slug,
            ] : null),
            'image' => $image ? [
                'url' => $image->url,
                'alt' => $image->alt ?: $this->name,
            ] : null,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'pricing' => [
                'price' => (int) $this->price,
                'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
                'display_price' => (int) $displayPrice,
            ],
            'inventory' => [
                'purchasable' => (bool) $this->is_purchasable,
                'availability_label' => $this->availability_label,
            ],
            'rating' => [
                'average' => $reviewAverage === null ? null : round((float) $reviewAverage, 1),
                'count' => (int) ($reviewCount ?? 0),
            ],
            'sold_count' => (int) $this->sold_count,
            'has_variants' => (bool) (($this->has_variants ?? false) || ((int) ($this->variants_count ?? 0) > 0)),
            'specifications' => $specifications,
        ];
    }
}
