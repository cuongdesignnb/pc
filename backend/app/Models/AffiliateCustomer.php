<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer phone number that belongs to a CTV for good. */
class AffiliateCustomer extends Model
{
    protected $fillable = ['phone', 'affiliate_id', 'user_id', 'source', 'first_discount_order_id', 'assigned_by'];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
