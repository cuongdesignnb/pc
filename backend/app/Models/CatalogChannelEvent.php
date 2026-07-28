<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChannelEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CatalogChannelConnection::class, 'catalog_channel_connection_id');
    }
}
