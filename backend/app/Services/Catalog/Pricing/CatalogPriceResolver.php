<?php

namespace App\Services\Catalog\Pricing;

use App\Models\Product;

class CatalogPriceResolver
{
    /** @return array{retail_price:int,selected_price:?int,price_books:array<int,int>,currency:string} */
    public function forProduct(Product $product): array
    {
        $retail = max(0, (int) round((float) ($product->kiot_retail_price ?? $product->price ?? 0)));
        $selected = $product->kiot_selected_price !== null
            ? max(0, (int) round((float) $product->kiot_selected_price))
            : null;
        $books = [];
        $rows = $product->relationLoaded('catalogPrices')
            ? $product->catalogPrices
            : $product->catalogPrices()->get();
        foreach ($rows as $row) {
            if ($row->price_book_id !== null && $row->price_source === 'price_book') {
                $books[(int) $row->price_book_id] = max(0, (int) $row->price);
            }
        }

        return [
            'retail_price' => $retail,
            'selected_price' => $selected,
            'price_books' => $books,
            'currency' => 'VND',
        ];
    }
}
