<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'title',
        'url',
        'type',
        'category_id',
        'icon',
        'badge_text',
        'badge_color',
        'css_class',
        'target',
        'sort_order',
        'is_active',
        'is_mega',
        'mega_columns',
        'description',
        'image',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_mega' => 'boolean',
        'sort_order' => 'integer',
        'mega_columns' => 'integer',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the resolved URL (from custom url or from category slug)
     */
    public function getResolvedUrlAttribute(): string
    {
        if ($this->type === 'category' && $this->category) {
            return '/categories/' . $this->category->slug;
        }
        return $this->url ?? '#';
    }
}
