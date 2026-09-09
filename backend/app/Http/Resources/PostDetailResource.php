<?php

namespace App\Http\Resources;

use App\Services\News\ArticleContentSanitizer;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = app(ArticleContentSanitizer::class)->sanitizeAndBuildToc($this->body, $this->title);
        $category = $this->relationLoaded('category') && $this->category ? [
            'id' => (int) $this->category->id,
            'name' => (string) $this->category->name,
            'slug' => (string) $this->category->slug,
        ] : null;
        $author = $this->relationLoaded('author') && $this->author
            ? PostAuthorResource::make($this->author)->resolve($request)
            : null;
        $seoTitle = filled($this->meta_title) ? (string) $this->meta_title : (string) $this->title;
        $seoDescription = filled($this->meta_description)
            ? (string) $this->meta_description
            : ($this->excerpt === null ? null : (string) $this->excerpt);

        return [
            'id' => (int) $this->id,
            'title' => (string) $this->title,
            'slug' => (string) $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $content['body'],
            'featured_image' => PublicAssetUrl::normalize($this->featured_image),
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'view_count' => (int) $this->view_count,
            'category' => $category,
            'author' => $author,
            'seo' => [
                'title' => $seoTitle,
                'description' => $seoDescription,
                'image' => PublicAssetUrl::normalize($this->featured_image),
            ],
            'toc' => $content['toc'],
        ];
    }
}
