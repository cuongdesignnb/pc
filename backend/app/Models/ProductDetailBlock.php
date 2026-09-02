<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetailBlock extends Model
{
    public const TYPES = ['hero_banner', 'feature_cards', 'benchmark_cards', 'use_case_cards', 'notice', 'image_text'];

    protected $fillable = ['product_id', 'type', 'title', 'payload', 'sort_order', 'is_active'];

    protected $casts = [
        'payload' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
