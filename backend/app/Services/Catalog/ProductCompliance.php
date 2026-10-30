<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

/**
 * Product information required by Nghị định 37/2026/NĐ-CP (Phụ lục I): year
 * of manufacture, warnings, usage and storage instructions, and the
 * "sản phẩm tân trang làm mới" label. Warnings and instructions fall back from
 * the product to its category (nearest parent first) to the shop default.
 */
final class ProductCompliance
{
    public const REFURBISHED_LABEL = 'Sản phẩm tân trang làm mới';

    /** @return array{manufacture_year: int|null, is_refurbished: bool, refurbished_label: string|null, warning_info: string|null, usage_instructions: string|null} */
    public function forProduct(Product $product): array
    {
        $categories = [];
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            $categories[] = $category;
            $category = $category->parent;
        }

        $refurbished = $product->is_refurbished ?? collect($categories)->contains(fn (Category $c) => (bool) $c->is_refurbished);

        return [
            'manufacture_year' => $product->manufacture_year ? (int) $product->manufacture_year : null,
            'is_refurbished' => $refurbished,
            'refurbished_label' => $refurbished ? self::REFURBISHED_LABEL : null,
            'warning_info' => $this->firstText($product->warning_info, $categories, 'warning_info', 'product_default_warning_info'),
            'usage_instructions' => $this->firstText($product->usage_instructions, $categories, 'usage_instructions', 'product_default_usage_instructions'),
        ];
    }

    /** @param list<Category> $categories */
    private function firstText(?string $own, array $categories, string $field, string $settingKey): ?string
    {
        foreach ([$own, ...array_map(fn (Category $c) => $c->{$field}, $categories), Setting::get($settingKey)] as $text) {
            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return null;
    }
}
