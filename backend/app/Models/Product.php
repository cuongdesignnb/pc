<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'component_type_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'is_active',
        'is_featured',
        'weight',
        'warranty_months',
        'specifications_text',
        'meta_title',
        'meta_description',
        'views_count',
        'sold_count',
    ];

    protected $appends = ['quantity'];

    protected $hidden = ['stock_quantity'];

    protected $casts = [
        'price' => 'decimal:0',
        'sale_price' => 'decimal:0',
        'cost_price' => 'decimal:0',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'weight' => 'integer',
        'warranty_months' => 'integer',
        'views_count' => 'integer',
        'sold_count' => 'integer',
    ];

    public function getQuantityAttribute(): int
    {
        return $this->stock_quantity;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function supportedValues(): HasMany
    {
        return $this->hasMany(ComponentSupportedValue::class);
    }

    public function powerRequirement(): HasOne
    {
        return $this->hasOne(PowerRequirement::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    public function getCurrentPriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }

    public function getAverageRatingAttribute(): ?float
    {
        return $this->approvedReviews()->avg('rating');
    }

    /**
     * Parse specifications_text into array of [label, value] pairs.
     * Each line format: "Label: Value"
     */
    public function getParsedSpecificationsAttribute(): array
    {
        if (empty($this->specifications_text)) {
            return [];
        }

        $lines = array_filter(explode("\n", $this->specifications_text), 'trim');
        $specs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $specs[] = [
                    'label' => trim($parts[0]),
                    'value' => trim($parts[1]),
                ];
            } else {
                $specs[] = [
                    'label' => $line,
                    'value' => '',
                ];
            }
        }

        return $specs;
    }
}
