<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductContentHeadingResolver
{
    public function resolve(Product $product, string $override = 'auto'): string
    {
        if ($override === 'configuration') {
            return 'Cấu hình chi tiết';
        }
        if ($override === 'specifications') {
            return 'Thông số kỹ thuật';
        }

        $category = $product->relationLoaded('category')
            ? $product->getRelation('category')
            : $product->category;
        $names = [];
        while ($category instanceof Category) {
            $configuredHeading = $category->getAttribute('technical_heading');
            if ($configuredHeading === 'configuration') {
                return 'Cấu hình chi tiết';
            }
            if ($configuredHeading === 'specifications') {
                return 'Thông số kỹ thuật';
            }

            $names[] = Str::lower(Str::ascii(trim((string) $category->getAttribute('name').' '.(string) $category->getAttribute('slug'))));
            $category = $category->relationLoaded('parent')
                ? $category->getRelation('parent')
                : null;
        }
        $haystack = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', implode(' ', $names)));
        $haystack = ' '.$haystack.' ';
        $configurationTerms = ['pc', 'laptop', 'may tinh de ban', 'may dong bo', 'desktop', 'workstation', 'all in one'];

        foreach ($configurationTerms as $term) {
            if (str_contains($haystack, ' '.$term.' ')) {
                return 'Cấu hình chi tiết';
            }
        }

        return 'Thông số kỹ thuật';
    }

    public function contentHeading(bool $hasVerifiedFacts): string
    {
        return $hasVerifiedFacts ? 'Điểm nổi bật' : 'Thông tin sản phẩm';
    }
}
