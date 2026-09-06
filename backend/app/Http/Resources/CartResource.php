<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly array $summary = [],
        private readonly Collection $recommendations = new Collection(),
        private readonly Collection $accessories = new Collection(),
        private readonly array $benefits = [],
        private readonly array $paymentMethods = [],
        private readonly array $support = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $totalQuantity = (int) $this->items->sum('quantity');
        $selectedQuantity = (int) $this->items->where('is_selected', true)->sum('quantity');

        return [
            'id' => (int) $this->id,
            'items' => CartItemResource::collection($this->items),
            'summary' => $this->summary,
            'coupon' => null,
            'cart' => [
                'id' => (int) $this->id,
                'item_count' => (int) $this->items->count(),
                'quantity' => $totalQuantity,
                'selected_quantity' => $selectedQuantity,
            ],
            'accessories' => ProductCardResource::collection($this->accessories),
            'recommendations' => ProductCardResource::collection($this->recommendations),
            'benefits' => $this->benefits,
            'payment_methods' => $this->paymentMethods,
            'support' => $this->support,
            // Backward-compatible fields used by the existing header/cart
            // composable. New code should consume summary instead.
            'total' => (int) ($this->summary['total'] ?? 0),
            'count' => $totalQuantity,
            'selected_count' => $selectedQuantity,
        ];
    }
}
