<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuildPreset extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'usage_type',
        'products',
        'starting_price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'products' => 'array',
        'starting_price' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
