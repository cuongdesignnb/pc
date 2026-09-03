<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isVerifiedPurchase = $this->order !== null
            && $this->order->order_status !== 'cancelled'
            && $this->order->items->contains('product_id', $this->product_id);

        return [
            'id' => $this->id,
            'reviewer_name' => $this->user?->name ?? $this->guest_name ?? 'Khách hàng',
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'admin_reply' => $this->admin_reply,
            'verified_purchase' => $isVerifiedPurchase,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
