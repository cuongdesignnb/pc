<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

/**
 * Paid warranty options the customer picks when buying (e.g. 1, 3 or 6
 * months). A product uses its own packages, else those of its category or
 * the nearest parent category that has some, else the shop-wide default
 * packages in Cài đặt → Bảo hành.
 *
 * A package adds a fixed amount, or a percentage of the unit price (rounded
 * up to the next 1,000đ), to each unit. Negative values make a package
 * cheaper than the listed price (e.g. a shorter warranty), but never below 0đ.
 */
final class WarrantyPackages
{
    /** @return list<array{months: int, price: int, percent: float|null}> */
    public function forProduct(?Product $product, ?int $basePrice = null): array
    {
        if (! $product) {
            return [];
        }
        $basePrice ??= $product->purchasableUnitPrice();

        return array_map(fn (array $package): array => [
            'months' => $package['months'],
            'price' => self::priceFor($package, $basePrice),
            // Lets the product page reprice a percent package for a variant.
            'percent' => $package['percent'],
        ], $this->rulesFor($product));
    }

    /**
     * The package the customer chose, or null when the product's own default
     * warranty applies (nothing chosen). Throws when the choice isn't offered.
     *
     * @return array{months: int, price: int}|null
     */
    public function select(?Product $product, ?int $months, ?int $basePrice = null): ?array
    {
        if ($months === null) {
            return null;
        }

        foreach ($this->forProduct($product, $basePrice) as $package) {
            if ($package['months'] === $months) {
                return ['months' => $package['months'], 'price' => $package['price']];
            }
        }

        throw new InvalidWarrantyPackage;
    }

    /** @return list<array{months: int, price: int, percent: float|null}> */
    private function rulesFor(Product $product): array
    {
        if ($packages = self::normalise($product->warranty_packages)) {
            return $packages;
        }
        $category = $product->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if ($packages = self::normalise($category->warranty_packages)) {
                return $packages;
            }
            $category = $category->parent;
        }

        return self::normalise(Setting::get('warranty_default_packages', []));
    }

    /** @param array{price: int, percent: float|null} $package */
    public static function priceFor(array $package, int $basePrice): int
    {
        $basePrice = max(0, $basePrice);
        $price = $package['percent'] !== null
            ? (int) (ceil($basePrice * $package['percent'] / 100 / 1000) * 1000)
            : $package['price'];

        // A discount package can bring the unit price down to 0đ, not below.
        return max(-$basePrice, $price);
    }

    /**
     * Stored form: [{months, price}] for a fixed amount or [{months, percent}]
     * for a share of the price.
     *
     * @return list<array{months: int, price: int, percent: float|null}>
     */
    public static function normalise(mixed $packages): array
    {
        return collect(is_array($packages) ? $packages : [])
            ->filter(fn ($package): bool => is_array($package) && (int) ($package['months'] ?? 0) > 0)
            ->map(function (array $package): array {
                $percent = isset($package['percent']) && $package['percent'] !== '' && $package['percent'] !== null
                    ? min(100, max(-100, (float) $package['percent']))
                    : null;

                return [
                    'months' => (int) $package['months'],
                    'price' => $percent === null ? (int) ($package['price'] ?? 0) : 0,
                    'percent' => $percent,
                ];
            })
            ->unique('months')
            ->sortBy('months')
            ->values()
            ->all();
    }

    /** What admin forms save: percent packages keep only the percentage. */
    public static function forStorage(mixed $packages): ?array
    {
        $packages = array_map(
            fn (array $package): array => $package['percent'] === null
                ? ['months' => $package['months'], 'price' => $package['price']]
                : ['months' => $package['months'], 'percent' => $package['percent']],
            self::normalise($packages),
        );

        return $packages ?: null;
    }

    /** Validation for a warranty package list field (categories, products, settings). */
    public static function validationRules(string $field = 'warranty_packages'): array
    {
        return [
            $field => 'nullable|array|max:10',
            "{$field}.*.months" => 'required|integer|min:1|max:120|distinct',
            "{$field}.*.price" => 'nullable|integer|min:-1000000000|max:1000000000',
            "{$field}.*.percent" => 'nullable|numeric|min:-100|max:100',
        ];
    }
}
