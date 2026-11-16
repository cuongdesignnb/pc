<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One write made with an admin API token (order status, payment, shipment). */
class AdminApiWriteLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'status_code' => 'integer',
    ];
}
