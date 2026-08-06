<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogGoogleSheetPriceColumn extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CatalogChannelConnection::class, 'connection_id');
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(CatalogPriceBook::class, 'price_book_id');
    }
}
