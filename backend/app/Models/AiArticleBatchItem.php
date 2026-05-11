<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiArticleBatchItem extends Model
{
    protected $fillable = [
        'batch_id', 'keyword', 'post_id', 'status',
        'error_message', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AiArticleBatch::class, 'batch_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
