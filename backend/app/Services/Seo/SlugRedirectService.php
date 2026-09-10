<?php

namespace App\Services\Seo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\SlugHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class SlugRedirectService
{
    public function __construct(private readonly PublicUrlResolver $urls) {}

    /**
     * Public aliases are historical records, not a pool of reusable slugs.
     * Reusing one would make an old URL resolve to the wrong entity.
     */
    public function assertLegacySlugAvailable(string $routeNamespace, string $slug): void
    {
        $history = SlugHistory::forAlias($routeNamespace, $slug)->latest('id')->first();
        if ($history) {
            throw new LogicException("Slug {$slug} đã tồn tại trong lịch sử URL công khai và không thể tái sử dụng.");
        }
    }

    /**
     * A public path is globally owned by the storefront. Check the complete
     * path, not only a route namespace, so a category/page or another entity
     * cannot silently take over an old alias.
     */
    public function assertPathAvailable(string $path, ?Model $except = null): void
    {
        $path = $this->path($path);
        if ($path === null) {
            throw new LogicException('URL đích phải là một path nội bộ hợp lệ.');
        }

        $history = SlugHistory::query()
            ->where('site_key', 'storefront')
            ->where('source_path', $path)
            ->lockForUpdate()
            ->latest('id')
            ->first();
        if ($history) {
            throw new LogicException("URL {$path} đã tồn tại trong lịch sử URL công khai và không thể tái sử dụng.");
        }

        $this->assertCurrentPathAvailable($path, $except);
    }

    public function assertSlugChangeAllowed(Model $entity, string $newSlug): void
    {
        $oldSlug = (string) $entity->getAttribute('slug');
        if ($oldSlug === $newSlug) {
            return;
        }
        if (filled($entity->getAttribute('slug_locked_at'))) {
            throw new LogicException('Slug đã được khóa sau khi công khai. Hãy dùng thao tác đổi slug có kiểm soát để tạo redirect và audit.');
        }

        $routeNamespace = match ($entity::class) {
            Category::class => 'category',
            Product::class => 'product',
            Post::class => 'post',
            PostCategory::class => 'post-category',
            \App\Models\Page::class => 'page',
            \App\Models\Brand::class => 'brand',
            default => null,
        };
        if ($routeNamespace !== null) {
            $this->assertLegacySlugAvailable($routeNamespace, $newSlug);
        }
    }

    public function productBySlug(string $slug): ?Product
    {
        $product = Product::query()->where('slug', $slug)->first();
        if ($product) {
            return $product;
        }

        return $this->productFromHistory(SlugHistory::forAlias('product', $slug)->latest('id')->first());
    }

    public function categoryBySlug(string $slug): ?Category
    {
        $category = Category::query()->where('slug', $slug)->first();
        if ($category) {
            return $category;
        }

        return $this->categoryFromHistory(SlugHistory::forAlias('category', $slug)->latest('id')->first());
    }

    public function postBySlug(string $slug): ?Post
    {
        $post = Post::query()->where('slug', $slug)->first();
        if ($post) {
            return $post;
        }

        return $this->postFromHistory(SlugHistory::forAlias('post', $slug)->latest('id')->first());
    }

    public function postCategoryBySlug(string $slug): ?PostCategory
    {
        $category = PostCategory::query()->where('slug', $slug)->first();
        if ($category) {
            return $category;
        }

        return $this->postCategoryFromHistory(SlugHistory::forAlias('post-category', $slug)->latest('id')->first());
    }

    public function productByPath(string $path): ?Product
    {
        $path = $this->path($path);
        if ($path === null) {
            return null;
        }

        $segments = explode('/', ltrim($path, '/'));
        if (count($segments) === 2 && $segments[0] === 'products') {
            return $this->productBySlug($segments[1]);
        }
        if (count($segments) !== 2) {
            return null;
        }

        $product = Product::query()
            ->where('slug', $segments[1])
            ->whereHas('category', fn ($query) => $query->where('slug', $segments[0]))
            ->first();
        if ($product) {
            return $product;
        }

        return $this->productFromHistory(SlugHistory::forSourcePath($path)
            ->where('route_namespace', 'product')
            ->latest('id')
            ->first());
    }

    public function categoryByPath(string $path): ?Category
    {
        $path = $this->path($path);
        if ($path === null || count(explode('/', ltrim($path, '/'))) !== 1) {
            return null;
        }

        $slug = ltrim($path, '/');

        return $this->categoryBySlug($slug)
            ?? $this->categoryFromHistory(SlugHistory::forSourcePath($path)
                ->where('route_namespace', 'category')
                ->latest('id')
                ->first());
    }

    public function postByPath(string $path): ?Post
    {
        $path = $this->path($path);
        if ($path === null) {
            return null;
        }
        $prefix = '/tin-tuc/';
        if (str_starts_with($path, $prefix)) {
            return $this->postBySlug(substr($path, strlen($prefix)));
        }

        return $this->postFromHistory(SlugHistory::forSourcePath($path)
            ->where('route_namespace', 'post')
            ->latest('id')
            ->first());
    }

