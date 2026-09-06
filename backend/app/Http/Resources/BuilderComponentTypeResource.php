<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ComponentType */
class BuilderComponentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'display_order' => (int) $this->display_order,
            'is_required' => (bool) $this->is_required,
            'specification_keys' => $this->whenLoaded('specificationKeys', fn () => $this->specificationKeys
                ->sortBy('display_order')
                ->values()
                ->map(fn ($key) => [
                    'id' => (int) $key->id,
                    'key' => $key->key,
                    'label' => $key->label,
                    'data_type' => $key->data_type,
                    'unit' => $key->unit,
                    'is_filterable' => (bool) $key->is_filterable,
                    'display_order' => (int) $key->display_order,
                ])),
        ];
    }
}
