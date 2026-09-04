<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayPrice = $this->purchasableUnitPrice();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo' => $this->brand->logo,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'pricing' => [
                'price' => (int) $this->price,
                'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
                'display_price' => $displayPrice,
            ],
            'inventory' => [
                'purchasable' => $this->is_purchasable,
                'availability_label' => $this->availability_label,
            ],
            'rating' => [
                'average' => $this->approved_reviews_avg_rating === null
                    ? null
                    : round((float) $this->approved_reviews_avg_rating, 1),
                'count' => (int) ($this->approved_reviews_count ?? 0),
            ],
            // Backward-compatible presentation values. No operational/sync metadata is exposed.
            'price' => (int) $this->price,
            'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
            'quantity' => $this->quantity,
            'is_purchasable' => $this->is_purchasable,
            'availability_label' => $this->availability_label,
            'warranty_months' => $this->warranty_months,
            'is_featured' => $this->is_featured,
        ];
    }
}
