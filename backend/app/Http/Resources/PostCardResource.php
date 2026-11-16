<?php

namespace App\Http\Resources;

use App\Services\Seo\PublicUrlResolver;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urls = app(PublicUrlResolver::class);

        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'public_url' => $urls->absolute($urls->postPath($this->resource)),
            'excerpt' => $this->excerpt,
            'featured_image' => PublicAssetUrl::normalize($this->featured_image),
            'published_at' => $this->published_at?->toISOString(),
            'view_count' => (int) $this->view_count,
            'is_featured' => (bool) $this->is_featured,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => (int) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'canonical_path' => $urls->postCategoryPath($this->category),
            ] : null),
            'author' => $this->when(
                $this->resource->relationLoaded('authorProfile') || $this->resource->relationLoaded('author'),
                fn () => ($author = PostAuthorResource::forPost($this->resource, $request)) ? [
                    'name' => $author['name'],
                    'slug' => $author['slug'],
                    'canonical_path' => $author['canonical_path'],
                ] : null,
            ),
        ];
    }
}
