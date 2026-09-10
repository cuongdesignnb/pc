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
use InvalidArgumentException;

/**
 * Builds and applies an explicit slug migration manifest.
 *
 * Planning is read-only. Applying requires the exact checksum emitted by the
 * plan and an operator identity; the current row values are checked again in
 * the same transaction before any public slug is changed.
 */
class SlugMigrationPlanner
{
    public function __construct(
        private readonly VietnameseSlugNormalizer $slugs,
        private readonly PublicUrlResolver $urls,
        private readonly SlugRedirectService $redirects,
    ) {}

    /** @return array<string, mixed> */
    public function plan(?string $batchId = null): array
    {
        $batchId = trim((string) ($batchId ?: 'seo-'.now()->format('YmdHis')));
        $rows = $this->sourceRows();
        $mappings = [];

        foreach ($rows as $row) {
            /** @var Model $entity */
            $entity = $row['entity'];
            $currentSlug = trim((string) $entity->getAttribute('slug'));
            $source = trim((string) $entity->getAttribute($row['source_field']));
            $candidate = null;
            $decision = 'REVIEW';
            $reason = 'missing_safe_candidate';

            try {
                $candidate = $this->slugs->normalize($source);
            } catch (InvalidArgumentException $exception) {
                $reason = $exception instanceof \App\Exceptions\SeoSlugException
                    ? $exception->errorCode
                    : ($exception->getCode() ?: 'invalid_source');
            }

            $currentIsSafe = false;
            try {
                $this->slugs->validateCustom($currentSlug);
                $currentIsSafe = ! $this->slugs->isReserved($currentSlug);
            } catch (InvalidArgumentException) {
                $currentIsSafe = false;
            }

            if ($currentIsSafe) {
                $decision = 'KEEP';
                $candidate = $currentSlug;
                $reason = $entity->getAttribute('slug_locked_at')
                    ? 'locked_public_slug'
                    : 'preserve_existing_public_slug';
            } elseif ($candidate !== null && ! $this->slugs->isReserved($candidate)) {
                $decision = 'CHANGE';
                $reason = $currentSlug === '' ? 'missing_slug' : 'invalid_or_reserved_slug';
            }

            $mappings[] = [
                'entity_type' => $row['entity_type'],
                'entity_id' => (int) $entity->getKey(),
                'route_namespace' => $row['route_namespace'],
                'collision_namespace' => $row['collision_namespace'],
                'source_text' => $source,
                'current_slug' => $currentSlug,
                'target_slug' => $candidate,
                'decision' => $decision,
                'reason' => $reason,
                'current_updated_at' => $entity->getAttribute('updated_at')?->toISOString(),
                'current_path' => null,
                'target_path' => null,
                'path_changed' => false,
            ];
        }

        $this->markCollisions($mappings);
        $this->fillPaths($mappings);

        foreach ($mappings as &$mapping) {
            // Keep the verbose current/target names for the application code,
            // and also emit the audit vocabulary used by the migration
            // runbook so the JSON can be reviewed without interpretation.
            $mapping['old_slug'] = $mapping['current_slug'];
            $mapping['new_slug'] = $mapping['target_slug'];
            $mapping['old_path'] = $mapping['current_path'];
            $mapping['new_path'] = $mapping['target_path'];
            $mapping['expected_status'] = $mapping['path_changed'] ? 301 : 200;
            $mapping['approved_by'] = null;
            $mapping['dependencies'] = [];

            if ($mapping['entity_type'] === 'product') {
                $entity = $this->findEntity('product', (int) $mapping['entity_id']);
                if ($entity instanceof Product && $entity->category) {
                    $mapping['dependencies'] = ['category_id' => (int) $entity->category->id];
                }
            }
        }
        unset($mapping);

        $counts = [];
        foreach ($mappings as $mapping) {
            $counts[$mapping['decision']] = ($counts[$mapping['decision']] ?? 0) + 1;
        }
        ksort($counts);

        $manifest = [
            'version' => 1,
            'site_key' => 'storefront',
            'policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'batch_id' => $batchId,
            'generated_at' => now()->toIso8601String(),
            'source_commit' => config('app.release'),
            'approved_by' => null,
            'data_snapshot' => $this->dataSnapshot(),
            'counts' => $counts,
            'mappings' => $mappings,
        ];
        $manifest['checksum'] = $this->checksum($manifest);

        return $manifest;
    }

