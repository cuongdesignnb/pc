<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'full_name',
        'phone',
        'province',
        'province_code',
        'district',
        'ward',
        'ward_code',
        'street',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->street, $this->ward, $this->district, $this->province])
            ->filter(fn (?string $part): bool => filled($part))
            ->implode(', ');
    }
}
