<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;

/**
 * "Khuyến mại" lines shown on the product page (e.g. "Tặng túi chống sốc").
 * A product uses its own lines, else those of its category or the nearest
 * parent category that has some, so a list typed once on "Laptop" covers
 * every laptop.
 */
final class ProductPromotions
{
    public const MAX_LINES = 20;

    /** @return list<string> */
    public function forProduct(?Product $product): array
    {
        if (! $product) {
            return [];
        }
        if ($lines = self::lines($product->promotion_text)) {
            return $lines;
        }
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if ($lines = self::lines($category->promotion_text)) {
                return $lines;
            }
            $category = $category->parent;
        }

        return [];
    }

    /** @return list<string> One offer per non-empty line, without list markers. */
    public static function lines(?string $text): array
    {
        return collect(preg_split('/\R/u', (string) $text) ?: [])
            ->map(fn (string $line): string => trim(strip_tags(preg_replace('/^\s*(?:[-*•+✓✔✅]|\d+[.)])\s*/u', '', $line) ?? '')))
            ->filter(fn (string $line): bool => $line !== '')
            ->map(fn (string $line): string => mb_substr($line, 0, 200))
            ->take(self::MAX_LINES)
            ->values()
            ->all();
    }

    /** Trimmed text, or null when it holds no offer. */
    public static function forStorage(?string $text): ?string
    {
        return ($lines = self::lines($text)) ? implode("\n", $lines) : null;
    }
}
