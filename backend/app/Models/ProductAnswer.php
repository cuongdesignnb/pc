<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnswer extends Model
{
    protected $fillable = ['question_id', 'user_id', 'body', 'is_official', 'is_approved'];

    protected $casts = [
        'is_official' => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class, 'question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
