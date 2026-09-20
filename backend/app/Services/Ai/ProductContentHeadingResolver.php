<?php

namespace App\Services\Ai;

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

        $category = $product->category;
        $names = [];
        while ($category) {
            $names[] = Str::lower(Str::ascii($category->name.' '.$category->slug));
            $category = $category->parent;
        }
        $haystack = implode(' ', $names);
        $configurationTerms = ['pc ', 'pc-', 'laptop', 'may tinh de ban', 'may dong bo', 'desktop', 'workstation', 'all in one'];

        foreach ($configurationTerms as $term) {
            if (str_contains($haystack, $term)) {
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
