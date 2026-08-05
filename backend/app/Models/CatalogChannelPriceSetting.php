<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChannelPriceSetting extends Model
{
    public const WEBSITE = 'website';

    public const GOOGLE_SHEETS = 'google_sheets';

    public const GOOGLE_MERCHANT = 'google_merchant';

    public const META_CATALOG = 'meta_catalog';

    public const CHANNELS = [self::WEBSITE, self::GOOGLE_SHEETS, self::GOOGLE_MERCHANT, self::META_CATALOG];

    public const PRICE_SOURCES = ['retail_price', 'selected_price', 'all_price_books'];

    public const FALLBACK_POLICIES = ['none', 'retail_price', 'selected_price'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configured_at' => 'datetime',
        ];
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(CatalogPriceBook::class, 'price_book_id');
    }
}
