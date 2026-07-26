<?php

namespace App\Services\Catalog;

use App\Models\Product;

class ProductPurchasabilityService
{
    public function isPurchasable(Product $product, int $quantity = 1): bool
    {
        return $quantity > 0
            && $product->isSellableOnline()
            && $product->stock_quantity >= $quantity;
    }

    public function unitPrice(Product $product): int
    {
        return $product->purchasableUnitPrice();
    }
}
