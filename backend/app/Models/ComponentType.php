<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComponentType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'display_order',
        'is_required',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_required' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function specificationKeys(): HasMany
    {
        return $this->hasMany(SpecificationKey::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
