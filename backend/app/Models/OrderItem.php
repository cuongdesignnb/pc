<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'product_name',
        'variant_name',
        'sku',
        'quantity',
        'price',
        'total',
        'warranty_months',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'variant_id' => 'integer',
        'price' => 'decimal:0',
        'total' => 'decimal:0',
        'warranty_months' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