    public function checksum(array $manifest): string
    {
        unset($manifest['checksum'], $manifest['generated_at']);
        $this->sortRecursive($manifest);

        return hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array{applied:bool,batch_id:string,changed:int,checksum:string} */
    public function apply(array $manifest, string $expectedChecksum, string $approvedBy): array
    {
        $approvedBy = trim($approvedBy);
        if ($approvedBy === '') {
            throw new InvalidArgumentException('Cần --approved-by để ghi nhận người phê duyệt.');
        }
        if (($manifest['version'] ?? null) !== 1 || ($manifest['policy_version'] ?? null) !== VietnameseSlugNormalizer::POLICY_VERSION) {
            throw new InvalidArgumentException('Manifest không thuộc phiên bản slug policy hiện tại.');
        }
        $actualChecksum = (string) ($manifest['checksum'] ?? '');
        if ($actualChecksum === '' || ! hash_equals($actualChecksum, trim($expectedChecksum)) || ! hash_equals($actualChecksum, $this->checksum($manifest))) {
            throw new InvalidArgumentException('Checksum manifest không khớp; dừng để tránh migrate nhầm snapshot.');
        }

        $mappings = $manifest['mappings'] ?? null;
        if (! is_array($mappings) || $mappings === []) {
            throw new InvalidArgumentException('Manifest không có mappings hợp lệ.');
        }
        foreach ($mappings as $mapping) {
            if (! in_array($mapping['decision'] ?? '', ['KEEP', 'CHANGE'], true)) {
                throw new InvalidArgumentException('Manifest còn mapping chưa được xử lý: '.($mapping['entity_type'] ?? 'unknown').'#'.($mapping['entity_id'] ?? '?'));
            }
            if (($mapping['target_slug'] ?? null) === null || $mapping['target_slug'] === '') {
                throw new InvalidArgumentException('Manifest có target slug rỗng.');
            }
            $this->slugs->validateCustom($mapping['target_slug']);
            if ($this->slugs->isReserved($mapping['target_slug'])) {
                throw new InvalidArgumentException('Manifest có target slug reserved: '.$mapping['target_slug']);
            }
        }

        $this->assertTargetUniqueness($mappings);
        $batchId = trim((string) ($manifest['batch_id'] ?? ''));
        if ($batchId === '') {
            throw new InvalidArgumentException('Manifest thiếu batch_id.');
        }

        return DB::transaction(function () use ($mappings, $batchId, $approvedBy, $actualChecksum): array {
            $entities = [];
            foreach ($mappings as $mapping) {
                $entity = $this->findEntity($mapping['entity_type'], (int) $mapping['entity_id'], true);
                if (! $entity) {
                    throw new InvalidArgumentException('Không tìm thấy entity '.$mapping['entity_type'].'#'.$mapping['entity_id'].'.');
                }
                if ($this->alreadyApplied($mapping, $entity, $batchId)) {
                    $entities[] = [$mapping, $entity, false];
                    continue;
                }
                if ((string) $entity->getAttribute('slug') !== (string) ($mapping['current_slug'] ?? '')) {
                    throw new InvalidArgumentException('Entity đã thay đổi sau khi lập manifest: '.$mapping['entity_type'].'#'.$mapping['entity_id'].'.');
                }
                $snapshot = $mapping['current_updated_at'] ?? null;
                $updatedAt = $entity->getAttribute('updated_at')?->toISOString();
                if ($snapshot !== $updatedAt) {
                    throw new InvalidArgumentException('Entity đã được cập nhật sau khi lập manifest: '.$mapping['entity_type'].'#'.$mapping['entity_id'].'.');
                }
                $entities[] = [$mapping, $entity, true];
            }

            // Release table-level unique indexes before applying a swap or a
            // cycle such as old-a -> old-b and old-b -> old-a.
            foreach ($entities as [$mapping, $entity, $needsChange]) {
                if (! $needsChange) {
                    continue;
                }
                if ((string) $mapping['current_slug'] === (string) $mapping['target_slug']) {
                    continue;
                }
                $entity->forceFill(['slug' => $this->temporarySlug($batchId, $mapping)])->save();
            }

            foreach ($entities as [$mapping, $entity, $needsChange]) {
                if (! $needsChange) {
                    continue;
                }
                $entity->forceFill([
                    'slug' => $mapping['target_slug'],
                    'slug_source' => $mapping['source_text'] ?: $mapping['target_slug'],
                    'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
                    'slug_locked_at' => $entity->getAttribute('slug_locked_at') ?: now(),
                ])->save();
            }

            foreach ($entities as [$mapping, $entity, $needsChange]) {
                if (! $needsChange) {
                    continue;
                }
                $sourcePath = $mapping['current_path'] ?? null;
                $targetPath = $mapping['target_path'] ?? null;
                if (! $sourcePath || ! $targetPath || $sourcePath === $targetPath) {
                    continue;
                }
                $this->redirects->record(
                    $entity,
                    $mapping['route_namespace'],
                    (string) $mapping['current_slug'],
                    $sourcePath,
                    $targetPath,
                    null,
                    'seo_manifest:'.$batchId.':'.$approvedBy,
                    $batchId,
                );
            }

            return [
                'applied' => true,
                'batch_id' => $batchId,
                'changed' => collect($entities)->filter(fn (array $item): bool => $item[2] && ($item[0]['current_path'] !== $item[0]['target_path'] || $item[0]['current_slug'] !== $item[0]['target_slug']))->count(),
                'checksum' => $actualChecksum,
            ];
        }, 3);
    }

    /** @param array<string, mixed> $mapping */
    private function alreadyApplied(array $mapping, Model $entity, string $batchId): bool
    {
        if (($mapping['decision'] ?? null) !== 'CHANGE'
            || (string) $mapping['target_slug'] === ''
            || (string) $entity->getAttribute('slug') !== (string) $mapping['target_slug']) {
            return false;
        }

        $sourcePath = (string) ($mapping['old_path'] ?? $mapping['current_path'] ?? '');
        $targetPath = (string) ($mapping['new_path'] ?? $mapping['target_path'] ?? '');
        if ($sourcePath === '' || $targetPath === '' || $sourcePath === $targetPath) {
            return false;
        }

        return SlugHistory::query()
            ->where('site_key', 'storefront')
            ->where('entity_type', $entity::class)
            ->where('entity_id', $entity->getKey())
            ->where('source_path', $sourcePath)
            ->where('target_path', $targetPath)
            ->where('migration_batch_id', $batchId)
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    private function sourceRows(): array
    {
        $rows = [];
        $definitions = [
            ['category', 'category', 'root', Category::query()->orderBy('id')->get(), 'name'],
            ['product', 'product', 'product', Product::query()->with('category')->orderBy('id')->get(), 'name'],
            ['post', 'post', 'post', Post::query()->orderBy('id')->get(), 'title'],
            ['post-category', 'post-category', 'post-category', PostCategory::query()->orderBy('id')->get(), 'name'],
            ['page', 'page', 'root', Page::query()->orderBy('id')->get(), 'title'],
            ['brand', 'brand', 'brand', Brand::query()->orderBy('id')->get(), 'name'],
        ];

        foreach ($definitions as [$entityType, $routeNamespace, $collisionNamespace, $entities, $sourceField]) {
            foreach ($entities as $entity) {
                $rows[] = [
                    'entity_type' => $entityType,
                    'route_namespace' => $routeNamespace,
                    'collision_namespace' => $collisionNamespace,
                    'source_field' => $sourceField,
                    'entity' => $entity,
                ];
            }
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function markCollisions(array &$mappings): void
    {
        $groups = [];
        foreach ($mappings as $index => $mapping) {
            if (! in_array($mapping['decision'], ['KEEP', 'CHANGE'], true) || ! is_string($mapping['target_slug'])) {
                continue;
            }
            $key = $mapping['collision_namespace'].'|'.$mapping['target_slug'];
            $groups[$key][] = $index;
        }

        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $index) {
                $mappings[$index]['decision'] = 'COLLISION';
                $mappings[$index]['target_slug'] = null;
                $mappings[$index]['reason'] = 'duplicate_target_slug';
            }
        }
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function fillPaths(array &$mappings): void
    {
        $categoryTargets = [];
        foreach ($mappings as $mapping) {
            if ($mapping['entity_type'] === 'category' && is_string($mapping['target_slug'])) {
                $categoryTargets[(int) $mapping['entity_id']] = $mapping['target_slug'];
            }
        }

        foreach ($mappings as &$mapping) {
            $entity = $this->findEntity($mapping['entity_type'], (int) $mapping['entity_id']);
            if (! $entity) {
                continue;
            }
            $currentSlug = (string) $mapping['current_slug'];
            $targetSlug = is_string($mapping['target_slug']) ? $mapping['target_slug'] : $currentSlug;
            $currentPath = $this->entityPath($mapping['entity_type'], $entity, $currentSlug, false, []);
            $targetPath = $this->entityPath($mapping['entity_type'], $entity, $targetSlug, true, $categoryTargets);
            $mapping['current_path'] = $currentPath;
            $mapping['target_path'] = $targetPath;
            $mapping['path_changed'] = $currentPath !== $targetPath;

            if ($mapping['decision'] === 'KEEP' && $mapping['path_changed']) {
                $mapping['decision'] = 'CHANGE';
                $mapping['reason'] = 'category_path_change';
            }
        }
        unset($mapping);
    }

    /** @param array<int, array<string, mixed>> $mappings */
    private function assertTargetUniqueness(array $mappings): void
    {
        $groups = [];
        foreach ($mappings as $mapping) {
            $slug = (string) ($mapping['target_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $key = $mapping['collision_namespace'].'|'.$slug;
            $groups[$key][] = $mapping['entity_type'].'#'.$mapping['entity_id'];

            $targetPath = (string) ($mapping['target_path'] ?? '');
            // Historical aliases are permanently owned by the entity they
            // redirect to. A target path must therefore never be reused,
            // including by a KEEP mapping whose current path already points
            // at an old alias because data was changed outside this service.
            if ($targetPath !== ''
                && SlugHistory::query()->where('site_key', 'storefront')->where('source_path', $targetPath)->exists()) {
                throw new InvalidArgumentException('Target path đã tồn tại trong lịch sử URL: '.$targetPath);
            }
        }
        foreach ($groups as $key => $owners) {
            if (count($owners) > 1) {
                throw new InvalidArgumentException('Target slug collision '.$key.': '.implode(', ', $owners));
            }
        }
    }

    private function entityPath(string $type, Model $entity, string $slug, bool $target, array $categoryTargets): ?string
    {
        if ($slug === '') {
            return null;
        }
        return match ($type) {
            'category' => $this->urls->categoryPathForSlug($slug),
            'product' => $this->productPath($entity, $slug, $target, $categoryTargets),
            'post' => $this->urls->postPathForSlug($slug),
            'post-category' => $this->urls->postCategoryPathForSlug($slug),
            'page' => $this->urls->pagePathForSlug($slug),
            'brand' => $this->urls->brandPathForSlug($slug),
            default => null,
        };
    }

    private function productPath(Model $entity, string $slug, bool $target, array $categoryTargets): ?string
    {
        $category = $entity instanceof Product ? $entity->category : null;
        if (! $category || $category->slug === null) {
            return null;
        }
        $categorySlug = $target ? ($categoryTargets[(int) $category->id] ?? (string) $category->slug) : (string) $category->slug;

        return $this->urls->productPathForSlug($entity, $slug, $categorySlug);
    }

    private function findEntity(string $type, int $id, bool $forUpdate = false): ?Model
    {
        $query = match ($type) {
            'category' => Category::query(),
            'product' => Product::query()->with('category'),
            'post' => Post::query(),
            'post-category' => PostCategory::query(),
            'page' => Page::query(),
            'brand' => Brand::query(),
            default => null,
        };

        if ($query === null) {
            return null;
        }

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->whereKey($id)->first();
    }

    private function temporarySlug(string $batchId, array $mapping): string
    {
        return 'seo-migrate-'.substr(hash('sha256', $batchId.'|'.$mapping['entity_type'].'|'.$mapping['entity_id']), 0, 32);
    }

    /** @return array<string, array{count:int,max_updated_at:?string}> */
    private function dataSnapshot(): array
    {
        $queries = [
            'categories' => Category::query(),
            'products' => Product::query(),
            'posts' => Post::query(),
            'post_categories' => PostCategory::query(),
            'pages' => Page::query(),
            'brands' => Brand::query(),
        ];

        $snapshot = [];
        foreach ($queries as $table => $query) {
            $max = $query->max('updated_at');
            $snapshot[$table] = [
                'count' => (int) $query->count(),
                'max_updated_at' => $max ? (string) $max : null,
            ];
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $value */
    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        unset($item);
        ksort($value);
    }
}
