<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * The laptop categories of the shop: the ones picked in Cài đặt → Cộng tác viên
 * ("Danh mục laptop", affiliate_laptop_category_slugs), or else the top-level
 * categories named "Laptop …". Sub-categories count too. Used by the CTV
 * discount and the "laptop free shipping" rule.
 */
class LaptopCategories
{
    /** @return Collection<int, int> */
    public function ids(): Collection
    {
        $slugs = Setting::get('affiliate_laptop_category_slugs', []);
        $slugs = is_array($slugs) ? array_values(array_filter($slugs, 'is_string')) : [];

        return $slugs !== []
            ? Category::whereIn('slug', $slugs)->pluck('id')
            : Category::whereNull('parent_id')->where('name', 'like', 'Laptop%')->pluck('id');
    }

    public function isLaptop(?Product $product, ?Collection $laptopIds = null): bool
    {
        $laptopIds ??= $this->ids();
        $category = $product?->category;
        for ($depth = 0; $category instanceof Category && $depth < 5; $depth++) {
            if ($laptopIds->contains($category->id)) {
                return true;
            }
            $category = $category->parent;
        }

        return false;
    }

    /**
     * Money spent on laptops in these order lines.
     *
     * @param  array<int, array{product_id: int, line_total: int}>  $lines
     */
    public function subtotal(array $lines): int
    {
        if ($lines === []) {
            return 0;
        }
        $laptopIds = $this->ids();
        if ($laptopIds->isEmpty()) {
            return 0;
        }
        $products = Product::with('category.parent.parent.parent.parent')
            ->whereIn('id', array_map(fn (array $line): int => (int) $line['product_id'], $lines))
            ->get()
            ->keyBy('id');

        $total = 0;
        foreach ($lines as $line) {
            if ($this->isLaptop($products->get((int) $line['product_id']), $laptopIds)) {
                $total += max(0, (int) $line['line_total']);
            }
        }

        return $total;
    }
}
