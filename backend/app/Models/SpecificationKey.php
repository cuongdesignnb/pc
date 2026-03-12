<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpecificationKey extends Model
{
    protected $fillable = [
        'component_type_id',
        'key',
        'label',
        'data_type',
        'unit',
        'is_filterable',
        'display_order',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'display_order' => 'integer',
    ];

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class);
    }

    public function productSpecifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }
}
