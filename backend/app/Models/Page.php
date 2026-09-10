<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'body',
        'meta_title',
        'meta_description',
        'is_active',
        'slug_source',
        'slug_policy_version',
        'slug_locked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'slug_locked_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
