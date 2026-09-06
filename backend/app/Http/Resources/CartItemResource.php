<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $variant = $this->variant;
        $unitPrice = $variant?->display_price ?? $product?->purchasableUnitPrice() ?? (int) $this->price;
        $originalUnitPrice = $variant
            ? (int) $variant->price
            : ($product ? (int) $product->price : (int) $this->price);
        $availableQuantity = $variant
            ? max(0, (int) $variant->stock_quantity)
            : max(0, (int) ($product?->quantity ?? 0));
        $isPurchasable = $product !== null
            && $product->isVisibleOnStorefront()
            && ($variant
                ? $variant->is_active && $availableQuantity >= (int) $this->quantity
                : $product->isSellableOnline() && $availableQuantity >= (int) $this->quantity);
        $availabilityLabel = $product === null
            ? 'Sản phẩm không còn tồn tại'
            : ($variant && ! $variant->is_active
                ? 'Ngừng kinh doanh'
                : ($variant && $availableQuantity <= 0 ? 'Hết hàng' : $product->availability_label));
        $priceChanged = (int) $this->price !== (int) $unitPrice;
        $savingPerUnit = max(0, $originalUnitPrice - (int) $unitPrice);
        $lineOriginal = $originalUnitPrice * (int) $this->quantity;
        $lineTotal = (int) $unitPrice * (int) $this->quantity;
        $productPayload = $product ? ProductCardResource::make($product)->resolve($request) : null;
        if (is_array($productPayload)) {
            $productPayload['image'] = $product->relationLoaded('images') && $product->images->first()
                ? [
                    'url' => $product->images->first()->url,
                    'alt' => $product->images->first()->alt,
                ]
                : null;
        }

        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'variant_id' => $this->variant_id === null ? null : (int) $this->variant_id,
            'quantity' => (int) $this->quantity,
            'selected' => (bool) $this->is_selected,
            // Keep the original snapshot string for clients that consumed the
            // legacy cart response; use unit_price for all new calculations.
            'price' => (string) $this->price,
            'unit_price' => (int) $unitPrice,
            'original_unit_price' => $originalUnitPrice,
            'line_subtotal' => $lineTotal,
            'line_discount' => $savingPerUnit * (int) $this->quantity,
            'price_changed' => $priceChanged,
            'previous_unit_price' => $priceChanged ? (int) $this->price : null,
            'pricing' => [
                'original_unit_price' => $originalUnitPrice,
                'unit_price' => (int) $unitPrice,
                'saving_per_unit' => $savingPerUnit,
                'line_original' => $lineOriginal,
                'line_total' => $lineTotal,
                'line_saving' => $savingPerUnit * (int) $this->quantity,
                'discount_percent' => $originalUnitPrice > 0
                    ? (int) round(($savingPerUnit / $originalUnitPrice) * 100)
                    : 0,
            ],
            'availability' => [
                'purchasable' => $isPurchasable,
                'available_quantity' => $availableQuantity,
                'label' => $availabilityLabel,
            ],
            'inventory' => [
                'max_quantity' => $availableQuantity,
                'available_quantity' => $availableQuantity,
                'purchasable' => $isPurchasable,
                'availability_label' => $availabilityLabel,
            ],
            'product' => $productPayload,
            'variant' => $variant ? [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'attributes' => is_array($variant->attributes) ? $variant->attributes : null,
                'pricing' => [
                    'price' => (int) $variant->price,
                    'sale_price' => $variant->sale_price === null ? null : (int) $variant->sale_price,
                    'display_price' => (int) $variant->display_price,
                ],
                'inventory' => [
                    'available_quantity' => max(0, (int) $variant->stock_quantity),
                    'is_available' => (bool) ($variant->is_active && $variant->stock_quantity > 0),
                ],
            ] : null,
        ];
    }
}
