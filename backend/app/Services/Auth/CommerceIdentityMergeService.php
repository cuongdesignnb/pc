<?php

namespace App\Services\Auth;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommerceIdentityMergeService
{
    /**
     * Move a guest cart into the account cart atomically, preserving the
     * product + variant line key and capping quantities at live stock.
     *
     * @return array{merged: bool, moved_items: int, warnings: array<int, array<string, mixed>>}
     */
    public function mergeGuestCartIntoUser(User $user, ?string $sessionId): array
    {
        $sessionId = trim((string) $sessionId);
        if ($sessionId === '' || strlen($sessionId) > 255) {
            return $this->emptyResult();
        }

        return DB::transaction(function () use ($user, $sessionId): array {
            $guestCart = Cart::query()
                ->whereNull('user_id')
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if (! $guestCart) {
                return $this->emptyResult();
            }

            $accountCart = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $accountCart) {
                $accountCart = Cart::create(['user_id' => $user->id]);
            }

            $guestItems = $guestCart->items()->lockForUpdate()->get();
            $warnings = [];
            $movedItems = 0;

            foreach ($guestItems as $guestItem) {
                $product = Product::query()
                    ->with('category')
                    ->lockForUpdate()
                    ->find($guestItem->product_id);
                $variantId = $guestItem->variant_id === null ? null : (int) $guestItem->variant_id;
                $variant = $variantId === null
                    ? null
                    : ProductVariant::query()
                        ->whereKey($variantId)
                        ->where('product_id', $guestItem->product_id)
                        ->lockForUpdate()
                        ->first();

                if (! $product || ($variantId !== null && ! $variant)) {
                    $warnings[] = $this->warning(
                        'unavailable',
                        'Một sản phẩm trong giỏ khách đã không còn khả dụng và chưa được chuyển vào tài khoản.',
                        $guestItem,
                        0,
                    );
                    $guestItem->delete();
                    continue;
                }

                $availableQuantity = $variant
                    ? ($product->isVisibleOnStorefront() && $variant->is_active
                        ? max(0, (int) $variant->stock_quantity)
                        : 0)
                    : ($product->isSellableOnline() ? max(0, (int) $product->stock_quantity) : 0);
                $unitPrice = (int) ($variant?->display_price ?? $product->purchasableUnitPrice());
                $existing = $accountCart->items()
                    ->where('product_id', $product->id)
                    ->when(
                        $variantId === null,
                        fn ($query) => $query->whereNull('variant_id'),
                        fn ($query) => $query->where('variant_id', $variantId),
                    )
                    ->lockForUpdate()
                    ->first();
                $existingQuantity = (int) ($existing?->quantity ?? 0);
                $guestQuantity = max(0, (int) $guestItem->quantity);
                $requestedQuantity = $existingQuantity + $guestQuantity;
                $appliedQuantity = min($requestedQuantity, $availableQuantity);

                if ($appliedQuantity <= 0) {
                    $warnings[] = $this->warning(
                        'out_of_stock',
                        'Một sản phẩm trong giỏ khách đã hết hàng và chưa được chuyển vào tài khoản.',
                        $guestItem,
                        0,
                        $requestedQuantity,
                    );
                    $guestItem->delete();
                    continue;
                }

                $attributes = [
                    'quantity' => $appliedQuantity,
                    'price' => $unitPrice,
                    'is_selected' => (bool) ($existing?->is_selected || $guestItem->is_selected),
                ];
                if ($existing) {
                    $existing->update($attributes);
                } else {
                    $accountCart->items()->create($attributes + [
                        'product_id' => $product->id,
                        'variant_id' => $variantId,
                    ]);
                }

                if ($appliedQuantity < $requestedQuantity) {
                    $warnings[] = $this->warning(
                        'quantity_capped',
                        'Số lượng một sản phẩm đã được điều chỉnh theo tồn kho hiện tại.',
                        $guestItem,
                        $appliedQuantity,
                        $requestedQuantity,
                    );
                }

                $movedItems++;
                $guestItem->delete();
            }

            $guestCart->delete();

            return [
                'merged' => true,
                'moved_items' => $movedItems,
                'warnings' => $warnings,
            ];
        });
    }

    /** @return array{merged: bool, moved_items: int, warnings: array<int, array<string, mixed>>} */
    private function emptyResult(): array
    {
        return ['merged' => false, 'moved_items' => 0, 'warnings' => []];
    }

    /** @return array<string, mixed> */
    private function warning(
        string $code,
        string $message,
        CartItem $item,
        int $appliedQuantity,
        ?int $requestedQuantity = null,
    ): array {
        return [
            'code' => $code,
            'message' => $message,
            'product_id' => (int) $item->product_id,
            'variant_id' => $item->variant_id === null ? null : (int) $item->variant_id,
            'requested_quantity' => $requestedQuantity ?? max(0, (int) $item->quantity),
            'applied_quantity' => $appliedQuantity,
        ];
    }
}
