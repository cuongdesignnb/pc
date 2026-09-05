<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class HomepageController extends Controller
{
    public function index(): JsonResponse
    {
        $response = [
            'hero_banners' => $this->banners('hero'),
            'sidebar_banners' => $this->banners('sidebar'),
            'category_sidebar' => $this->categorySidebar(),
            'featured_categories' => $this->featuredCategories(),
            'flash_sale' => $this->flashSale(),
            'best_sellers' => [
                'laptop' => $this->productsForCategory('laptop', 5),
                'pc_gaming' => $this->productsForCategory('pc-gaming', 5),
                'components' => $this->productsForCategory('linh-kien-pc', 5),
            ],
            'pc_builder_banner' => $this->banners('pc_builder')->first(),
            'combo_banners' => $this->banners('combo'),
            'setup_banners' => $this->banners('setup_inspiration'),
            'featured_accessories' => $this->featuredAccessories(),
            'posts' => $this->posts(),
            'testimonials' => $this->testimonials(),
        ];

        return response()->json($response)->setSharedMaxAge(60);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function banners(string $position): Collection
    {
        return Banner::active()
            ->where('position', $position)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'description' => $banner->description,
                'badge' => $banner->badge,
                'image' => PublicAssetUrl::normalize($banner->image),
                'link' => $banner->link,
                'position' => $banner->position,
                'sort_order' => (int) $banner->sort_order,
                'metadata' => is_array($banner->metadata) ? $banner->metadata : null,
            ])
            ->values();
    }

    /** @return list<array<string, mixed>> */
    private function categorySidebar(): array
    {
        $preferredSlugs = [
            'pc-gaming',
            'pc-do-hoa-render',
            'laptop',
            'vga',
            'cpu',
            'mainboard',
            'ram',
            'ssd',
            'psu',
            'man-hinh',
            'phu-kien',
            'ghe-gaming',
        ];

        $categories = Category::query()
            ->visibleOnStorefront()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Keep the merchandising order where the slugs exist, then append the
        // rest of the synced public catalogue. A single matching slug must not
        // hide every other category returned by the integration.
        $preferred = $categories
            ->filter(fn (Category $category): bool => in_array($category->slug, $preferredSlugs, true))
            ->sortBy(fn (Category $category): int => array_search($category->slug, $preferredSlugs, true))
            ->values();
        $remaining = $categories
            ->reject(fn (Category $category): bool => in_array($category->slug, $preferredSlugs, true))
            ->values();

        return $preferred
            ->concat($remaining)
            ->take(13)
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => PublicAssetUrl::normalize($category->image),
                'icon' => PublicAssetUrl::normalize($category->icon),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function featuredCategories(): array
    {
        $configured = Setting::get('homepage_featured_category_slugs', []);
        if (is_string($configured)) {
            $configured = json_decode($configured, true) ?: [];
        }

        $slugs = collect(is_array($configured) ? $configured : [])
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug) => trim($slug))
            ->values();

        if ($slugs->isEmpty()) {
            $slugs = collect([
                'pc-gaming',
                'laptop-gaming',
                'vga',
                'cpu',
                'mainboard',
                'ram',
                'ssd',
                'man-hinh',
                'ghe-gaming',
            ]);
        }

        $categories = Category::query()
            ->visibleOnStorefront()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $configuredCategories = $categories
            ->filter(fn (Category $category): bool => $slugs->contains($category->slug))
            ->sortBy(fn (Category $category): int => $slugs->search($category->slug))
            ->values();
        $remainingCategories = $categories
            ->reject(fn (Category $category): bool => $slugs->contains($category->slug))
            ->values();

        return $configuredCategories
            ->concat($remainingCategories)
            ->take(12)
            ->map(function (Category $category): array {
                $image = PublicAssetUrl::normalize($category->image);
                if (! $image) {
                    $image = $this->productQuery()
                        ->whereIn('category_id', $this->categoryIds($category))
                        ->orderByDesc('is_featured')
                        ->orderByDesc('sold_count')
                        ->first()?->images?->first()?->url;
                    $image = PublicAssetUrl::normalize($image);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'image' => $image,
                    'icon' => PublicAssetUrl::normalize($category->icon),
                ];
            })->values()->all();
    }

    /** @return array<string, mixed> */
    private function flashSale(): array
    {
        $enabled = $this->toBoolean(Setting::get('homepage_flash_sale_enabled', true), true);
        $endsAt = Setting::get('homepage_flash_sale_ends_at');
        $endsAt = is_string($endsAt) && trim($endsAt) !== '' ? trim($endsAt) : null;

        $products = [];
        if ($enabled) {
            $products = ProductCardResource::collection(
                $this->productQuery()
                    ->whereNotNull('sale_price')
                    ->where('sale_price', '>', 0)
                    ->whereColumn('sale_price', '<', 'price')
                    ->orderByRaw('(price - sale_price) / NULLIF(price, 0) DESC')
                    ->orderByDesc('sold_count')
                    ->limit(6)
                    ->get()
            )->resolve();
        }

        return [
            'enabled' => $enabled,
            'ends_at' => $endsAt,
            'products' => $products,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function productsForCategory(string $slug, int $limit): array
    {
        $category = Category::query()->where('slug', $slug)->visibleOnStorefront()->first();
        $products = $category
            ? $this->productQuery()
                ->whereIn('category_id', $this->categoryIds($category))
                ->orderByDesc('sold_count')
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
            : collect();

        // Kiot category names/slugs are configurable and may differ from the
        // storefront's merchandising aliases. Keep the homepage populated from
        // the public synced catalogue when an alias has no matching products.
        if ($products->isEmpty()) {
            $products = $this->productQuery()
                ->orderByDesc('sold_count')
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        return ProductCardResource::collection($products)->resolve();
    }

    /** @return list<array<string, mixed>> */
    private function featuredAccessories(): array
    {
        $category = Category::query()->where('slug', 'phu-kien')->visibleOnStorefront()->first();
        if (! $category) {
            return [];
        }

        $products = $this->productQuery()
            ->whereIn('category_id', $this->categoryIds($category))
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return ProductCardResource::collection($products)->resolve();
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        return Post::query()
            ->published()
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at', 'view_count'])
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'featured_image' => PublicAssetUrl::normalize($post->featured_image),
                'published_at' => $post->published_at?->toISOString(),
                'view_count' => (int) $post->view_count,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function testimonials(): array
    {
        $reviews = Review::query()
            ->where('is_approved', true)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->with([
                'user:id,name,avatar',
                'order:id,shipping_city,order_status',
                'order.items:id,order_id,product_id',
            ])
            ->latest()
            ->limit(12)
            ->get();

        return $reviews
            ->sortByDesc(fn (Review $review): int => $this->isVerifiedPurchase($review) ? 1 : 0)
            ->take(8)
            ->map(fn (Review $review) => [
                'id' => $review->id,
                'name' => $review->user?->name ?? $review->guest_name ?? 'Khách hàng',
                'avatar' => PublicAssetUrl::normalize($review->user?->avatar),
                'city' => $review->order?->shipping_city,
                'rating' => (int) $review->rating,
                'body' => $review->body,
                'verified_purchase' => $this->isVerifiedPurchase($review),
            ])
            ->values()
            ->all();
    }

    private function productQuery(): Builder
    {
        return Product::query()
            ->with(['category', 'brand', 'images'])
            ->withAvg('approvedReviews', 'rating')
            ->withCount('approvedReviews')
            ->visibleOnStorefront();
    }

    /** @return list<int> */
    private function categoryIds(Category $category): array
    {
        $ids = collect([$category->id]);
        $frontier = collect([$category->id]);

        // Storefront categories are normally two levels deep. Three passes also
        // keep this endpoint safe if an admin adds a deeper merchandising tree.
        for ($pass = 0; $pass < 3 && $frontier->isNotEmpty(); $pass++) {
            $children = Category::query()
                ->visibleOnStorefront()
                ->whereIn('parent_id', $frontier->all())
                ->pluck('id');
            $ids = $ids->merge($children);
            $frontier = $children;
        }

        return $ids->unique()->values()->all();
    }

    private function isVerifiedPurchase(Review $review): bool
    {
        return $review->order !== null
            && $review->order->order_status !== 'cancelled'
            && $review->order->items->contains('product_id', $review->product_id);
    }

    private function toBoolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $fallback;
    }
}
