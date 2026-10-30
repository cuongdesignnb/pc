<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\News\ArticleContentSanitizer;
use App\Services\Seo\PublicUrlResolver;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use App\Support\PolicyPages;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function show(
        string $slug,
        SlugRedirectService $redirects,
        PublicUrlResolver $urls,
        VietnameseSlugNormalizer $slugs,
        ArticleContentSanitizer $sanitizer,
    ): JsonResponse {
        abort_if($slugs->isReserved($slug) || $urls->pagePathForSlug($slug) === null, 404);

        // A category owns its existing root URL, including historical aliases.
        abort_if($redirects->categoryBySlug($slug) !== null, 404);
        $page = $redirects->pageBySlug($slug);
        abort_unless($page && $page->is_active, 404);
        $canonicalPath = $urls->pagePathForSlug((string) $page->slug);
        abort_if($canonicalPath === null || $slugs->isReserved((string) $page->slug), 404);
        abort_if($redirects->categoryBySlug((string) $page->slug) !== null, 404);

        return response()->json([
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $sanitizer->sanitize(PolicyPages::fill($page->body), $page->title),
                'meta_title' => PolicyPages::fill($page->meta_title, false),
                'meta_description' => PolicyPages::fill($page->meta_description, false),
                'canonical_path' => $canonicalPath,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
