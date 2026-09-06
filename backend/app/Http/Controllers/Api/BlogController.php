<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostCardResource;
use App\Models\Banner;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function show(string $slug): JsonResponse
    {
        $post = Post::with(['category', 'author'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $post->increment('view_count');

        // Related posts
        $related = Post::with(['category'])
            ->published()
            ->where('id', '!=', $post->id)
            ->where('post_category_id', $post->post_category_id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return response()->json([
            'post' => $post,
            'related' => PostCardResource::collection($related)->resolve(),
        ]);
    }

    /**
     * Get post categories
     */
    public function categories(): JsonResponse
    {
        return response()->json($this->categoriesPayload());
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

    /** @return list<array{id: int, name: string, slug: string, posts_count: int}> */
    private function categoriesPayload(): array
    {
        return PostCategory::query()
            ->withCount(['posts' => fn (Builder $query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (PostCategory $category): bool => (int) $category->posts_count > 0)
            ->map(fn (PostCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'posts_count' => (int) $category->posts_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Prefer the reference topics when those categories exist, then fill any
     * missing slots with the most populated published categories.
     *
     * @param  list<array{id: int, name: string, slug: string, posts_count: int}>  $categories
     * @return list<array{id: int, name: string, slug: string, posts_count: int, image: string|null}>
     */
    private function topicsPayload(array $categories): array
    {
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

        return $selected->map(function (PostCategory $category): array {
            $image = $category->posts()
                ->published()
                ->whereNotNull('featured_image')
                ->where('featured_image', '!=', '')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->value('featured_image');

            return [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'posts_count' => (int) $category->posts_count,
                'image' => PublicAssetUrl::normalize($image),
            ];
        })->all();
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
}
