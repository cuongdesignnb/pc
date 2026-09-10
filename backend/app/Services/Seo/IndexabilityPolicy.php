<?php

namespace App\Services\Seo;

/**
 * Shared indexability rules for sitemap generation and SEO documentation.
 * Query URLs are never put in a sitemap; page > 1 is a valid public URL but
 * remains self-canonical in the storefront.
 */
class IndexabilityPolicy
{
    public function isPrivatePath(string $path): bool
    {
        $path = '/'.trim($path, '/');
        foreach ((array) config('seo.private_paths', []) as $private) {
            $private = '/'.trim((string) $private, '/');
            if ($path === $private || str_starts_with($path, $private.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isIndexablePath(?string $path): bool
    {
        if ($path === null || $path === '' || str_contains($path, '?') || str_contains($path, '#')) {
            return false;
        }

        return ! $this->isPrivatePath($path) && str_starts_with($path, '/');
    }

    /** @param array<string, mixed> $query */
    public function isIndexableQuery(array $query): bool
    {
        $blockedKeys = [
            'search', 'q', 'category', 'brand', 'brands', 'component_type',
            'min_price', 'max_price', 'in_stock', 'sort', 'order', 'variant',
            'sku', 'filter', 'filters', 'page_size', 'per_page', 'cursor',
        ];
        foreach (array_keys($query) as $key) {
            if (in_array((string) $key, $blockedKeys, true)) {
                return false;
            }
            if (str_starts_with((string) $key, 'f_') || str_starts_with((string) $key, 'spec_')) {
                return false;
            }
        }

        return array_keys($query) === [] || (count($query) === 1 && array_key_exists('page', $query));
    }
}
