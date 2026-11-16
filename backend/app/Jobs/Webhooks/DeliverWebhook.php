<?php

namespace App\Jobs\Webhooks;

use App\Services\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Sends one outbound webhook; failures are retried by webhooks:retry, not by the queue. */
class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookSender $sender): void
    {
        try {
            $sender->deliver($this->deliveryId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
