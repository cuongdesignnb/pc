<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'barcode',
        'inventory_source',
        'provider',
        'remote_product_id',
        'kiot_product_id',
        'kiot_sync_status',
        'kiot_availability_status',
        'kiot_is_under_repair',
        'kiot_sellable',
        'kiot_has_serial',
        'kiot_physical_quantity',
        'kiot_reserved_quantity',
        'kiot_available_quantity',
        'kiot_retail_price',
        'kiot_selected_price',
        'kiot_price_fallback_used',
        'kiot_sync_checksum',
        'show_on_pc_website',
        'kiot_remote_updated_at',
        'kiot_synced_at',
        'kiot_sync_error_code',
        'kiot_sync_error_message',
    ];

    protected $appends = ['quantity', 'is_purchasable', 'availability_label'];

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
        'kiot_product_id' => 'integer',
        'remote_product_id' => 'integer',
        'kiot_sellable' => 'boolean',
        'kiot_is_under_repair' => 'boolean',
        'kiot_has_serial' => 'boolean',
        'kiot_physical_quantity' => 'integer',
        'kiot_reserved_quantity' => 'integer',
        'kiot_available_quantity' => 'integer',
        'kiot_retail_price' => 'decimal:2',
        'kiot_selected_price' => 'decimal:0',
        'kiot_price_fallback_used' => 'boolean',
        'show_on_pc_website' => 'boolean',
        'kiot_remote_updated_at' => 'datetime',
        'kiot_synced_at' => 'datetime',
    ];

    public function getQuantityAttribute(): int
    {
        return $this->stock_quantity;
    }

    public function scopeSellableOnline(Builder $query): Builder
    {
        return $query->visibleOnStorefront()
            ->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereNull('provider')
                        ->where('stock_quantity', '>', 0);
                })
                    ->orWhere(function (Builder $query) {
                        $query->where('provider', 'kiot')
                            ->where('kiot_sellable', true)
                            ->where('kiot_sync_status', 'active')
                            ->where('kiot_availability_status', 'available')
                            ->where('kiot_available_quantity', '>', 0)
                            ->where('price', '>', 0);
                    });
            });
    }

    public function catalogPrices(): HasMany
    {
        return $this->hasMany(CatalogProductPrice::class);
    }

    public function scopeVisibleOnStorefront(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('show_on_pc_website', true)
            ->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereNull('provider')
                        ->where(function (Builder $query) {
                            $query->whereNull('inventory_source')
                                ->orWhere('inventory_source', '!=', 'kiot')
                                ->orWhere(function (Builder $query) {
                                    $query->where('inventory_source', 'kiot')
                                        ->where('stock_quantity', '>', 0);
                                });
                        });
                })
                    ->orWhere(function (Builder $query) {
                        $query->where('provider', 'kiot')
                            ->where('kiot_sync_status', 'active')
                            ->whereHas('category', fn (Builder $query) => $query->visibleOnStorefront());
                    });
            });
    }

    public function isSellableOnline(): bool
    {
        if (! $this->isVisibleOnStorefront()) {
            return false;
        }

        if ($this->provider !== 'kiot') {
            return $this->stock_quantity > 0
                && ($this->inventory_source === 'local'
                    || ($this->inventory_source === 'kiot' && $this->kiot_sellable));
        }

        return $this->kiot_sellable
            && $this->kiot_sync_status === 'active'
            && $this->kiot_availability_status === 'available'
            && (int) $this->kiot_available_quantity > 0
            && (int) $this->price > 0;
    }

    public function isVisibleOnStorefront(): bool
    {
        if ($this->provider !== 'kiot') {
            return $this->is_active
                && $this->show_on_pc_website
                && ($this->inventory_source !== 'kiot' || $this->stock_quantity > 0);
        }

        $category = $this->relationLoaded('category') ? $this->category : $this->category()->first();

        return $this->is_active
            && $this->show_on_pc_website
            && $category?->isVisibleOnStorefront()
            && $this->kiot_sync_status === 'active';
    }

    public function getIsPurchasableAttribute(): bool
    {
        return $this->isSellableOnline();
    }

    public function getAvailabilityLabelAttribute(): string
    {
        if ($this->inventory_source !== 'kiot') {
            return $this->stock_quantity > 0 ? 'Còn hàng' : 'Hết hàng';
        }
        if ((int) $this->price <= 0) {
            return 'Liên hệ';
        }

        return match ($this->kiot_availability_status) {
            'repairing' => 'Đang sửa chữa',
            'reserved' => 'Tạm thời không sẵn hàng',
            'sold' => 'Hết hàng',
            'inactive', 'deleted' => 'Ngừng kinh doanh',
            default => $this->isSellableOnline() ? 'Còn hàng' : 'Tạm thời không sẵn hàng',
        };
    }

    public function purchasableUnitPrice(): int
    {
        return (int) ($this->inventory_source === 'kiot'
            ? $this->price
            : ($this->sale_price ?? $this->price));
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
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
            if (empty($line)) {
                continue;
            }

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
