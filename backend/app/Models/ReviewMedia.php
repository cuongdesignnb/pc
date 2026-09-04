<?php

namespace App\Models;

use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewMedia extends Model
{
    protected $table = 'review_media';

    protected $fillable = ['review_id', 'url', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function getUrlAttribute($value): string
    {
        return (string) PublicAssetUrl::normalize($value);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
