<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a CTV withdraws from approved commission: "pending" (chờ chuyển)
 * until staff transfer it, then "paid" (đã rút); "cancelled" returns the
 * amount to the CTV's balance.
 */
class AffiliatePayout extends Model
{
    public const STATUSES = ['pending', 'paid', 'cancelled'];

    protected $fillable = [
        'affiliate_id',
        'amount',
        'status',
        'source',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'paid_at',
        'paid_by',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'paid_at' => 'datetime',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
