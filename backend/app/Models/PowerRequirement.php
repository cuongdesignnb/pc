<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerRequirement extends Model
{
    protected $primaryKey = 'product_id';
    public $incrementing = false;

    protected $fillable = [
        'product_id',
        'typical_tdp',
        'peak_tdp',
        'requires_pcie_power',
        'pcie_connectors_needed',
    ];

    protected $casts = [
        'typical_tdp' => 'integer',
        'peak_tdp' => 'integer',
        'requires_pcie_power' => 'boolean',
        'pcie_connectors_needed' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
