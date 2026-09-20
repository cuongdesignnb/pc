<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProductContentCampaignItem extends Model
{
    protected $fillable = [
        'campaign_id', 'product_id', 'source_snapshot', 'generated_payload', 'research_sources', 'status',
        'attempts', 'locked_at', 'generated_at', 'applied_at', 'error_message', 'warnings',
    ];

    protected $casts = [
        'source_snapshot' => 'array',
        'generated_payload' => 'array',
        'research_sources' => 'array',
        'locked_at' => 'datetime',
        'generated_at' => 'datetime',
        'applied_at' => 'datetime',
        'warnings' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AiProductContentCampaign::class, 'campaign_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
