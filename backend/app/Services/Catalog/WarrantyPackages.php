<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;

/**
 * Paid warranty options set on a category (e.g. used laptops: 1, 3 or 6
 * months). Products use the packages of their own category, or of the
 * nearest parent category that has some.
 */
final class WarrantyPackages
{
    /** @return list<array{months: int, price: int}> */
    public function forProduct(?Product $product): array
    {
        $category = $product?->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            $packages = self::normalise($category->warranty_packages);
            if ($packages !== []) {
                return $packages;
            }
            $category = $category->parent;
        }

        return [];
    }

    /**
     * The package the customer chose, or null when the product's own default
     * warranty applies (nothing chosen). Throws when the choice isn't offered.
     *
     * @return array{months: int, price: int}|null
     */
    public function select(?Product $product, ?int $months): ?array
    {
        if ($months === null) {
            return null;
        }

        foreach ($this->forProduct($product) as $package) {
            if ($package['months'] === $months) {
                return $package;
            }
        }

        throw new InvalidWarrantyPackage;
    }

    /** @return list<array{months: int, price: int}> */
    public static function normalise(mixed $packages): array
    {
        return collect(is_array($packages) ? $packages : [])
            ->filter(fn ($package): bool => is_array($package) && (int) ($package['months'] ?? 0) > 0)
            ->map(fn (array $package): array => [
                'months' => (int) $package['months'],
                'price' => max(0, (int) ($package['price'] ?? 0)),
            ])
            ->unique('months')
            ->sortBy('months')
            ->values()
            ->all();
    }
}
