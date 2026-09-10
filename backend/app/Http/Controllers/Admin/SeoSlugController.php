<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\Seo\PublicUrlResolver;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class SeoSlugController extends Controller
{
    /** @var array<string, class-string<Model>> */
    private const ENTITY_TYPES = [
        'category' => Category::class,
        'product' => Product::class,
        'post' => Post::class,
        'post-category' => PostCategory::class,
        'page' => Page::class,
        'brand' => Brand::class,
    ];

    /**
     * Preview a controlled slug change without mutating the catalog.
     */
    public function preview(
        Request $request,
        string $entityType,
        int $entityId,
        VietnameseSlugNormalizer $slugs,
        PublicUrlResolver $urls,
        SlugRedirectService $redirects,
    ): JsonResponse {
        $entity = $this->findEntity($entityType, $entityId);
        abort_unless($entity, 404);

        $newSlug = $this->validatedSlug($request, $slugs);
        $this->assertTarget($entityType, $entity, $newSlug, $slugs, $urls, $redirects);

        return $this->response($this->previewPayload($entityType, $entity, $newSlug, $slugs, $urls));
    }

    /**
     * Change a public slug and create a direct 301 history entry in the same
     * transaction. This is deliberately separate from ordinary CRUD updates.
     */
    public function change(
        Request $request,
        string $entityType,
        int $entityId,
        VietnameseSlugNormalizer $slugs,
        PublicUrlResolver $urls,
        SlugRedirectService $redirects,
    ): JsonResponse {
        $newSlug = $this->validatedSlug($request, $slugs);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $reason = trim((string) $validated['reason']);
        $actorId = $request->user()?->getAuthIdentifier();

        $payload = DB::transaction(function () use (
            $entityType,
            $entityId,
            $newSlug,
            $reason,
            $actorId,
            $slugs,
            $urls,
            $redirects,
        ): array {
            $entity = $this->findEntity($entityType, $entityId, true);
            abort_unless($entity, 404);

            $this->assertTarget($entityType, $entity, $newSlug, $slugs, $urls, $redirects);
            $oldSlug = trim((string) $entity->getAttribute('slug'));
            $oldCategorySlug = $entity instanceof Product ? $entity->category?->slug : null;
            $oldPath = $this->pathFor($entityType, $entity, $oldSlug, $slugs, $urls);
            $newPath = $this->pathFor($entityType, $entity, $newSlug, $slugs, $urls);

            if ($oldSlug === $newSlug) {
                return $this->previewPayload($entityType, $entity, $newSlug, $slugs, $urls) + [
                    'changed' => false,
                    'reason' => $reason,
                    'redirect' => null,
                ];
            }

            $entity->forceFill([
                'slug' => $newSlug,
                'slug_source' => $entity->getAttribute('slug_source') ?: $this->sourceText($entity),
                'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
                'slug_locked_at' => $entity->getAttribute('slug_locked_at') ?: now(),
            ])->save();

            $history = $this->recordChange(
                $entityType,
                $entity,
                $oldSlug,
                $oldCategorySlug,
                $oldPath,
                $actorId === null ? null : (int) $actorId,
                $reason,
                $redirects,
            );

            return $this->previewPayload($entityType, $entity, $newSlug, $slugs, $urls) + [
                'changed' => true,
                'reason' => $reason,
                'redirect' => $history ? [
                    'source_path' => $history->source_path,
                    'target_path' => $history->target_path,
                    'status' => (int) $history->redirect_status,
                    'migration_batch_id' => $history->migration_batch_id,
                ] : null,
            ];
        }, 3);

        return $this->response($payload);
    }

    /** @return array<string, mixed> */
    private function previewPayload(
        string $entityType,
        Model $entity,
        string $newSlug,
        VietnameseSlugNormalizer $slugs,
        PublicUrlResolver $urls,
    ): array {
        $oldSlug = trim((string) $entity->getAttribute('slug'));
        $oldPath = $this->pathFor($entityType, $entity, $oldSlug, $slugs, $urls);
        $newPath = $this->pathFor($entityType, $entity, $newSlug, $slugs, $urls);

        return [
            'entity_type' => $entityType,
            'entity_id' => (int) $entity->getKey(),
            'entity_name' => $this->sourceText($entity),
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
            'old_path' => $oldPath,
            'new_path' => $newPath,
            'old_url' => $urls->absolute($oldPath),
            'new_url' => $urls->absolute($newPath),
            'affected_url_count' => $this->affectedUrlCount($entityType, $entity),
            'will_redirect' => $oldPath !== null && $newPath !== null && $oldPath !== $newPath,
            'redirect_status' => 301,
            'slug_locked' => true,
        ];
    }

    private function validatedSlug(Request $request, VietnameseSlugNormalizer $slugs): string
    {
        $value = $request->validate([
            'new_slug' => ['required', 'string', 'max:'.VietnameseSlugNormalizer::MAX_LENGTH],
        ])['new_slug'];

        try {
            $slug = $slugs->validateCustom((string) $value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['new_slug' => $exception->getMessage()]);
        }
        if ($slugs->isReserved($slug)) {
            throw ValidationException::withMessages(['new_slug' => 'Slug này dành riêng cho route hệ thống.']);
        }

        return $slug;
    }

    private function assertTarget(
        string $entityType,
        Model $entity,
        string $newSlug,
        VietnameseSlugNormalizer $slugs,
        PublicUrlResolver $urls,
        SlugRedirectService $redirects,
    ): void {
        $oldSlug = trim((string) $entity->getAttribute('slug'));
        if ($newSlug === $oldSlug) {
            return;
        }

        $newPath = $this->pathFor($entityType, $entity, $newSlug, $slugs, $urls);
        if ($newPath === null) {
            throw ValidationException::withMessages([
                'new_slug' => 'Không thể tạo URL công khai từ slug mới và dữ liệu danh mục hiện tại.',
            ]);
        }

        try {
            $redirects->assertLegacySlugAvailable($this->routeNamespace($entityType), $newSlug);
            $redirects->assertPathAvailable($newPath, $entity);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['new_slug' => $exception->getMessage()]);
        }

        $query = $this->sameTableSlugQuery($entityType, $newSlug);
        if ($query && $query->whereKeyNot($entity->getKey())->exists()) {
            throw ValidationException::withMessages(['new_slug' => 'Slug này đã được sử dụng bởi một entity khác.']);
        }

        // Category and static pages share the root namespace. Keep that
        // invariant explicit even when the two tables have separate indexes.
        if ($entityType === 'category' && Page::query()->where('slug', $newSlug)->exists()) {
            throw ValidationException::withMessages(['new_slug' => 'Slug này đã được sử dụng bởi một trang tĩnh.']);
        }
        if ($entityType === 'page' && Category::query()->where('slug', $newSlug)->exists()) {
            throw ValidationException::withMessages(['new_slug' => 'Slug này đã được sử dụng bởi một danh mục.']);
        }
    }

    /** @return Builder<Model>|null */
    private function sameTableSlugQuery(string $entityType, string $slug): ?Builder
    {
        return match ($entityType) {
            'category' => Category::query()->where('slug', $slug),
            'product' => Product::query()->where('slug', $slug),
            'post' => Post::query()->where('slug', $slug),
            'post-category' => PostCategory::query()->where('slug', $slug),
            'page' => Page::query()->where('slug', $slug),
            'brand' => Brand::query()->where('slug', $slug),
            default => null,
        };
    }

    private function findEntity(string $entityType, int $entityId, bool $forUpdate = false): ?Model
    {
        abort_unless(array_key_exists($entityType, self::ENTITY_TYPES), 404);

        $query = match ($entityType) {
            'category' => Category::query(),
            'product' => Product::query()->with('category'),
            'post' => Post::query(),
            'post-category' => PostCategory::query(),
            'page' => Page::query(),
            'brand' => Brand::query(),
        };
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->whereKey($entityId)->first();
    }

    private function routeNamespace(string $entityType): string
    {
        return $entityType;
    }

    private function pathFor(
        string $entityType,
        Model $entity,
        string $slug,
        VietnameseSlugNormalizer $slugs,
        PublicUrlResolver $urls,
    ): ?string {
        $slug = trim($slug, '/');
        try {
            $slug = $slugs->validateCustom($slug);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return match ($entityType) {
            'category' => $urls->categoryPathForSlug($slug),
            'page' => $urls->pagePathForSlug($slug),
            'post' => $urls->postPathForSlug($slug),
            'post-category' => $urls->postCategoryPathForSlug($slug),
            'brand' => $urls->brandPathForSlug($slug),
            'product' => $entity instanceof Product ? $urls->productPathForSlug($entity, $slug) : null,
            default => null,
        };
    }

    private function sourceText(Model $entity): string
    {
        return (string) ($entity->getAttribute('name')
            ?: $entity->getAttribute('title')
            ?: $entity->getAttribute('slug'));
    }

    private function affectedUrlCount(string $entityType, Model $entity): int
    {
        if ($entityType !== 'category' || ! $entity instanceof Category) {
            return 1;
        }

        return 1 + $entity->products()->visibleOnStorefront()->count();
    }

    private function recordChange(
        string $entityType,
        Model $entity,
        string $oldSlug,
        ?string $oldCategorySlug,
        ?string $oldPath,
        ?int $actorId,
        string $reason,
        SlugRedirectService $redirects,
    ): ?\App\Models\SlugHistory {
        if ($oldPath === null) {
            return null;
        }

        return match ($entityType) {
            'category' => $redirects->recordCategoryChange($entity->fresh(), $oldSlug, $actorId, $reason),
            'product' => $redirects->recordProductChange($entity->fresh(['category']), $oldSlug, $oldCategorySlug, $actorId, $oldPath, $reason),
            'post' => $redirects->recordPostChange($entity->fresh(), $oldSlug, $actorId, $reason),
            'post-category' => $redirects->recordPostCategoryChange($entity->fresh(), $oldSlug, $actorId, $reason),
            'page' => $redirects->recordPageChange($entity->fresh(), $oldSlug, $actorId, $reason),
            'brand' => $redirects->recordBrandChange($entity->fresh(), $oldSlug, $actorId, $reason),
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): JsonResponse
    {
        return response()->json($payload)
            ->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
    }
}
