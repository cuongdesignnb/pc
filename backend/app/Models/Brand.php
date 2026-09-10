<?php

namespace App\Models;

use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'website',
        'is_active',
        'slug_source',
        'slug_policy_version',
        'slug_locked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'slug_locked_at' => 'datetime',
    ];

    public function getLogoAttribute(?string $value): ?string
    {
        return PublicAssetUrl::normalize($value);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
