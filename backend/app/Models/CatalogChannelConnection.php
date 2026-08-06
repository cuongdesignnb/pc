<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogChannelConnection extends Model
{
    public const WEBSITE = 'website';

    public const GOOGLE_SHEETS = 'google_sheets';

    public const GOOGLE_MERCHANT = 'google_merchant';

    public const META_CATALOG = 'meta_catalog';

    public const CHANNELS = [self::WEBSITE, self::GOOGLE_SHEETS, self::GOOGLE_MERCHANT, self::META_CATALOG];

    protected $guarded = [];

    protected $hidden = ['configuration_encrypted'];

    protected function casts(): array
    {
        return [
            'configuration_encrypted' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CatalogChannelEvent::class);
    }

    public function googleSheetPriceColumns(): HasMany
    {
        return $this->hasMany(CatalogGoogleSheetPriceColumn::class, 'connection_id');
    }
}
