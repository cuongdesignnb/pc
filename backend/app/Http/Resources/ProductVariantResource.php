<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'attributes' => $this->attributes,
            'pricing' => [
                'price' => (int) $this->price,
                'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
                'display_price' => $this->display_price,
            ],
            'inventory' => [
                'quantity' => $this->stock_quantity,
                'is_available' => $this->is_available,
            ],
            // Kept during the storefront transition for existing cart and builder clients.
            'price' => (int) $this->price,
            'sale_price' => $this->sale_price === null ? null : (int) $this->sale_price,
            'display_price' => $this->display_price,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'is_available' => $this->is_available,
        ];
    }
}
