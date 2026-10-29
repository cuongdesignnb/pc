<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commission on one order. "pending" until the order has been delivered for
 * the approval window or staff confirm it, then "approved" (withdrawable);
 * "cancelled" when the order is cancelled or returned.
 */
class AffiliateCommission extends Model
{
    public const STATUSES = ['pending', 'approved', 'cancelled'];

    protected $fillable = [
        'affiliate_id',
        'order_id',
        'external_order_id',
        'base_amount',
        'amount',
        'breakdown',
        'status',
        'approve_after',
        'approved_at',
        'approved_by',
        'cancelled_reason',
    ];

    protected $casts = [
        'base_amount' => 'decimal:0',
        'amount' => 'decimal:0',
        'breakdown' => 'array',
        'approve_after' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function externalOrder(): BelongsTo
    {
        return $this->belongsTo(AffiliateExternalOrder::class);
    }
}
