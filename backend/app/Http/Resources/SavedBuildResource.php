<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SavedBuild */
class SavedBuildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'build' => (object) collect($this->products ?? [])->mapWithKeys(
                fn ($productId, $componentTypeId) => [(string) $componentTypeId => (int) $productId]
            )->all(),
            'total_price' => (int) $this->total_price,
            'total_tdp' => (int) $this->total_tdp,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
