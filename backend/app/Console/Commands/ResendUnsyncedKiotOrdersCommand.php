<?php

namespace App\Console\Commands;

use App\Exceptions\KiotIntegrationException;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\Order;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Orders\OrderHistoryRecorder;
use Illuminate\Console\Command;

/**
 * Orders KIOT refused ("rejected") or that ran out of send attempts ("failed")
 * are sent again a few times, further apart each time, in case the cause was
 * fixed meanwhile (product created or linked in KIOT, stock received, KIOT back).
 * Every try is written to the order's history.
 */
class ResendUnsyncedKiotOrdersCommand extends Command
{
    /** Minutes to wait before automatic try 1, 2 and 3. */
    public const DELAYS = [15, 60, 240];

    protected $signature = 'kiot:resend-unsynced-orders';

    protected $description = 'Tự gửi lại (tối đa 3 lần) các đơn web chưa vào KIOT';

    public function handle(KiotConfigurationResolver $resolver, KiotOrderService $orders): int
    {
        if (! $resolver->resolve()->orderSyncEnabled) {
            return self::SUCCESS;
        }

        $sent = 0;
        Order::query()
            ->whereIn('kiot_sync_status', ['rejected', 'failed'])
            ->where('order_status', '!=', 'cancelled')
            ->where('kiot_auto_resend_count', '<', count(self::DELAYS))
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->each(function (Order $order) use ($orders, &$sent) {
                $try = (int) $order->kiot_auto_resend_count + 1;
                $since = $order->kiot_auto_resend_at ?? $order->kiot_last_attempt_at ?? $order->updated_at;
                if ($since && $since->isAfter(now()->subMinutes(self::DELAYS[$try - 1]))) {
                    return;
                }

                $order->forceFill(['kiot_auto_resend_count' => $try, 'kiot_auto_resend_at' => now()])->saveQuietly();
                $last = $try === count(self::DELAYS) ? ' (lần cuối, sau đó cần gửi lại bằng tay)' : '';
                OrderHistoryRecorder::kiot($order, "Tự gửi lại sang KIOT lần {$try}/".count(self::DELAYS).$last.'.');
                try {
                    ProcessKiotOutboxEvent::dispatchSync($orders->resend($order));
                    $sent++;
                } catch (KiotIntegrationException $exception) {
                    OrderHistoryRecorder::kiot($order, "Không gửi lại được: {$exception->getMessage()}");
                }
            });

        $this->info("Đã gửi lại {$sent} đơn sang KIOT.");

        return self::SUCCESS;
    }
}
