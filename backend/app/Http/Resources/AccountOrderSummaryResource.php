<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class AccountOrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstItem = $this->relationLoaded('items') ? $this->items->first() : null;
        $product = $firstItem?->relationLoaded('product') ? $firstItem->product : null;
        $image = $product && $product->relationLoaded('images') ? $product->images->first() : null;
        $status = (string) $this->order_status;
        $statusLabel = $this->statusLabel($status);
        $itemsCount = (int) ($this->relationLoaded('items') ? $this->items->sum('quantity') : 0);
        $additionalItemCount = max(0, ($this->relationLoaded('items') ? $this->items->count() : 0) - 1);

        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'created_at' => $this->created_at?->toISOString(),
            'total' => (int) $this->total,
            'payment_status' => $this->payment_status,
            'order_status' => $status,
            'display_status' => [
                'code' => $status,
                'label' => $statusLabel,
            ],
            // Kept as a flat alias for older storefront consumers.
            'status_label' => $statusLabel,
            'items_count' => $itemsCount,
            'additional_item_count' => $additionalItemCount,
            'representative_item' => $firstItem ? [
                'id' => (int) $firstItem->id,
                'product_id' => $firstItem->product_id === null ? null : (int) $firstItem->product_id,
                'product_name' => (string) $firstItem->product_name,
                'quantity' => (int) $firstItem->quantity,
                'image' => $image ? [
                    'url' => $image->url,
                    'alt' => $image->alt ?: $firstItem->product_name,
                ] : null,
            ] : null,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xử lý',
            'confirmed' => 'Đã xác nhận',
            'processing' => 'Đang xử lý',
            'shipping' => 'Đang giao',
            'delivered' => 'Đã giao hàng',
            'cancelled' => 'Đã hủy',
            default => 'Đang cập nhật',
        };
    }
}
