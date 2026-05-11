<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Post;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['products' => [], 'posts' => []]);
        }

        $products = Product::where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'LIKE', "%{$q}%")
                    ->orWhere('sku', 'LIKE', "%{$q}%");
            })
            ->with(['category:id,slug,name', 'images' => fn($img) => $img->orderBy('sort_order')->limit(1)])
            ->select('id', 'name', 'slug', 'price', 'sale_price', 'category_id')
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => $p->price,
                'sale_price' => $p->sale_price,
                'image' => $p->images->first()?->url,
                'url' => '/' . ($p->category?->slug ?? 'san-pham') . '/' . $p->slug,
            ]);

        $posts = Post::where('status', 'published')
            ->where('title', 'LIKE', "%{$q}%")
            ->with('category:id,slug,name')
            ->select('id', 'title', 'slug', 'post_category_id', 'published_at')
            ->limit(4)
            ->latest('published_at')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'url' => '/tin-tuc/' . $p->slug,
                'category' => $p->category?->name,
            ]);

        return response()->json([
            'products' => $products,
            'posts' => $posts,
        ]);
    }
}
