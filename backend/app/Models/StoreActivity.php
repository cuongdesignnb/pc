<?php

namespace App\Models;

use App\Support\PublicAssetUrl;
use App\Support\StoreActivityVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo or video of the shop shown in the homepage "Hoạt động tại cửa
 * hàng" grid. Videos are YouTube/Facebook links or MP4 files.
 */
class StoreActivity extends Model
{
    public const TYPES = ['image', 'video'];

    protected $fillable = ['title', 'type', 'image', 'video_url', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, mixed>|null Null when a video link can no longer be played. */
    public function toStorefront(): ?array
    {
        $video = $this->type === 'video' ? StoreActivityVideo::resolve($this->video_url) : null;
        if ($this->type === 'video' && $video === null) {
            return null;
        }

        $image = PublicAssetUrl::normalize($this->image) ?: ($video['thumbnail_url'] ?? null);
        if ($this->type === 'image' && ! $image) {
            return null;
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'image' => $image,
            'video' => $video,
        ];
    }
}
