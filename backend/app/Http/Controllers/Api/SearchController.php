<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\PublicUrlResolver;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, PublicUrlResolver $urls)
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['products' => [], 'posts' => []]);
        }

        $products = Product::visibleOnStorefront()
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('sku', 'LIKE', "%{$q}%");
            })
            // Do not apply a global limit to the has-many relation. On a
            // search result containing several products, `limit(1)` returns
            // one image for the whole eager-load query instead of one image
            // per product. That made the image disappear for most results.
            ->with([
                'category:id,slug,name',
                'images' => fn ($img) => $img
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->select('id', 'name', 'slug', 'price', 'sale_price', 'category_id', 'provider', 'inventory_source', 'kiot_retail_price', 'kiot_selected_price')
            ->limit(6)
            ->get()
            ->map(function (Product $product) use ($urls): array {
                $pricing = $product->storefrontPricing();
                $image = $product->images->first(fn ($image) => filled($image->url));

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $pricing['price'],
                    'sale_price' => $pricing['sale_price'],
                    'display_price' => $pricing['display_price'],
                    'is_contact_price' => $pricing['is_contact_price'],
                    'image' => $image?->url,
                    'url' => $urls->productPath($product),
                ];
            });

        $posts = Post::where('status', 'published')
            ->where('title', 'LIKE', "%{$q}%")
            ->with('category:id,slug,name')
            ->select('id', 'title', 'slug', 'post_category_id', 'published_at')
            ->limit(4)
            ->latest('published_at')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'url' => $urls->postPath($p),
                'category' => $p->category?->name,
            ]);

        return response()->json([
            'products' => $products,
            'posts' => $posts,
        ]);
    }
}
