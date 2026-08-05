<?php

namespace App\Services\Catalog\Pricing;

use App\Models\CatalogPriceBook;

class CatalogPriceValidationService
{
    public function validateSource(string $source, ?int $priceBookId = null): array
    {
        if (in_array($source, ['retail_price', 'selected_price', 'all_price_books'], true)) {
            return [];
        }
        if ($source === 'price_book' && $priceBookId && CatalogPriceBook::query()->whereKey($priceBookId)->where('is_active', true)->exists()) {
            return [];
        }
        if (preg_match('/^price_book:(\d+)$/', $source, $matches) === 1
            && CatalogPriceBook::query()->whereKey((int) $matches[1])->where('is_active', true)->exists()) {
            return [];
        }

        return ['price_source' => 'PRICE_SOURCE_UNAVAILABLE'];
    }
}
