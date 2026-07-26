<?php

namespace App\Services\Integrations\Kiot;

use App\Models\Category;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class KiotCategorySyncService
{
    public function sync(array $items, bool $dryRun, array &$report): void
    {
        $items = collect($items)
            ->filter(fn ($item) => is_array($item) && (int) ($item['id'] ?? 0) > 0)
            ->keyBy(fn (array $item) => (int) $item['id']);
        $existing = Category::query()
            ->where('provider', 'kiot')
            ->whereIn('remote_category_id', $items->keys())
            ->get()
            ->keyBy('remote_category_id');
        $changedRemoteIds = [];

        foreach ($items as $remoteId => $remote) {
            $category = $existing->get($remoteId);
            $status = (string) ($remote['sync_status'] ?? (($remote['is_active'] ?? true) ? 'active' : 'inactive'));
            $active = $status === 'active' && (bool) ($remote['is_active'] ?? true);
            $visible = $active && (bool) ($remote['show_on_pc_website'] ?? false);
            $attributes = [
                'name' => trim((string) ($remote['name'] ?? '')) ?: 'KIOT Category '.$remoteId,
                'slug' => $this->uniqueSlug((string) ($remote['slug'] ?? $remote['name'] ?? ''), $remoteId, $category?->id),
                'is_active' => $active,
                'show_on_pc_website' => $visible,
                'provider_sync_status' => $status,
                'provider_sync_checksum' => $this->checksum($remote),
                'provider_updated_at' => isset($remote['updated_at']) ? CarbonImmutable::parse($remote['updated_at']) : null,
            ];

            if (! $category) {
                $report['category_create']++;
                $changedRemoteIds[$remoteId] = true;
                if (! $dryRun) {
                    $category = Category::create($attributes + [
                        'provider' => 'kiot',
                        'remote_category_id' => $remoteId,
                        'provider_synced_at' => now(),
                    ]);
                    $existing->put($remoteId, $category);
                }
            } elseif ($category->fill($attributes)->isDirty()) {
                $report['category_update']++;
                $changedRemoteIds[$remoteId] = true;
                if (! $dryRun) {
                    $category->provider_synced_at = now();
                    $category->save();
                }
            }

            if (! $visible) {
                $report['category_hidden']++;
            }
        }

        $allMapped = Category::query()
            ->where('provider', 'kiot')
            ->get(['id', 'remote_category_id', 'parent_id'])
            ->keyBy('remote_category_id');
        foreach ($items as $remoteId => $remote) {
            $parentRemoteId = (int) ($remote['parent_id'] ?? 0) ?: null;
            $category = $dryRun ? $existing->get($remoteId) : $allMapped->get($remoteId);
            $parent = $parentRemoteId ? $allMapped->get($parentRemoteId) : null;
            if ($parentRemoteId && ! $parent) {
                $report['warning_details'][] = [
                    'code' => 'CATEGORY_PARENT_MISSING',
                    'remote_category_id' => $remoteId,
                    'parent_remote_category_id' => $parentRemoteId,
                ];
                $report['warnings']++;

                continue;
            }
            if ($category && (int) $category->parent_id !== (int) ($parent?->id)) {
                if (! $dryRun) {
                    $category->update(['parent_id' => $parent?->id]);
                }
                if (! isset($changedRemoteIds[$remoteId])) {
                    $report['category_update']++;
                }
            }
        }
    }

    private function uniqueSlug(string $value, int $remoteId, ?int $ignoreCategoryId): string
    {
        $base = Str::slug($value) ?: 'kiot-category-'.$remoteId;
        $slug = $base;
        $suffix = 0;
        while (Category::query()->where('slug', $slug)
            ->when($ignoreCategoryId, fn ($query) => $query->whereKeyNot($ignoreCategoryId))
            ->exists() || Product::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$remoteId.($suffix > 1 ? '-'.$suffix : '');
        }

        return $slug;
    }

    private function checksum(array $remote): string
    {
        return hash('sha256', json_encode([
            'id' => (int) $remote['id'],
            'code' => $remote['code'] ?? null,
            'name' => $remote['name'] ?? null,
            'slug' => $remote['slug'] ?? null,
            'parent_id' => $remote['parent_id'] ?? null,
            'is_active' => (bool) ($remote['is_active'] ?? true),
            'show_on_pc_website' => (bool) ($remote['show_on_pc_website'] ?? false),
            'sync_status' => $remote['sync_status'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
