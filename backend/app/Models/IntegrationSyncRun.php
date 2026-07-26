<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'totals_json' => 'array',
        'warnings_json' => 'array',
        'pages_processed' => 'integer',
        'remote_processed' => 'integer',
        'created' => 'integer',
        'updated' => 'integer',
        'unchanged' => 'integer',
        'images_downloaded' => 'integer',
        'warnings' => 'integer',
        'errors' => 'integer',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
