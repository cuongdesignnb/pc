<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A byline for news articles, managed in admin under Nội dung → Tác giả. */
class Author extends Model
{
    /** Social profile keys the admin form offers; used for schema.org sameAs. */
    public const SOCIAL_KEYS = ['website', 'facebook', 'youtube', 'tiktok', 'linkedin', 'x'];

    protected $fillable = ['name', 'slug', 'avatar', 'job_title', 'bio', 'social_links', 'is_active'];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return list<string> Non-empty https profile links. */
    public function sameAs(): array
    {
        return collect((array) $this->social_links)
            ->only(self::SOCIAL_KEYS)
            ->filter(fn (mixed $url): bool => is_string($url) && str_starts_with($url, 'https://'))
            ->values()
            ->all();
    }
}
