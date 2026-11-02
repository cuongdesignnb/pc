<?php

namespace App\Services\Seo;

use App\Models\Category;

/**
 * Price-range pages such as /laptop-duoi-5-trieu: the products of one
 * category, in stock, within a price range, served as a page of their own.
 */
class PriceLandingPages
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return (array) config('seo.price_landings', []);
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        $landing = $this->all()[$slug] ?? null;

        return is_array($landing) ? ['slug' => $slug] + $landing : null;
    }

    /** @return list<array{slug:string,label:string,path:string,min:?int,max:?int}> */
    public function forCategory(Category $category): array
    {
        $links = [];
        foreach ($this->all() as $slug => $landing) {
            if (($landing['category'] ?? null) !== $category->slug) {
                continue;
            }
            $links[] = [
                'slug' => $slug,
                'label' => (string) ($landing['label'] ?? $landing['title'] ?? $slug),
                'path' => '/'.$slug,
                'min' => isset($landing['min_price']) ? (int) $landing['min_price'] : null,
                'max' => isset($landing['max_price']) ? (int) $landing['max_price'] : null,
            ];
        }

        return $links;
    }
}
