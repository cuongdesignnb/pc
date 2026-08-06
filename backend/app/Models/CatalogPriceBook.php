<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogPriceBook extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'provider_updated_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CatalogProductPrice::class, 'price_book_id');
    }
}
