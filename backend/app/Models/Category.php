<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'component_type_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'sort_order',
        'is_active',
        'provider',
        'remote_category_id',
        'show_on_pc_website',
        'provider_sync_status',
        'provider_sync_checksum',
        'provider_updated_at',
        'provider_synced_at',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'remote_category_id' => 'integer',
        'show_on_pc_website' => 'boolean',
        'provider_updated_at' => 'datetime',
        'provider_synced_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function scopeVisibleOnStorefront(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('show_on_pc_website', true)
            ->where(function (Builder $query) {
                $query->whereNull('provider')
                    ->orWhere(function (Builder $query) {
                        $query->where('provider', 'kiot')
                            ->where('provider_sync_status', 'active');
                    });
            });
    }

    public function isVisibleOnStorefront(): bool
    {
        return $this->is_active
            && $this->show_on_pc_website
            && ($this->provider === null
                || ($this->provider === 'kiot' && $this->provider_sync_status === 'active'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class);
    }

    public function filters(): BelongsToMany
    {
        return $this->belongsToMany(Filter::class, 'category_filter')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('category_filter.sort_order');
    }
}
