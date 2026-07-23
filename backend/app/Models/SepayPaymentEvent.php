<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SepayPaymentEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'external_transaction_id' => 'integer',
        'amount' => 'decimal:0',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
