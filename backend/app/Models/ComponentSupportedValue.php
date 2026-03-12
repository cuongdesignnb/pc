<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentSupportedValue extends Model
{
    protected $fillable = [
        'product_id',
        'specification_key_id',
        'supported_value',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function specificationKey(): BelongsTo
    {
        return $this->belongsTo(SpecificationKey::class);
    }
}
