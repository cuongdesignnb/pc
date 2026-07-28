<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogChannelSyncRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'summary_json' => 'array',
        'items_total' => 'integer',
        'items_valid' => 'integer',
        'items_invalid' => 'integer',
        'items_created' => 'integer',
        'items_updated' => 'integer',
        'items_skipped' => 'integer',
        'warnings' => 'integer',
        'errors' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
