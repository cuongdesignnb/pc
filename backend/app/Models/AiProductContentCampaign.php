<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProductContentCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'filters', 'mode', 'technical_heading', 'use_web_research', 'append_contact_footer', 'include_product_images',
        'max_items', 'status', 'total_items', 'pending_items', 'draft_items', 'applied_items',
        'failed_items', 'review_items', 'scheduled_at', 'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
        'use_web_research' => 'boolean',
        'append_contact_footer' => 'boolean',
        'include_product_images' => 'boolean',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AiProductContentCampaignItem::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refreshProgress(): void
    {
        $counts = $this->items()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $total = (int) $this->items()->count();
        $pending = (int) (($counts['pending'] ?? 0) + ($counts['processing'] ?? 0));
        $failed = (int) ($counts['failed'] ?? 0);
        $review = (int) ($counts['needs_review'] ?? 0);
        $completed = $total > 0 && ($pending === 0);
        $hasStarted = $this->started_at !== null;

        $this->forceFill([
            'total_items' => $total,
            'pending_items' => $pending,
            'draft_items' => (int) ($counts['draft'] ?? 0),
            'applied_items' => (int) ($counts['applied'] ?? 0),
            'failed_items' => $failed,
            'review_items' => $review,
            'status' => $this->status === 'cancelled'
                ? 'cancelled'
                : ($completed
                    ? ($failed > 0 || $review > 0 ? 'partial_failed' : 'completed')
                    : ($hasStarted ? 'processing' : 'pending')),
            'completed_at' => $completed ? ($this->completed_at ?: now()) : null,
        ])->saveQuietly();
    }
}
