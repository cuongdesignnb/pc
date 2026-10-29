<?php

namespace App\Models;

use App\Services\Catalog\InvalidWarrantyPackage;
use App\Services\Catalog\WarrantyPackages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'warranty_months',
        'quantity',
        'price',
        'is_selected',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'variant_id' => 'integer',
        'warranty_months' => 'integer',
        'price' => 'decimal:0',
        'is_selected' => 'boolean',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * The warranty package this line pays for, or null when it uses the
     * product's default warranty or the package is no longer offered.
     *
     * @return array{months: int, price: int}|null
     */
    public function warrantyPackage(): ?array
    {
        try {
            return app(WarrantyPackages::class)->select($this->product, $this->warranty_months);
        } catch (InvalidWarrantyPackage) {
            return null;
        }
    }

    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }
}
