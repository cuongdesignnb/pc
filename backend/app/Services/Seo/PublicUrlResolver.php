<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Exceptions\SeoSlugException;
use RuntimeException;

class PublicUrlResolver
{
    public function __construct(private readonly VietnameseSlugNormalizer $slugs) {}

    public function origin(): ?string
    {
        // Keep the SEO origin authoritative in production while preserving
        // the existing catalog storefront setting for local jobs, tests and
        // installations that have not introduced SEO_SITE_ORIGIN yet.
        $configured = config('seo.site_origin') ?: config('catalog.storefront_url');
        if (! is_string($configured) || trim($configured) === '') {
            return null;
        }

        $origin = rtrim(trim($configured), '/');
        $parts = parse_url($origin);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || blank($parts['host'] ?? null)
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')) {
            return null;
        }
        if (app()->environment('production') && strtolower((string) $parts['scheme']) !== 'https') {
            return null;
        }

        return $origin;
    }

    public function requireOrigin(): string
    {
        return $this->origin() ?? throw new RuntimeException('SEO_SITE_ORIGIN/CATALOG_STOREFRONT_URL chưa được cấu hình hợp lệ.');
    }

    public function categoryPath(Category $category): ?string
    {
        return $this->categoryPathForSlug((string) $category->slug);
    }

    public function categoryPathForSlug(string $slug): ?string
    {
        return $this->segmentPath($slug);
    }

    public function productPath(Product $product): ?string
    {
        return $this->productPathForSlug($product, (string) $product->slug);
    }

    public function productPathForSlug(Product $product, string $productSlug, ?string $categorySlug = null): ?string
    {
        $category = $product->relationLoaded('category') ? $product->category : $product->category()->first();
        if (! $category || blank($category->slug)) {
            return null;
        }

        $categoryPath = $categorySlug === null
            ? $this->categoryPath($category)
            : $this->categoryPathForSlug($categorySlug);
        $productPathSegment = $this->segment($productSlug);
        if ($categoryPath === null || $productPathSegment === null) {
            return null;
        }

        return $categoryPath.'/'.$productPathSegment;
    }

    public function postPath(Post $post): ?string
    {
        return $this->postPathForSlug((string) $post->slug);
    }

    public function postPathForSlug(string $slug): ?string
    {
        $slug = $this->segment($slug);

        return $slug === null ? null : '/tin-tuc/'.$slug;
    }

    public function postCategoryPath(PostCategory $category): ?string
    {
        return $this->postCategoryPathForSlug((string) $category->slug);
    }

    public function postCategoryPathForSlug(string $slug): ?string
    {
        $slug = $this->segment($slug);

        return $slug === null ? null : '/tin-tuc/chuyen-muc/'.$slug;
    }

    public function pagePathForSlug(string $slug): ?string
    {
        return $this->segmentPath($slug);
    }

    public function brandPathForSlug(string $slug): ?string
    {
        $slug = $this->segment($slug);

        return $slug === null ? null : '/thuong-hieu/'.$slug;
    }

    public function absolute(?string $path): ?string
    {
        if ($path === null || $path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '?') || str_contains($path, '#')) {
            return null;
        }

        $origin = $this->origin();
        return $origin === null ? null : $origin.'/'.ltrim($path, '/');
    }

    public function categoryUrl(Category $category): ?string
    {
        return $this->absolute($this->categoryPath($category));
    }

    public function productUrl(Product $product): ?string
    {
        return $this->absolute($this->productPath($product));
    }

    public function postUrl(Post $post): ?string
    {
        return $this->absolute($this->postPath($post));
    }

    private function segmentPath(string $value): ?string
    {
        $segment = $this->segment($value);

        return $segment === null ? null : '/'.$segment;
    }

    private function segment(string $value): ?string
    {
        $value = trim($value, '/');
        if ($value === '' || str_contains($value, '/') || str_contains($value, '?') || str_contains($value, '#')) {
            return null;
        }

        try {
            $value = $this->slugs->validateCustom($value);
        } catch (SeoSlugException) {
            return null;
        }

        return $this->slugs->isReserved($value) ? null : $value;
    }
}
