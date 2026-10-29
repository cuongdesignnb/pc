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
use App\Models\StoreActivity;
use App\Services\Seo\PublicUrlResolver;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class HomepageController extends Controller
{
    public function __construct(private readonly PublicUrlResolver $urls) {}

    public function index(): JsonResponse
    {
        $response = [
            'hero_banners' => $this->banners('hero'),
            'sidebar_banners' => $this->banners('sidebar'),
            'category_sidebar' => $this->categorySidebar(),
            'category_sections' => $this->featuredCategorySections(),
            'flash_sale' => $this->flashSale(),
            'product_tabs' => $this->productTabs(),
            'store_activities' => $this->storeActivities(),
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
        // The categories picked in Cài đặt, in that order; otherwise every
        // top-level category in its admin "Thứ tự".
        $slugs = $this->categorySlugsSetting('homepage_sidebar_category_slugs');
        $query = Category::query()->visibleOnStorefront();

        $categories = $slugs->isNotEmpty()
            ? $query->whereIn('slug', $slugs)->get()
                ->sortBy(fn (Category $category) => $slugs->search($category->slug))
            : $query->whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get();

        return $categories
            ->take(13)
            ->map(fn (Category $category) => $this->categoryCard($category))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function categoryCard(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'canonical_path' => $this->urls->categoryPath($category),
            'image' => PublicAssetUrl::normalize($category->image),
            'icon' => PublicAssetUrl::normalize($category->icon),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function featuredCategorySections(): array
    {
        $slugs = $this->homepageFeaturedCategorySlugs();
        $categories = Category::query()
            ->visibleOnStorefront()
            ->whereIn('slug', $slugs->all())
            ->get()
            ->keyBy('slug');

        return $slugs
            ->map(fn (string $slug): ?array => $this->categorySection($categories->get($slug)))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function categorySection(?Category $category): ?array
    {
        if (! $category) {
            return null;
        }

        $categoryIds = $this->categoryIds($category);
        $products = $this->productQuery()
            ->whereIn('category_id', $categoryIds)
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderByDesc('created_at')
            ->limit($this->homepageProductLimit())
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $productCount = $this->productQuery()
            ->whereIn('category_id', $categoryIds)
            ->count();
        $image = PublicAssetUrl::normalize($category->image);

        if (! $image) {
            $image = PublicAssetUrl::normalize($products->first()?->images?->first()?->url);
        }

        $children = Category::query()
            ->visibleOnStorefront()
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $child) => $this->categoryCard($child))
            ->values()
            ->all();

        return [
            'category' => array_merge($this->categoryCard($category), [
                'description' => $category->description,
                'image' => $image,
            ]),
            'children' => $children,
            'product_count' => (int) $productCount,
            'products' => ProductCardResource::collection($products)->resolve(),
        ];
    }

    private function homepageProductLimit(): int
    {
        $configured = Setting::get('homepage_products_per_section');

        return is_numeric($configured)
            ? max(1, min(12, (int) $configured))
            : 8;
    }

    /** @return Collection<int, string> */
    private function homepageFeaturedCategorySlugs(): Collection
    {
        return $this->categorySlugsSetting('homepage_featured_category_slugs');
    }

    /** @return Collection<int, string> */
    private function categorySlugsSetting(string $key): Collection
    {
        $configured = Setting::get($key, []);
        if (is_string($configured)) {
            $configured = json_decode($configured, true) ?: [];
        }

        $slugs = collect(is_array($configured) ? $configured : [])
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn (string $slug) => trim($slug))
            ->unique()
            ->values();

        return $slugs;
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

    /**
     * Tabs of the homepage product block, each one a category chosen in
     * Cài đặt → Trang chủ. Tabs whose category is hidden or empty are dropped.
     *
     * @return list<array<string, mixed>>
     */
    private function productTabs(): array
    {
        $configured = Setting::get('homepage_product_tabs', []);
        if (is_string($configured)) {
            $configured = json_decode($configured, true) ?: [];
        }

        $tabs = collect(is_array($configured) ? $configured : [])
            ->filter(fn ($tab): bool => is_array($tab) && is_string($tab['category_slug'] ?? null) && trim($tab['category_slug']) !== '')
            ->take(6);
        $categories = Category::query()
            ->visibleOnStorefront()
            ->whereIn('slug', $tabs->map(fn (array $tab) => trim($tab['category_slug']))->all())
            ->get()
            ->keyBy('slug');

        return $tabs->map(function (array $tab) use ($categories): ?array {
            $category = $categories->get(trim($tab['category_slug']));
            if (! $category) {
                return null;
            }

            $products = $this->productQuery()
                ->whereIn('category_id', $this->categoryIds($category))
                ->orderByDesc('sold_count')
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit($this->homepageProductLimit())
                ->get();
            if ($products->isEmpty()) {
                return null;
            }

            $label = is_string($tab['label'] ?? null) && trim($tab['label']) !== '' ? trim($tab['label']) : $category->name;

            return [
                'label' => $label,
                'category' => $this->categoryCard($category),
                'products' => ProductCardResource::collection($products)->resolve(),
            ];
        })->filter()->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function storeActivities(): array
    {
        return StoreActivity::active()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->map(fn (StoreActivity $activity) => $activity->toStorefront())
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function posts(): array
    {
        if (! $this->toBoolean(Setting::get('homepage_show_posts', true), true)) {
            return [];
        }

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
                'public_url' => $this->urls->absolute($this->urls->postPath($post)),
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
