<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\SlugHistory;
use App\Services\News\ArticleContentSanitizer;
use App\Services\Seo\PublicUrlResolver;
use App\Services\Seo\VietnameseSlugNormalizer;
use App\Support\SiteTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Admin API for tools outside the admin site (content writers, AI agents,
 * KIOT read access). Authorised by EnsureAdminApiToken. Product writes are
 * limited to SEO and content fields: price, stock and visibility stay with
 * KIOT and the admin site. Slugs are set on create only, so public URLs never
 * change behind the redirect tooling.
 */
class AdminApiController extends Controller
{
    public function __construct(private readonly PublicUrlResolver $urls) {}

    // ---- Products -------------------------------------------------------

    public function products(Request $request): JsonResponse
    {
        $query = Product::query()->with(['category:id,name,slug', 'images'])->orderByDesc('id');
        if (filled($request->search)) {
            $search = (string) $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if (filled($request->category_id)) {
            $query->where('category_id', (int) $request->category_id);
        }
        if (filled($request->updated_since)) {
            $query->where('updated_at', '>=', Carbon::parse((string) $request->updated_since));
        }
        $page = $query->paginate(min(100, max(1, (int) $request->input('per_page', 50))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $product) => $this->productPayload($product))->all(),
            'meta' => $this->meta($page),
        ]);
    }

    public function product(Product $product): JsonResponse
    {
        return response()->json(['data' => $this->productPayload($product->load(['category:id,name,slug', 'images']))]);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'price' => 'prohibited', 'sale_price' => 'prohibited', 'stock_quantity' => 'prohibited',
            'category_id' => 'prohibited', 'is_active' => 'prohibited', 'slug' => 'prohibited',
        ], ['prohibited' => 'API không được sửa :attribute (giá, tồn kho, danh mục, hiển thị và slug chỉ sửa trên trang admin).']);
        $validated = $request->validate([
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:1000',
            'short_description' => 'sometimes|nullable|string|max:500',
            'description' => 'sometimes|nullable|string|max:200000',
            'images' => 'sometimes|array|max:50',
            'images.*.id' => 'required|integer',
            'images.*.alt' => 'nullable|string|max:255',
            'specifications' => 'sometimes|nullable|array|max:100',
            'specifications.*.label' => 'required|string|max:255',
            'specifications.*.value' => 'nullable|string|max:2000',
        ]);

        $fields = collect($validated)->except(['images', 'specifications'])->all();
        if (array_key_exists('specifications', $validated)) {
            // Stored as "Label: value" lines, the same as the admin's "Thông số kỹ thuật" box.
            $fields['specifications_text'] = collect($validated['specifications'] ?? [])
                ->map(fn (array $spec): string => self::oneLine(str_replace(':', ' ', $spec['label'])).': '.self::oneLine((string) ($spec['value'] ?? '')))
                ->implode("\n") ?: null;
        }
        $product->fill($fields)->save();
        foreach ($validated['images'] ?? [] as $image) {
            $product->images()->whereKey($image['id'])->update(['alt_text' => filled($image['alt']) ? trim($image['alt']) : null]);
        }

        return $this->product($product->fresh());
    }

    // ---- Categories -----------------------------------------------------

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Category $category) => $this->categoryPayload($category))->all()]);
    }

    public function storeCategory(Request $request, VietnameseSlugNormalizer $slugs): JsonResponse
    {
        $validated = $request->validate($this->categoryRules() + [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:160',
        ]);
        $this->assertParent($validated['parent_id'] ?? null);
        $validated['slug'] = $this->freeSlug($slugs, $validated['slug'] ?? null, $validated['name'], 'category');
        $category = Category::create($validated + ['is_active' => true, 'sort_order' => $validated['sort_order'] ?? 0]);

        return response()->json(['data' => $this->categoryPayload($category)], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        $request->validate(['slug' => 'prohibited'], ['prohibited' => 'Slug danh mục chỉ sửa trên trang admin.']);
        $validated = $request->validate($this->categoryRules() + ['name' => 'sometimes|required|string|max:255']);
        if (array_key_exists('parent_id', $validated)) {
            $this->assertParent($validated['parent_id'], $category);
        }
        $category->fill($validated)->save();

        return response()->json(['data' => $this->categoryPayload($category)]);
    }

    public function postCategories(): JsonResponse
    {
        return response()->json(['data' => PostCategory::query()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PostCategory $category) => $category->only(['id', 'name', 'slug', 'description', 'sort_order']) + [
                'url' => $this->urls->absolute($this->urls->postCategoryPath($category)),
            ])->all()]);
    }

    // ---- Posts ----------------------------------------------------------

    public function posts(Request $request): JsonResponse
    {
        $query = Post::query()->orderByDesc('id');
        if (filled($request->status)) {
            $query->where('status', (string) $request->status);
        }
        $page = $query->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Post $post) => $this->postPayload($post, false))->all(),
            'meta' => $this->meta($page),
        ]);
    }

    public function post(Post $post): JsonResponse
    {
        return response()->json(['data' => $this->postPayload($post)]);
    }

    public function storePost(Request $request, ArticleContentSanitizer $sanitizer, VietnameseSlugNormalizer $slugs): JsonResponse
    {
        $validated = $request->validate($this->postRules() + [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:160',
            'body' => 'required|string',
        ]);
        $validated['slug'] = $this->freeSlug($slugs, $validated['slug'] ?? null, $validated['title'], 'post');
        $validated['body'] = $sanitizer->sanitize($validated['body']);
        $validated['status'] ??= 'draft';
        $validated['published_at'] = $this->publishedAt($validated);
        $post = Post::create($validated + [
            'user_id' => $request->user()->id,
            'view_count' => 0,
            'slug_source' => $validated['title'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => now(),
        ]);

        return response()->json(['data' => $this->postPayload($post)], 201);
    }

    public function updatePost(Request $request, Post $post, ArticleContentSanitizer $sanitizer): JsonResponse
    {
        $request->validate(['slug' => 'prohibited'], ['prohibited' => 'Slug bài viết chỉ sửa trên trang admin.']);
        $validated = $request->validate($this->postRules() + [
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
        ]);
        if (isset($validated['body'])) {
            $validated['body'] = $sanitizer->sanitize($validated['body']);
        }
        if (array_key_exists('published_at', $validated) || ($validated['status'] ?? null) === 'published') {
            $validated['published_at'] = $this->publishedAt($validated + ['status' => $post->status], $post->published_at);
        }
        $post->fill($validated)->save();

        return response()->json(['data' => $this->postPayload($post)]);
    }

    // ---- Media ----------------------------------------------------------

    public function media(Request $request): JsonResponse
    {
        $query = Media::query()->latest();
        if (filled($request->search)) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('alt', 'like', '%'.$request->search.'%'));
        }
        $page = $query->paginate(min(100, max(1, (int) $request->input('per_page', 40))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $media) => $media->only(['id', 'name', 'alt', 'url', 'thumbnail_url', 'mime_type', 'width', 'height', 'folder']))->all(),
            'meta' => $this->meta($page),
        ]);
    }

    public function updateMedia(Request $request, Media $medium): JsonResponse
    {
        $medium->fill($request->validate([
            'alt' => 'sometimes|nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'caption' => 'sometimes|nullable|string|max:1000',
        ]))->save();

        return response()->json(['data' => $medium->only(['id', 'name', 'alt', 'url', 'thumbnail_url', 'mime_type', 'width', 'height', 'folder'])]);
    }

    // ---- Helpers --------------------------------------------------------

    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'url' => $this->urls->productUrl($product),
            'category' => $product->category?->only(['id', 'name', 'slug']),
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
            // What the website sells now (0 when sold out or hidden).
            'stock_quantity' => (int) $product->stock_quantity,
            // Ready to sell: KIOT units not under repair and not reserved.
            'ready_quantity' => $this->readyQuantity($product),
            'inventory_source' => $product->inventory_source,
            'kiot' => $product->inventory_source === 'kiot' ? [
                'stock_quantity' => (int) $product->kiot_physical_quantity,
                'reserved_quantity' => (int) $product->kiot_reserved_quantity,
                'ready_quantity' => $this->readyQuantity($product),
                'synced_at' => $product->kiot_synced_at?->toIso8601String(),
            ] : null,
            'is_active' => (bool) $product->is_active,
            'show_on_pc_website' => (bool) $product->show_on_pc_website,
            'manufacture_year' => $product->manufacture_year,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'specifications' => collect($product->parsed_specifications)->values()->all(),
            'images' => $product->images->sortBy('sort_order')->values()->map(fn ($image) => [
                'id' => $image->id, 'url' => $image->url, 'alt' => $image->alt, 'is_primary' => (bool) $image->is_primary,
            ])->all(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function readyQuantity(Product $product): int
    {
        if ($product->inventory_source !== 'kiot') {
            return (int) $product->stock_quantity;
        }

        return (int) ($product->kiot_ready_quantity ?? $product->kiot_available_quantity);
    }

    private function categoryPayload(Category $category): array
    {
        return $category->only([
            'id', 'parent_id', 'name', 'slug', 'description', 'image', 'meta_title', 'meta_description',
            'sort_order', 'is_active', 'provider',
        ]) + ['url' => $this->urls->absolute($this->urls->categoryPath($category))];
    }

    private function postPayload(Post $post, bool $withBody = true): array
    {
        return $post->only([
            'id', 'post_category_id', 'title', 'slug', 'excerpt', 'featured_image', 'status', 'is_featured',
            'meta_title', 'meta_description',
        ]) + ($withBody ? ['body' => $post->body] : []) + [
            'url' => $this->urls->postUrl($post),
            'published_at' => $post->published_at?->toIso8601String(),
            // Published with a future date: goes live by itself at published_at.
            'is_scheduled' => $post->status === 'published' && $post->published_at?->isFuture() === true,
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    private function categoryRules(): array
    {
        return [
            'parent_id' => 'sometimes|nullable|integer|exists:categories,id',
            'description' => 'sometimes|nullable|string|max:20000',
            'image' => 'sometimes|nullable|string|max:500',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:1000',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    private function postRules(): array
    {
        return [
            'excerpt' => 'sometimes|nullable|string|max:500',
            'featured_image' => 'sometimes|nullable|string|max:500',
            'post_category_id' => 'sometimes|nullable|exists:post_categories,id',
            'status' => 'sometimes|in:draft,published,archived',
            'is_featured' => 'sometimes|boolean',
            'published_at' => 'sometimes|nullable|date',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:1000',
        ];
    }

    private function assertParent(mixed $parentId, ?Category $editing = null): void
    {
        $error = app(AdminCategoryController::class)->parentError($parentId, $editing);
        if ($error !== null) {
            throw ValidationException::withMessages(['parent_id' => $error]);
        }
    }

    /** A valid, unused slug from the given one or from the title. */
    private function freeSlug(VietnameseSlugNormalizer $slugs, ?string $slug, string $title, string $namespace): string
    {
        try {
            $base = filled($slug) ? $slugs->validateCustom((string) $slug) : $slugs->normalizeShortened($title, VietnameseSlugNormalizer::MAX_LENGTH - 4);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['slug' => $exception->getMessage()]);
        }
        if ($slugs->isReserved($base)) {
            throw ValidationException::withMessages(['slug' => 'Slug này dành riêng cho route hệ thống.']);
        }
        $taken = fn (string $candidate): bool => $namespace === 'post'
            ? Post::where('slug', $candidate)->exists() || SlugHistory::forAlias('post', $candidate)->exists()
            : Category::where('slug', $candidate)->exists() || Product::where('slug', $candidate)->exists()
                || SlugHistory::forAlias('category', $candidate)->exists();
        $candidate = $base;
        for ($counter = 2; $taken($candidate); $counter++) {
            if (filled($slug)) {
                throw ValidationException::withMessages(['slug' => 'Slug này đã được dùng.']);
            }
            $candidate = $slugs->shorten($base, VietnameseSlugNormalizer::MAX_LENGTH - strlen('-'.$counter)).'-'.$counter;
        }

        return $candidate;
    }

    private function publishedAt(array $validated, ?Carbon $current = null): ?Carbon
    {
        if (filled($validated['published_at'] ?? null)) {
            return Carbon::parse((string) $validated['published_at'], SiteTimezone::get())->utc();
        }

        return ($validated['status'] ?? null) === 'published' ? ($current ?? now()) : $current;
    }

    private function meta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
        ];
    }
}
