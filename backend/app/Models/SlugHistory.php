<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SlugHistory extends Model
{
    protected $fillable = [
        'site_key',
        'entity_type',
        'entity_id',
        'route_namespace',
        'legacy_slug',
        'source_path',
        'target_path',
        'target_entity_id',
        'redirect_status',
        'migration_batch_id',
        'reason',
        'actor_id',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'target_entity_id' => 'integer',
        'redirect_status' => 'integer',
        'actor_id' => 'integer',
    ];

    public function scopeForAlias(Builder $query, string $routeNamespace, string $slug): Builder
    {
        return $query->where('site_key', 'storefront')
            ->where('route_namespace', $routeNamespace)
            ->where('legacy_slug', $slug);
    }

    public function scopeForSourcePath(Builder $query, string $sourcePath): Builder
    {
        return $query->where('site_key', 'storefront')
            ->where('source_path', $sourcePath);
    }
}
