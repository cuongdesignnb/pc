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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