    public function record(
        Model $entity,
        string $routeNamespace,
        string $legacySlug,
        string $sourcePath,
        string $targetPath,
        ?int $actorId = null,
        string $reason = 'slug_change',
        ?string $migrationBatchId = null,
    ): SlugHistory {
        return DB::transaction(function () use ($entity, $routeNamespace, $legacySlug, $sourcePath, $targetPath, $actorId, $reason, $migrationBatchId): SlugHistory {
            $sourcePath = $this->path($sourcePath);
            $targetPath = $this->path($targetPath);
            if ($sourcePath === null || $targetPath === null) {
                throw new LogicException('URL redirect phải là một path nội bộ hợp lệ.');
            }
            if ($sourcePath === $targetPath) {
                throw new LogicException('Không thể tạo redirect từ URL canonical về chính nó.');
            }

            // A redirect source must not be a live canonical URL belonging to
            // another entity, and the target must not take over one either.
            // This protects the global storefront path namespace even when a
            // caller bypasses the admin slug-change controller.
            $this->assertCurrentPathAvailable($sourcePath, $entity);
            $this->assertPathAvailable($targetPath, $entity);

            $history = SlugHistory::query()
                ->where('site_key', 'storefront')
                ->where('source_path', $sourcePath)
                ->lockForUpdate()
                ->first();
            if ($history && (int) $history->target_entity_id !== (int) $entity->getKey()) {
                throw new LogicException("URL alias {$sourcePath} đã thuộc về entity khác.");
            }

            $history = SlugHistory::query()->updateOrCreate(
                ['site_key' => 'storefront', 'source_path' => $sourcePath],
                [
                    'entity_type' => $entity::class,
                    'entity_id' => $entity->getKey(),
                    'route_namespace' => $routeNamespace,
                    'legacy_slug' => $legacySlug,
                    'target_path' => $targetPath,
                    'target_entity_id' => $entity->getKey(),
                    'redirect_status' => 301,
                    'reason' => $reason,
                    'actor_id' => $actorId,
                    'migration_batch_id' => $migrationBatchId,
                ],
            );

            // A later rename must update every older alias for the same
            // entity, otherwise A -> B -> C becomes a redirect chain.
            SlugHistory::query()
                ->where('site_key', 'storefront')
                ->where('entity_type', $entity::class)
                ->where('target_entity_id', $entity->getKey())
                ->where('source_path', '!=', $targetPath)
                ->update(['target_path' => $targetPath]);

            return $history->refresh();
        }, 3);
    }

    public function recordCategoryChange(Category $category, string $oldSlug, ?int $actorId = null, string $reason = 'slug_change'): ?SlugHistory
    {
        if ($oldSlug === '' || $oldSlug === $category->slug) {
            return null;
        }

        $targetPath = $this->urls->categoryPath($category);
        if ($targetPath === null) {
            return null;
        }
        $oldCategoryPath = $this->urls->categoryPathForSlug($oldSlug);
        if ($oldCategoryPath === null) {
            return null;
        }
        $history = $this->record($category, 'category', $oldSlug, $oldCategoryPath, $targetPath, $actorId, $reason);

        // A category slug is part of every child PDP URL. Preserve those old
        // paths as direct product redirects so a category rename does not
        // create a redirect chain through the old category page.
        $category->products()->with('category')->get()->each(function (Product $product) use ($oldSlug, $actorId): void {
            $oldPath = $this->urls->productPathForSlug($product, (string) $product->slug, $oldSlug);
            $targetPath = $this->urls->productPath($product);
            if ($oldPath !== null && $targetPath !== null && $oldPath !== $targetPath) {
                $this->record($product, 'product', (string) $product->slug, $oldPath, $targetPath, $actorId, 'category_change');
            }
        });

        return $history;
    }

    public function recordProductChange(
        Product $product,
        string $oldSlug,
        ?string $oldCategorySlug,
        ?int $actorId = null,
        ?string $oldPath = null,
        string $reason = 'slug_change',
    ): ?SlugHistory {
        $targetPath = $this->urls->productPath($product);
        if ($oldSlug === '' || $targetPath === null) {
            return null;
        }
        $sourcePath = $oldPath ?: ($oldCategorySlug
            ? $this->urls->productPathForSlug($product, $oldSlug, $oldCategorySlug)
            : '/products/'.$oldSlug);
        if ($sourcePath === null) {
            return null;
        }
        if ($sourcePath === $targetPath) {
            return null;
        }

        return $this->record(
            $product,
            'product',
            $oldSlug,
            $sourcePath,
            $targetPath,
            $actorId,
            $reason !== 'slug_change'
                ? $reason
                : ($oldCategorySlug !== ($product->category?->slug) ? 'category_change' : 'slug_change'),
        );
    }

