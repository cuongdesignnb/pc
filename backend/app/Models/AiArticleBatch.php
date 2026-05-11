<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiArticleBatch extends Model
{
    protected $fillable = [
        'name', 'keywords', 'status', 'ai_provider',
        'default_category_id', 'schedule_at', 'auto_publish',
        'total_items', 'completed_items',
    ];

    protected $casts = [
        'keywords' => 'array',
        'auto_publish' => 'boolean',
        'schedule_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AiArticleBatchItem::class, 'batch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'default_category_id');
    }
}
