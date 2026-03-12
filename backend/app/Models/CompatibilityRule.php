<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompatibilityRule extends Model
{
    protected $fillable = [
        'source_type_id',
        'target_type_id',
        'source_spec_key',
        'target_spec_key',
        'rule_type',
        'allowed_values',
        'power_headroom',
        'message',
        'is_active',
    ];

    protected $casts = [
        'allowed_values' => 'array',
        'power_headroom' => 'integer',
        'is_active' => 'boolean',
    ];

    public function sourceType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'source_type_id');
    }

    public function targetType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'target_type_id');
    }
}
