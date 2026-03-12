<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecification extends Model
{
    protected $fillable = [
        'product_id',
        'specification_key_id',
        'value_string',
        'value_numeric',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:2',
    ];

    /**
     * Get the display value (string or numeric)
     */
    public function getValueAttribute(): ?string
    {
        if ($this->value_string !== null) {
            return $this->value_string;
        }
        if ($this->value_numeric !== null) {
            return rtrim(rtrim((string) $this->value_numeric, '0'), '.');
        }
        return null;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function specificationKey(): BelongsTo
    {
        return $this->belongsTo(SpecificationKey::class);
    }
}
