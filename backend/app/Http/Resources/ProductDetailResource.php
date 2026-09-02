<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayPrice = $this->purchasableUnitPrice();
        $approvedReviews = $this->approvedReviews;
        $reviewCount = $approvedReviews->count();
        $reviewAverage = $reviewCount > 0
            ? round((float) $approvedReviews->avg('rating'), 1)
            : null;
        $structuredSpecs = $this->specifications->map(fn ($spec) => [
            'key' => $spec->specificationKey?->key,
            'label' => $spec->specificationKey?->label ?? $spec->specificationKey?->key,
            'value' => $spec->value,
            'unit' => $spec->specificationKey?->unit,
        ])->filter(fn (array $spec) => filled($spec['label']) && $spec['value'] !== null)->values();
        $specifications = $structuredSpecs->isNotEmpty()
            ? $structuredSpecs
            : collect($this->parsed_specifications)->map(fn (array $spec) => [
                'key' => null,
                'label' => $spec['label'],
                'value' => $spec['value'],
                'unit' => null,
            ])->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo' => $this->brand->logo,
            ] : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'component_type' => $this->componentType ? [
                'id' => $this->componentType->id,
                'name' => $this->componentType->name,
                'slug' => $this->componentType->slug,
            ] : null,
            'pricing' => [
                'price' => (int) $this->price,
                'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
                'display_price' => $displayPrice,
                'discount_percent' => $this->sale_price !== null && (int) $this->price > 0
                    ? max(0, (int) round((1 - ((int) $this->sale_price / (int) $this->price)) * 100))
                    : 0,
                'saving' => $this->sale_price !== null ? max(0, (int) $this->price - (int) $this->sale_price) : 0,
            ],
            'inventory' => [
                'quantity' => $this->quantity,
                'purchasable' => $this->is_purchasable,
                'availability_label' => $this->availability_label,
            ],
            // Keep the established public contract while new consumers use the
            // grouped inventory payload above.
            'quantity' => $this->quantity,
            'is_purchasable' => (bool) $this->is_purchasable,
            'availability_label' => $this->availability_label,
            'warranty_months' => $this->warranty_months,
            'rating' => [
                'average' => $reviewAverage,
                'count' => $reviewCount,
                'breakdown' => collect(range(5, 1))->mapWithKeys(fn (int $rating) => [
                    (string) $rating => $approvedReviews->where('rating', $rating)->count(),
                ])->all(),
            ],
            'images' => ProductImageResource::collection($this->images),
            'variants' => ProductVariantResource::collection($this->variants),
            'highlights' => $this->highlights->where('is_active', true)->values()->map(fn ($highlight) => [
                'id' => $highlight->id,
                'title' => $highlight->title,
                'icon' => $highlight->icon,
            ]),
            'detail_blocks' => $this->detailBlocks->where('is_active', true)->values()->map(fn ($block) => [
                'id' => $block->id,
                'type' => $block->type,
                'title' => $block->title,
                'payload' => $block->payload,
            ]),
            'specifications' => $specifications,
            'short_description' => $this->short_description,
            // Legacy rich-editor HTML is intentionally reduced to text. New richer
            // content is rendered only from the typed detail_blocks payload above.
            'description' => filled($this->description)
                ? trim(html_entity_decode(strip_tags($this->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : null,
            'seo' => [
                'title' => $this->meta_title,
                'description' => $this->meta_description,
            ],
        ];
    }
}
