<?php

namespace App\Http\Resources;

use App\Models\Author;
use App\Models\Post;
use App\Services\Seo\PublicUrlResolver;
use App\Support\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Byline of a post. A post with an active author (Nội dung → Tác giả) shows
 * that author with a link to /tin-tuc/tac-gia/{slug}; older posts without one
 * keep showing the admin user who wrote them (no author page).
 *
 * @mixin \App\Models\Author
 */
class PostAuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urls = app(PublicUrlResolver::class);
        $path = $urls->authorPathForSlug((string) $this->slug);

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'avatar' => PublicAssetUrl::normalize($this->avatar) ?: null,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'same_as' => $this->resource->sameAs(),
            'canonical_path' => $path,
            'url' => $urls->absolute($path),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function forPost(Post $post, Request $request): ?array
    {
        $author = $post->relationLoaded('authorProfile') ? $post->authorProfile : ($post->author_id ? $post->authorProfile()->first() : null);
        if ($author instanceof Author && $author->is_active) {
            return self::make($author)->resolve($request);
        }

        $user = $post->relationLoaded('author') ? $post->author : null;
        if (! $user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'slug' => null,
            'avatar' => PublicAssetUrl::normalize($user->avatar ?? null) ?: null,
            'job_title' => null,
            'bio' => null,
            'same_as' => [],
            'canonical_path' => null,
            'url' => null,
        ];
    }
}
