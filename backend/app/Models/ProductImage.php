<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $appends = ['alt'];

    protected $hidden = ['alt_text'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function getAltAttribute(): ?string
    {
        return $this->alt_text;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