    public function recordPostChange(Post $post, string $oldSlug, ?int $actorId = null, string $reason = 'slug_change'): ?SlugHistory
    {
        if ($oldSlug === '' || $oldSlug === $post->slug) {
            return null;
        }

        $targetPath = $this->urls->postPath($post);
        $sourcePath = $this->urls->postPathForSlug($oldSlug);

        return $targetPath === null || $sourcePath === null
            ? null
            : $this->record($post, 'post', $oldSlug, $sourcePath, $targetPath, $actorId, $reason);
    }

    public function recordPostCategoryChange(PostCategory $category, string $oldSlug, ?int $actorId = null, string $reason = 'slug_change'): ?SlugHistory
    {
        if ($oldSlug === '' || $oldSlug === $category->slug) {
            return null;
        }

        $targetPath = $this->urls->postCategoryPath($category);
        if ($targetPath === null) {
            return null;
        }
        $sourcePath = $this->urls->postCategoryPathForSlug($oldSlug);
        if ($sourcePath === null) {
            return null;
        }

        return $this->record(
            $category,
            'post-category',
            $oldSlug,
            $sourcePath,
            $targetPath,
            $actorId,
            $reason,
        );
    }

    public function recordPageChange(\App\Models\Page $page, string $oldSlug, ?int $actorId = null, string $reason = 'slug_change'): ?SlugHistory
    {
        if ($oldSlug === '' || $oldSlug === $page->slug) {
            return null;
        }

        $sourcePath = $this->urls->pagePathForSlug($oldSlug);
        $targetPath = $this->urls->pagePathForSlug((string) $page->slug);

        return $sourcePath === null || $targetPath === null
            ? null
            : $this->record($page, 'page', $oldSlug, $sourcePath, $targetPath, $actorId, $reason);
    }

    public function recordBrandChange(\App\Models\Brand $brand, string $oldSlug, ?int $actorId = null, string $reason = 'slug_change'): ?SlugHistory
    {
        if ($oldSlug === '' || $oldSlug === $brand->slug) {
            return null;
        }

        $sourcePath = $this->urls->brandPathForSlug($oldSlug);
        $targetPath = $this->urls->brandPathForSlug((string) $brand->slug);

        return $sourcePath === null || $targetPath === null
            ? null
            : $this->record($brand, 'brand', $oldSlug, $sourcePath, $targetPath, $actorId, $reason);
    }

    private function productFromHistory(?SlugHistory $history): ?Product
    {
        return $history?->target_entity_id
            ? Product::query()->find($history->target_entity_id)
            : null;
    }

    private function categoryFromHistory(?SlugHistory $history): ?Category
    {
        return $history?->target_entity_id
            ? Category::query()->find($history->target_entity_id)
            : null;
    }

    private function postFromHistory(?SlugHistory $history): ?Post
    {
        return $history?->target_entity_id
            ? Post::query()->find($history->target_entity_id)
            : null;
    }

    private function postCategoryFromHistory(?SlugHistory $history): ?PostCategory
    {
        return $history?->target_entity_id
            ? PostCategory::query()->find($history->target_entity_id)
            : null;
    }

    private function path(string $path): ?string
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        $normalized = '/'.trim(preg_replace('#/+#', '/', $path) ?? '', '/');

        return $normalized === '/' ? $normalized : rtrim($normalized, '/');
    }

    private function assertCurrentPathAvailable(string $path, ?Model $except = null): void
    {
        $owner = $this->currentEntityByPath($path);
        if ($owner === null || ($except !== null && $this->sameEntity($owner, $except))) {
            return;
        }

        throw new LogicException("URL {$path} đang là URL canonical của entity khác.");
    }

    private function currentEntityByPath(string $path): ?Model
    {
        $segments = explode('/', trim($path, '/'));

        if (count($segments) === 1 && $segments[0] !== '') {
            return Category::query()->where('slug', $segments[0])->first()
                ?? Page::query()->where('slug', $segments[0])->first();
        }

        if (count($segments) === 2 && $segments[0] === 'tin-tuc') {
            return Post::query()->where('slug', $segments[1])->first();
        }

        if (count($segments) === 2 && $segments[0] === 'thuong-hieu') {
            return Brand::query()->where('slug', $segments[1])->first();
        }

        if (count($segments) === 2) {
            return Product::query()
                ->where('slug', $segments[1])
                ->whereHas('category', fn ($query) => $query->where('slug', $segments[0]))
                ->first();
        }

        if (count($segments) === 3 && $segments[0] === 'tin-tuc' && $segments[1] === 'chuyen-muc') {
            return PostCategory::query()->where('slug', $segments[2])->first();
        }

        return null;
    }

    private function sameEntity(Model $left, Model $right): bool
    {
        return $left::class === $right::class && (int) $left->getKey() === (int) $right->getKey();
    }
}
