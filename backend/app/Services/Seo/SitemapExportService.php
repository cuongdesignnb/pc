<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SitemapExportService
{
    public function __construct(
        private readonly PublicUrlResolver $urls,
        private readonly IndexabilityPolicy $policy,
    ) {}

    public function indexXml(): string
    {
        $shards = [
            '/sitemaps/static.xml',
            '/sitemaps/categories.xml',
            '/sitemaps/post-categories.xml',
        ];
        $pageSize = max(1, (int) config('seo.sitemap_page_size', 50000));
        $productPages = (int) ceil(Product::query()
            ->visibleOnStorefront()
            ->whereHas('category', fn (Builder $query) => $query->visibleOnStorefront())
            ->count() / $pageSize);
        $postPages = (int) ceil(Post::query()->published()->count() / $pageSize);

        for ($page = 1; $page <= $productPages; $page++) {
            $shards[] = '/sitemaps/products-'.$page.'.xml';
        }
        for ($page = 1; $page <= $postPages; $page++) {
            $shards[] = '/sitemaps/posts-'.$page.'.xml';
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($shards as $path) {
            $body .= '<sitemap><loc>'.$this->xml($this->urls->requireOrigin().$path).'</loc></sitemap>';
        }

        return $body.'</sitemapindex>';
    }

    public function shardXml(string $shard): ?string
    {
        $pageSize = max(1, (int) config('seo.sitemap_page_size', 50000));
        if ($shard === 'static') {
            return $this->urlSetXml($this->staticEntries());
        }
        if ($shard === 'categories') {
            return $this->urlSetXml($this->categoryEntries());
        }
        if ($shard === 'post-categories') {
            return $this->urlSetXml($this->postCategoryEntries());
        }
        if (preg_match('/^products-(\d+)$/', $shard, $matches) === 1) {
            $page = (int) $matches[1];
            return $this->productPageExists($page, $pageSize)
                ? $this->urlSetXml($this->productEntries($page, $pageSize))
                : null;
        }
        if (preg_match('/^posts-(\d+)$/', $shard, $matches) === 1) {
            $page = (int) $matches[1];
            return $this->postPageExists($page, $pageSize)
                ? $this->urlSetXml($this->postEntries($page, $pageSize))
                : null;
        }

        return null;
    }

    /** @return list<array{path:string,lastmod:?CarbonInterface}> */
    private function staticEntries(): array
    {
        return collect((array) config('seo.static_paths', []))
            ->filter(fn (mixed $path): bool => $this->policy->isIndexablePath((string) $path))
            ->map(fn (mixed $path): array => ['path' => (string) $path, 'lastmod' => null])
            ->values()
            ->all();
    }

    /** @return list<array{path:string,lastmod:?CarbonInterface}> */
    private function categoryEntries(): array
    {
        return Category::query()
            ->visibleOnStorefront()
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->map(function (Category $category): ?array {
                $path = $this->urls->categoryPath($category);

                return $path === null ? null : [
                    'path' => $path,
                    'lastmod' => $category->updated_at,
                ];
            })
            ->filter(fn (?array $entry): bool => $entry !== null && $this->policy->isIndexablePath($entry['path']))
            ->values()
            ->all();
    }

    /** @return list<array{path:string,lastmod:?CarbonInterface}> */
    private function postCategoryEntries(): array
    {
        return PostCategory::query()
            ->whereHas('posts', fn (Builder $query) => $query->published())
            ->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->map(function (PostCategory $category): ?array {
                $path = $this->urls->postCategoryPath($category);

                return $path === null ? null : [
                    'path' => $path,
                    'lastmod' => $category->updated_at,
                ];
            })
            ->filter(fn (?array $entry): bool => $entry !== null && $this->policy->isIndexablePath($entry['path']))
            ->values()
            ->all();
    }

    /** @return list<array{path:string,lastmod:?CarbonInterface}> */
    private function productEntries(int $page, int $pageSize): array
    {
        if ($page < 1) {
            return [];
        }

        return Product::query()
            ->with('category')
            ->visibleOnStorefront()
            ->whereHas('category', fn (Builder $query) => $query->visibleOnStorefront())
            ->orderBy('id')
            ->forPage($page, $pageSize)
            ->get(['id', 'category_id', 'slug', 'updated_at'])
            ->map(function (Product $product): ?array {
                $path = $this->urls->productPath($product);
                return $path === null || ! $this->policy->isIndexablePath($path)
                    ? null
                    : ['path' => $path, 'lastmod' => $product->updated_at];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<array{path:string,lastmod:?CarbonInterface}> */
    private function postEntries(int $page, int $pageSize): array
    {
        if ($page < 1) {
            return [];
        }

        return Post::query()
            ->published()
            ->orderBy('id')
            ->forPage($page, $pageSize)
            ->get(['id', 'slug', 'updated_at'])
            ->map(function (Post $post): ?array {
                $path = $this->urls->postPath($post);

                return $path === null ? null : [
                    'path' => $path,
                    'lastmod' => $post->updated_at,
                ];
            })
            ->filter(fn (?array $entry): bool => $entry !== null && $this->policy->isIndexablePath($entry['path']))
            ->values()
            ->all();
    }

    private function productPageExists(int $page, int $pageSize): bool
    {
        if ($page < 1) {
            return false;
        }

        $total = Product::query()
            ->visibleOnStorefront()
            ->whereHas('category', fn (Builder $query) => $query->visibleOnStorefront())
            ->count();

        return $page <= max(1, (int) ceil($total / $pageSize));
    }

    private function postPageExists(int $page, int $pageSize): bool
    {
        if ($page < 1) {
            return false;
        }

        return $page <= max(1, (int) ceil(Post::query()->published()->count() / $pageSize));
    }

    /** @param list<array{path:string,lastmod:?CarbonInterface}> $entries */
    private function urlSetXml(array $entries): string
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($entries as $entry) {
            $body .= '<url><loc>'.$this->xml($this->urls->requireOrigin().$entry['path']).'</loc>';
            if ($entry['lastmod'] instanceof CarbonInterface) {
                $body .= '<lastmod>'.$this->xml($entry['lastmod']->toIso8601String()).'</lastmod>';
            }
            $body .= '</url>';
        }

        return $body.'</urlset>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
