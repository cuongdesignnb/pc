<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CartService
{
    public function load(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.images',
            'items.product.brand',
            'items.product.category',
            'items.product.variants',
            'items.variant',
        ]);
    }

    public function summary(Cart $cart): array
    {
        $selected = $cart->items->where('is_selected', true);
        $itemCount = (int) $cart->items->sum('quantity');
        $originalSubtotal = 0;
        $subtotal = 0;
        $productDiscount = 0;
        $quantity = 0;

        foreach ($selected as $item) {
            $product = $item->product;
            $variant = $item->variant;
            $unitPrice = (int) ($variant?->display_price ?? $product?->purchasableUnitPrice() ?? $item->price);
            $originalUnitPrice = (int) ($variant?->price ?? $product?->price ?? $item->price);
            $itemQuantity = (int) $item->quantity;
            $originalSubtotal += $originalUnitPrice * $itemQuantity;
            $subtotal += $unitPrice * $itemQuantity;
            $productDiscount += max(0, $originalUnitPrice - $unitPrice) * $itemQuantity;
            $quantity += $itemQuantity;
        }

        $freeThreshold = $this->integerSetting('shipping_free_threshold', 500000);
        $defaultFee = $this->integerSetting('shipping_default_fee', 30000);
        $eligibleForFreeShipping = $quantity > 0 && $freeThreshold > 0 && $subtotal >= $freeThreshold;
        $amountRemaining = max(0, $freeThreshold - $subtotal);
        $estimatedFee = $quantity === 0 ? 0 : ($eligibleForFreeShipping ? 0 : $defaultFee);
        $couponDiscount = 0;
        $payableBeforeShipping = max(0, $subtotal - $couponDiscount);

        return [
            'item_count' => $itemCount,
            'selected_item_count' => $quantity,
            'line_count' => (int) $cart->items->count(),
            'selected_line_count' => (int) $selected->count(),
            'original_subtotal' => $originalSubtotal,
            'subtotal' => $subtotal,
            'payable_before_shipping' => $payableBeforeShipping,
            'coupon_discount' => $couponDiscount,
            'quantity' => $quantity,
            'product_discount' => $productDiscount,
            // Keep total/shipping_fee for older clients. The cart page uses
            // payable_before_shipping because the final fee depends on the
            // checkout address.
            'shipping_fee' => $estimatedFee,
            'total' => $payableBeforeShipping + $estimatedFee,
            'shipping' => [
                'default_fee' => $defaultFee,
                'free_threshold' => $freeThreshold,
                'amount_remaining_for_free_shipping' => $amountRemaining,
                'eligible_for_free_shipping' => $eligibleForFreeShipping,
                'estimated_fee' => $estimatedFee,
                // Compatibility aliases for the first cart-page iteration.
                'free_shipping_remaining' => $amountRemaining,
                'is_free' => $eligibleForFreeShipping,
            ],
        ];
    }

    public function recommendations(Cart $cart, int $limit = 8): Collection
    {
        $groups = $this->recommendationGroups($cart, $limit);

        return $groups['accessories']->concat($groups['recommendations'])->take($limit)->values();
    }

    /** @return array{accessories: Collection, recommendations: Collection} */
    public function recommendationGroups(Cart $cart, int $limit = 8): array
    {
        $productIds = $cart->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($productIds->isEmpty()) {
            return [
                'accessories' => collect(),
                'recommendations' => $this->fallbackRecommendations($productIds, $limit),
            ];
        }

        $relations = ProductRelation::query()
            ->with([
                'relatedProduct' => function (BelongsTo $query): void {
                    $query->sellableOnline()
                        ->with(['images', 'brand', 'category'])
                        ->withAvg('approvedReviews', 'rating')
                        ->withCount('approvedReviews')
                        ->withCount([
                            'variants as has_variants' => fn (Builder $variantQuery) => $variantQuery->where('is_active', true),
                        ]);
                },
            ])
            ->whereIn('product_id', $productIds)
            ->whereIn('relation_type', ['accessory', 'frequently_bought', 'related'])
            ->orderByRaw("CASE relation_type WHEN 'accessory' THEN 0 WHEN 'frequently_bought' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(max($limit * 6, $limit))
            ->get();

        $groups = [
            'accessories' => collect(),
            'recommendations' => collect(),
        ];
        foreach ($relations as $relation) {
            $product = $relation->relatedProduct;
            if (! $product || $productIds->contains((int) $product->id)) {
                continue;
            }
            $group = $relation->relation_type === 'accessory' ? 'accessories' : 'recommendations';
            if ($groups[$group]->contains('id', $product->id) || $groups[$group]->count() >= $limit) {
                continue;
            }
            $groups[$group]->push($product);
            if ($groups['accessories']->count() >= $limit && $groups['recommendations']->count() >= $limit) {
                break;
            }
        }

        if ($groups['recommendations']->count() < $limit) {
            $excludedIds = $productIds
                ->concat($groups['accessories']->pluck('id'))
                ->concat($groups['recommendations']->pluck('id'))
                ->unique()
                ->values();
            $groups['recommendations'] = $groups['recommendations']
                ->concat($this->fallbackRecommendations($excludedIds, $limit - $groups['recommendations']->count()))
                ->unique('id')
                ->take($limit)
                ->values();
        }

        return [
            'accessories' => $groups['accessories']->values(),
            'recommendations' => $groups['recommendations']->values(),
        ];
    }

    private function fallbackRecommendations(Collection $excludedIds, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return Product::query()
            ->sellableOnline()
            ->when($excludedIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $excludedIds->all()))
            ->with(['images', 'brand', 'category'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->withCount([
                'variants as has_variants' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function benefits(): array
    {
        return [
            $this->benefit('shipping', 'homepage_service_shipping_title', 'homepage_service_shipping_text', 'truck'),
            $this->benefit('authenticity', 'homepage_service_authenticity_title', 'homepage_service_authenticity_text', 'shield'),
            $this->benefit('returns', 'homepage_service_returns_title', 'homepage_service_returns_text', 'refresh'),
            $this->benefit('support', 'homepage_service_support_title', 'homepage_service_support_text', 'headset'),
        ];
    }

    public function paymentMethods(): array
    {
        $methods = [];
        $bank = trim((string) Setting::get('payment_bank_name', ''));
        if ($bank !== '') {
            $methods[] = ['key' => 'sepay', 'label' => 'Chuyển khoản', 'provider' => $bank];
        }
        if ((bool) Setting::get('payment_cod_enabled', true)) {
            $methods[] = ['key' => 'cod', 'label' => 'COD', 'provider' => 'Thanh toán khi nhận hàng'];
        }

        return $methods;
    }

    public function support(): array
    {
        $phone = trim((string) Setting::get('contact_phone', ''));
        $hotline = trim((string) Setting::get('contact_hotline', $phone));

        return [
            'hotline' => $hotline !== '' ? $hotline : $phone,
            'hours' => trim((string) Setting::get('business_hours', '')),
        ];
    }

    private function benefit(string $key, string $titleKey, string $textKey, string $icon): array
    {
        return [
            'key' => $key,
            'title' => (string) Setting::get($titleKey, ''),
            'description' => (string) Setting::get($textKey, ''),
            'icon' => $icon,
        ];
    }

    private function integerSetting(string $key, int $fallback): int
    {
        $value = Setting::get($key);

        return is_numeric($value) ? max(0, (int) $value) : $fallback;
    }
}
