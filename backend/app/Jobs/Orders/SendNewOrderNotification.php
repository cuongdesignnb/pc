<?php

namespace App\Jobs\Orders;

use App\Models\Order;
use App\Services\Mail\StorefrontSmtpMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNewOrderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $orderId) {}

    public function handle(StorefrontSmtpMailer $mailer): void
    {
        $order = Order::with(['items.product'])->find($this->orderId);
        if (! $order) {
            return;
        }

        $mailer->sendOrderNotification($order);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Unable to send new order notification email.', [
            'order_id' => $this->orderId,
            'exception' => $exception::class,
        ]);
    }
}
