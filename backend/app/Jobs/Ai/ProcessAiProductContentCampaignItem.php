<?php

namespace App\Jobs\Ai;

use App\Models\AiProductContentCampaignItem;
use App\Services\Ai\ProductContentCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessAiProductContentCampaignItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $itemId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ProductContentCampaignService $campaigns): void
    {
        $item = AiProductContentCampaignItem::with('campaign')->find($this->itemId);
        if (! $item || in_array($item->status, ['applied', 'cancelled', 'skipped', 'draft', 'needs_review'], true)) {
            return;
        }
        if ($item->status === 'processing' && $item->locked_at?->gt(now()->subMinutes(15))) {
            return;
        }

        $item->update(['status' => 'processing', 'locked_at' => now(), 'attempts' => $item->attempts + 1]);
        try {
            $payload = $campaigns->generateItem($item);
            if ($item->campaign->mode === 'publish' && $payload['can_auto_apply']) {
                $campaigns->applyItem($item->fresh());
            }
            $item->campaign->refreshProgress();
        } catch (\Throwable $exception) {
            $attempts = (int) $item->attempts;
            $item->update([
                'status' => $attempts >= 3 ? 'failed' : 'pending',
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
                'locked_at' => null,
            ]);
            $item->campaign->refreshProgress();
            if ($attempts < 3) {
                throw $exception;
            }
        }
    }
}
