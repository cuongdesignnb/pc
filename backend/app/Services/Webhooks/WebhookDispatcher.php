<?php

namespace App\Services\Webhooks;

use App\Jobs\Webhooks\DeliverWebhook;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Orders\OrderApiPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Queues outbound webhooks (hook.laptopplus.vn) for orders and contact
 * messages. Only subscribed events are queued, always after the surrounding
 * transaction commits, and a webhook problem never breaks the order or form.
 */
class WebhookDispatcher
{
    public function __construct(private readonly OrderApiPresenter $presenter) {}

    public function orderCreated(Order $order): void
    {
        $this->queueOrder('order.created', $order, []);
    }

    /** From the Order "updated" hook: status and payment changes, whatever made them. */
    public function orderUpdated(Order $order): void
    {
        if ($order->wasChanged('order_status')) {
            $this->queueOrder('order.status_changed', $order, ['status' => $order->getOriginal('order_status')]);
        }
        if ($order->wasChanged(['payment_status', 'amount_paid'])) {
            $this->queueOrder('order.payment_changed', $order, [
                'payment_status' => $order->getOriginal('payment_status'),
                'amount_paid' => (int) $order->getOriginal('amount_paid'),
            ]);
        }
    }

    public function contactCreated(ContactMessage $message): void
    {
        $this->safely(function () use ($message) {
            if (! WebhookEndpoint::current()->subscribes('contact.created')) {
                return;
            }
            DB::afterCommit(fn () => $this->safely(fn () => $this->enqueue('contact.created', $message->only([
                'id', 'name', 'phone', 'email', 'subject', 'message', 'page_url', 'status',
            ]) + ['created_at' => $message->created_at?->toIso8601String()])));
        });
    }

    /** "Gửi thử" on the admin page: a ping sent right away, never retried. */
    public function ping(): WebhookDelivery
    {
        $delivery = $this->create('ping', ['message' => 'Laptop Plus webhook test']);
        app(WebhookSender::class)->deliver($delivery->id);

        return $delivery->fresh();
    }

    /** @param  array<string, mixed>  $data */
    public function enqueue(string $event, array $data): WebhookDelivery
    {
        $delivery = $this->create($event, $data);
        DeliverWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    /** @param  array<string, mixed>  $previous */
    private function queueOrder(string $event, Order $order, array $previous): void
    {
        $this->safely(function () use ($event, $order, $previous) {
            if (! WebhookEndpoint::current()->subscribes($event)) {
                return;
            }
            $orderId = $order->id;
            // Built after commit, so a new order carries its items.
            DB::afterCommit(fn () => $this->safely(function () use ($event, $orderId, $previous) {
                $fresh = Order::with(['items.product', 'affiliate', 'affiliateCommission'])->find($orderId);
                if ($fresh) {
                    $this->enqueue($event, $this->presenter->present($fresh) + ($previous ? ['previous' => $previous] : []));
                }
            }));
        });
    }

    /** @param  array<string, mixed>  $data */
    private function create(string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        return WebhookDelivery::create([
            'uuid' => $uuid, 'event' => $event, 'status' => 'pending', 'next_attempt_at' => now(),
            'payload' => ['id' => $uuid, 'event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data],
        ]);
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
