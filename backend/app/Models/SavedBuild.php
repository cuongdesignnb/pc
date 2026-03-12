<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedBuild extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'products',
        'total_price',
        'total_tdp',
    ];

    protected $casts = [
        'products' => 'array',
        'total_price' => 'decimal:0',
        'total_tdp' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
