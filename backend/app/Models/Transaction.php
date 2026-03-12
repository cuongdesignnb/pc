<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'order_id',
        'sepay_transaction_id',
        'gateway',
        'amount',
        'reference_code',
        'content',
        'transaction_date',
    ];

    protected $casts = [
        'sepay_transaction_id' => 'integer',
        'amount' => 'decimal:0',
        'transaction_date' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
