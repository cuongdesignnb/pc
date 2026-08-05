<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProductPrice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'provider_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(CatalogPriceBook::class, 'price_book_id');
    }
}
