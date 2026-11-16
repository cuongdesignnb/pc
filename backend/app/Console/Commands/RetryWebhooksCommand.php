<?php

namespace App\Console\Commands;

use App\Jobs\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RetryWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry';

    protected $description = 'Gửi lại các webhook đang chờ hoặc lỗi đã tới giờ thử lại';

    public function handle(): int
    {
        $count = 0;
        WebhookDelivery::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('event', '!=', 'ping')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->select('id')
            ->chunkById(100, function ($deliveries) use (&$count) {
                foreach ($deliveries as $delivery) {
                    DeliverWebhook::dispatch($delivery->id);
                    $count++;
                }
            });
        $this->info("Đã đưa {$count} webhook vào hàng đợi.");

        return self::SUCCESS;
    }
}
