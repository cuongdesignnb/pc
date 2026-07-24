<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationConnection extends Model
{
    public const PROVIDER_KIOT = 'kiot';

    protected $fillable = [
        'provider',
        'configuration_source',
        'base_url',
        'client_id',
        'secret_encrypted',
        'secret_fingerprint',
        'api_version',
        'connection_status',
        'is_enabled',
        'product_sync_enabled',
        'order_sync_enabled',
        'capabilities',
        'last_tested_at',
        'last_connected_at',
        'last_error_at',
        'last_error_code',
        'last_error_message',
        'created_by',
        'updated_by',
        'disconnected_at',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'is_enabled' => 'boolean',
            'product_sync_enabled' => 'boolean',
            'order_sync_enabled' => 'boolean',
            'capabilities' => 'array',
            'last_tested_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'last_error_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationConnectionEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
