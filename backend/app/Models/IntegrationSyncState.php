<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSyncState extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_successful_watermark' => 'datetime',
        'last_started_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'items_processed' => 'integer',
        'items_matched' => 'integer',
        'items_unmatched' => 'integer',
    ];
}
