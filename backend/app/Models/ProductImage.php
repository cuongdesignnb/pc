<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'provider',
        'remote_image_id',
        'url',
        'source_url',
        'storage_path',
        'checksum',
        'mime_type',
        'file_size',
        'width',
        'height',
        'alt_text',
        'sort_order',
        'is_primary',
        'synced_at',
    ];

    protected $appends = ['alt'];

    protected $hidden = ['alt_text'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
        'remote_image_id' => 'integer',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'synced_at' => 'datetime',
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
