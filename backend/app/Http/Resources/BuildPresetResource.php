<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BuildPreset */
class BuildPresetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'usage_type' => $this->usage_type,
            'products' => collect($this->products ?? [])->mapWithKeys(
                fn ($productId, $componentTypeSlug) => [(string) $componentTypeSlug => (int) $productId]
            )->all(),
            'starting_price' => $this->starting_price === null ? null : (int) $this->starting_price,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
