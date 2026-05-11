<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilterValue extends Model
{
    protected $fillable = [
        'filter_id',
        'label',
        'slug',
        'match_value',
        'price_min',
        'price_max',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price_min' => 'decimal:0',
        'price_max' => 'decimal:0',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function filter(): BelongsTo
    {
        return $this->belongsTo(Filter::class);
    }
}
