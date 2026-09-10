<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostCardResource;
use App\Http\Resources\PostDetailResource;
use App\Models\Banner;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\Seo\SlugRedirectService;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BlogController extends Controller
{
    /**
     * Return the curated news landing payload used by /tin-tuc.
     */
    public function home(Request $request): JsonResponse
    {
        $categorySlug = trim((string) $request->input('category', ''));

        $hero = $this->publishedPosts($categorySlug)
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        if ($hero->count() < 3) {
            $heroIds = $hero->pluck('id')->all();
            $hero = $hero->concat(
                $this->publishedPosts($categorySlug)
                    ->when($heroIds !== [], fn (Builder $query) => $query->whereNotIn('id', $heroIds))
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(3 - $hero->count())
                    ->get(),
            )->values();
        }

        $heroIds = $hero->pluck('id')->all();
        $featured = $this->publishedPosts($categorySlug)
            ->when($heroIds !== [], fn (Builder $query) => $query->whereNotIn('id', $heroIds))
            ->orderByDesc('is_featured')
            ->orderByDesc('view_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $usedIds = array_merge($heroIds, $featured->pluck('id')->all());
        $latest = $this->publishedPosts($categorySlug)
            ->when($usedIds !== [], fn (Builder $query) => $query->whereNotIn('id', $usedIds))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $trending = $this->publishedPosts($categorySlug)
            ->orderByDesc('view_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $categories = $this->categoriesPayload();

        return response()->json([
            'hero' => PostCardResource::collection($hero)->resolve(),
            'featured' => PostCardResource::collection($featured)->resolve(),
            'latest' => PostCardResource::collection($latest)->resolve(),
            'trending' => PostCardResource::collection($trending)->resolve(),
            'categories' => $categories,
            'topics' => $this->topicsPayload($categories),
            'pc_builder' => $this->pcBuilderPayload(),
        ])->setSharedMaxAge(60);
    }

    /**
     * Get all posts
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->publishedPosts((string) $request->input('category', ''));

        // Filter by category
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $query->orderBy('published_at', 'desc')
            ->paginate(12);

        return response()->json([
            'posts' => PostCardResource::collection($posts->getCollection())->resolve(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get single post
     */
    public function show(Request $request, string $slug, SlugRedirectService $redirects): JsonResponse
    {
        $resolved = $redirects->postBySlug($slug);
        abort_unless($resolved, 404);

        $post = Post::with([
            'category:id,name,slug',
            'author:id,name,avatar',
        ])
            ->published()
            ->whereKey($resolved->getKey())
            ->firstOrFail();

        $related = $this->relatedPosts($post);
        $trending = $this->publishedPosts()
            ->orderByDesc('view_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
        $reviews = $this->publishedPosts()
            ->whereKeyNot($post->id)
            ->whereHas('category', fn (Builder $query) => $query->whereIn('slug', ['review-san-pham', 'review']))
            ->orderByDesc('view_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        if ($reviews->isEmpty()) {
            $reviews = $related->take(4);
        }

        return response()->json([
            'post' => PostDetailResource::make($post)->resolve($request),
            'related' => PostCardResource::collection($related)->resolve(),
            'sidebar' => [
                'trending' => PostCardResource::collection($trending)->resolve(),
                'reviews' => PostCardResource::collection($reviews)->resolve(),
                'pc_builder' => $this->pcBuilderPayload(),
            ],
        ]);
    }

    /**
     * Register a view separately from the read-only article endpoint.
     * A privacy-preserving IP/user-agent hash prevents refreshes and bots
     * from inflating the counter on every GET request.
     */
    public function view(Request $request, string $slug, SlugRedirectService $redirects): JsonResponse
    {
        $resolved = $redirects->postBySlug($slug);
        abort_unless($resolved, 404);

        $post = Post::query()
            ->published()
            ->whereKey($resolved->getKey())
            ->firstOrFail();
        $sessionId = $request->hasSession() ? $request->session()->getId() : '';
        $visitor = hash('sha256', implode('|', [
            (string) ($request->user()?->getAuthIdentifier() ?? ''),
            (string) $sessionId,
            (string) $request->ip(),
            (string) $request->userAgent(),
            (string) $post->id,
        ]));
        $key = 'news-view:'.$post->id.':'.$visitor;
        $tracked = Cache::add($key, true, now()->addMinutes(30));

        if ($tracked) {
            // View tracking is telemetry, not editorial content. Update it
            // through the base query builder so Laravel does not refresh
            // `updated_at`, which is also used as the article sitemap
            // lastmod value.
            DB::table($post->getTable())
                ->where($post->getKeyName(), $post->getKey())
                ->update(['view_count' => DB::raw('view_count + 1')]);
        }

        return response()->json([
            'tracked' => $tracked,
            'view_count' => (int) $post->fresh()->view_count,
        ])->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
    }

    /**
     * Get post categories
     */
    public function categories(): JsonResponse
    {
        return response()->json($this->categoriesPayload());
    }

    /**
     * Resolve a current or historical article category for the crawlable
     * category landing route. The API returns JSON so the storefront can
     * issue a single permanent redirect for an old category slug.
     */
    public function category(string $slug, SlugRedirectService $redirects): JsonResponse
    {
        $category = $redirects->postCategoryBySlug($slug);
        abort_unless($category, 404);

        $postsCount = $category->posts()
            ->published()
            ->count();
        abort_unless($postsCount > 0, 404);

        $urls = app(\App\Services\Seo\PublicUrlResolver::class);
        $canonicalPath = $urls->postCategoryPath($category);
        abort_unless($canonicalPath, 404);

        return response()->json([
            'category' => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'description' => $category->description,
                'posts_count' => $postsCount,
                'canonical_path' => $canonicalPath,
                'canonical_url' => $urls->absolute($canonicalPath),
            ],
            'trending' => PostCardResource::collection(
                $this->publishedPosts()
                    ->orderByDesc('view_count')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get(),
            )->resolve(),
            'categories' => $this->categoriesPayload(),
            'pc_builder' => $this->pcBuilderPayload(),
        ]);
    }

    /**
     * Get featured posts
     */
    public function featured(): JsonResponse
    {
        $posts = $this->publishedPosts()
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json(PostCardResource::collection($posts)->resolve());
    }

    /** @return Builder<Post> */
    private function publishedPosts(?string $categorySlug = null): Builder
    {
        $categorySlug = trim((string) $categorySlug);

        return Post::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'author:id,name',
            ])
            ->when($categorySlug !== '', function (Builder $query) use ($categorySlug): void {
                $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('slug', $categorySlug));
            });
    }

    /** @return list<array{id: int, name: string, slug: string, posts_count: int, canonical_path: string|null, canonical_url: string|null}> */
    private function categoriesPayload(): array
    {
        $urls = app(\App\Services\Seo\PublicUrlResolver::class);

        return PostCategory::query()
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (PostCategory $category): bool => (int) $category->posts_count > 0)
            ->map(function (PostCategory $category) use ($urls): ?array {
                $canonicalPath = $urls->postCategoryPath($category);
                if ($canonicalPath === null) {
                    return null;
                }

                return [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'posts_count' => (int) $category->posts_count,
                    'canonical_path' => $canonicalPath,
                    'canonical_url' => $urls->absolute($canonicalPath),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Prefer the reference topics when those categories exist, then fill any
     * missing slots with the most populated published categories.
     *
     * @param  list<array{id: int, name: string, slug: string, posts_count: int, canonical_path: string|null, canonical_url: string|null}>  $categories
     * @return list<array{id: int, name: string, slug: string, posts_count: int, image: string|null, canonical_path: string|null, canonical_url: string|null}>
     */
    private function topicsPayload(array $categories): array
    {
        $urls = app(\App\Services\Seo\PublicUrlResolver::class);
        $categoryModels = PostCategory::query()
            ->whereIn('slug', array_column($categories, 'slug'))
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->get()
            ->filter(fn (PostCategory $category): bool => (int) $category->posts_count > 0)
            ->keyBy('slug');

        $preferredSlugs = ['vga', 'cpu', 'laptop', 'setup-goc-may', 'meo-toi-uu-game'];
        $selected = collect($preferredSlugs)
            ->map(fn (string $slug): ?PostCategory => $categoryModels->get($slug))
            ->filter()
            ->values();

        if ($selected->count() < 5) {
            $selected = $selected->concat(
                $categoryModels
                    ->reject(fn (PostCategory $category): bool => $selected->contains('id', $category->id))
                    ->sortByDesc('posts_count')
                    ->sortBy('sort_order')
                    ->take(5 - $selected->count()),
            )->values();
        }

        return $selected->map(function (PostCategory $category) use ($urls): ?array {
            $image = $category->posts()
                ->published()
                ->whereNotNull('featured_image')
                ->where('featured_image', '!=', '')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->value('featured_image');
            $canonicalPath = $urls->postCategoryPath($category);
            if ($canonicalPath === null) {
                return null;
            }

            return [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'posts_count' => (int) $category->posts_count,
                'image' => PublicAssetUrl::normalize($image),
                'canonical_path' => $canonicalPath,
                'canonical_url' => $urls->absolute($canonicalPath),
            ];
        })->filter()->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function pcBuilderPayload(): ?array
    {
        $banner = Banner::query()
            ->active()
            ->whereIn('position', ['news_pc_builder', 'pc_builder'])
            ->orderByRaw("CASE WHEN position = 'news_pc_builder' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (! $banner) {
            return null;
        }

        return [
            'id' => (int) $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'badge' => $banner->badge,
            'image' => PublicAssetUrl::normalize($banner->image),
            'link' => $banner->link,
            'metadata' => is_array($banner->metadata) ? $banner->metadata : null,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Post> */
    private function relatedPosts(Post $post)
    {
        $related = collect();

        if ($post->post_category_id) {
            $related = $this->publishedPosts()
                ->whereKeyNot($post->id)
                ->where('post_category_id', $post->post_category_id)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(4)
                ->get();
        }

        if ($related->count() < 4) {
            $excludeIds = $related->pluck('id')->push($post->id)->all();
            $related = $related->concat(
                $this->publishedPosts()
                    ->whereNotIn('id', $excludeIds)
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit(4 - $related->count())
                    ->get(),
            )->values();
        }

        return $related;
    }
}
