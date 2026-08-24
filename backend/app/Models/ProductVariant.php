<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'sale_price',
        'stock_quantity',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:0',
        'sale_price' => 'decimal:0',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['display_price', 'is_available'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getDisplayPriceAttribute(): int
    {
        return (int) ($this->sale_price ?? $this->price);
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }
}
