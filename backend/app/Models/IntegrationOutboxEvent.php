<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationOutboxEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'attempt_count' => 'integer',
        'next_attempt_at' => 'datetime',
        'locked_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
