<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogChannelSyncConflict extends Model
{
    protected $guarded = [];

    protected $casts = [
        'details_json' => 'array',
        'resolved_at' => 'datetime',
    ];
}
