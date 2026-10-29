<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A sale made by phone or in the shop, recorded by staff for a CTV. */
class AffiliateExternalOrder extends Model
{
    protected $fillable = ['affiliate_id', 'customer_phone', 'kiot_invoice_code', 'category_id', 'amount', 'sold_at', 'note', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:0',
        'sold_at' => 'date',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class, 'external_order_id');
    }
}
