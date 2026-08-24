<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationSchedule extends Model
{
    protected $fillable = [
        'topic', 'keywords', 'type', 'tone', 'length', 'full_article', 'with_images', 'image_count',
        'auto_publish', 'category_id', 'product_id', 'status', 'attempts', 'error_message', 'warnings',
        'scheduled_at', 'locked_at', 'article_id', 'started_at', 'processed_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'full_article' => 'boolean',
        'with_images' => 'boolean',
        'auto_publish' => 'boolean',
        'warnings' => 'array',
        'scheduled_at' => 'datetime',
        'locked_at' => 'datetime',
        'started_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }
}
