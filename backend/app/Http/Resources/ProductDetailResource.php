<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\Seo\PublicUrlResolver;

/** @mixin \App\Models\Product */
class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urls = app(PublicUrlResolver::class);
        $canonicalPath = $urls->productPath($this->resource);
        $canonicalUrl = $urls->absolute($canonicalPath);
        $pricing = $this->storefrontPricing();
        $regularPrice = $pricing['price'];
        $salePrice = $pricing['sale_price'];
        $displayPrice = $pricing['display_price'];
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
                'canonical_path' => $urls->categoryPath($this->category),
            ] : null,
            'component_type' => $this->componentType ? [
                'id' => $this->componentType->id,
                'name' => $this->componentType->name,
                'slug' => $this->componentType->slug,
            ] : null,
            'is_featured' => (bool) $this->is_featured,
            'pricing' => $pricing + [
                'discount_percent' => $salePrice !== null && $regularPrice > 0
                    ? (int) round((1 - ($salePrice / $regularPrice)) * 100)
                    : 0,
                'saving' => $salePrice !== null ? $regularPrice - $salePrice : 0,
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
            'sold_count' => (int) $this->sold_count,
            'rating' => [
                'average' => $reviewAverage,
                'count' => $reviewCount,
                'breakdown' => collect(range(5, 1))->mapWithKeys(fn (int $rating) => [
                    (string) $rating => $approvedReviews->where('rating', $rating)->count(),
                ])->all(),
            ],
            'questions_count' => (int) ($this->approved_questions_count ?? 0),
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
                'canonical_path' => $canonicalPath,
                'canonical_url' => $canonicalUrl,
                'robots' => 'index,follow',
            ],
            'public_url' => $canonicalUrl,
        ];
    }
}
