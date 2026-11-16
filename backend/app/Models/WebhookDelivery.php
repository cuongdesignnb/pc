<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One webhook call (pending, sent, failed and waiting for a retry, or dead). */
class WebhookDelivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'attempt_count' => 'integer',
        'response_status' => 'integer',
        'next_attempt_at' => 'datetime',
        'locked_until' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
