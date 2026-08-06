<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChannelSyncRunItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'validation_errors_json' => 'array',
        'selected_price' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(CatalogChannelSyncRun::class, 'sync_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
