<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KiotOrderCancellationService
{
    public function __construct(private readonly KiotClient $client, private readonly KiotOutboxService $outbox) {}

    public function cancel(Order $order, string $reason): Order
    {
        $order->loadMissing('items');
        if ($order->order_status === 'cancelled') {
            return $order;
        }

        if ($order->kiot_sync_status === 'not_required') {
            $order->update(['order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled']);

            return $order->fresh();
        }
        $this->client->assertOrderSyncEnabled();

        $createEvent = IntegrationOutboxEvent::where('integration', 'kiot')
            ->where('aggregate_id', $order->id)->where('event_type', 'order.create')->first();
        if ($createEvent && $createEvent->status === 'pending' && $createEvent->attempt_count === 0) {
            DB::transaction(function () use ($createEvent, $order) {
                IntegrationOutboxEvent::whereKey($createEvent->id)->lockForUpdate()->update(['status' => 'cancelled', 'locked_at' => null]);
                Order::whereKey($order->id)->lockForUpdate()->update(['order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled']);
            });

            return $order->fresh();
        }

        if (in_array($order->kiot_sync_status, ['pending', 'sending', 'retrying', 'failed'], true)) {
            $remote = $this->client->order($order->id);
            if (! $remote->successful()) {
                if ($remote->errorCode() === 'EXTERNAL_ORDER_NOT_FOUND') {
                    DB::transaction(function () use ($order, $createEvent) {
                        if ($createEvent) {
                            $createEvent->update(['status' => 'cancelled', 'locked_at' => null]);
                        }
                        $order->update(['order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled']);
                    });

                    return $order->fresh();
                }
                throw new KiotIntegrationException($remote->errorCode() ?? 'INTERNAL_INTEGRATION_ERROR', $remote->errorMessage(), $remote->status >= 500 || $remote->status === 429 ? 'retryable' : 'business_rejection', $remote->status, $remote->body);
            }
        }

        $existing = IntegrationOutboxEvent::where('integration', 'kiot')
            ->where('aggregate_id', $order->id)->where('event_type', 'order.cancel')
            ->whereNotIn('status', ['cancelled'])->latest()->first();
        if ($existing) {
            $this->outbox->process($existing->id);

            return $this->result($existing, $order);
        }

        $event = DB::transaction(function () use ($order, $reason) {
            $eventId = (string) Str::uuid();
            $idempotencyKey = (string) Str::uuid();
            $payload = ['event_id' => $eventId, 'reason' => $reason];
            $rawBody = $this->client->encode($payload);
            $event = IntegrationOutboxEvent::create([
                'integration' => 'kiot', 'event_type' => 'order.cancel',
                'aggregate_type' => Order::class, 'aggregate_id' => $order->id,
                'event_id' => $eventId, 'idempotency_key' => $idempotencyKey,
                'payload' => $payload, 'raw_body' => $rawBody, 'payload_hash' => hash('sha256', $rawBody),
                'status' => 'pending', 'next_attempt_at' => now(),
            ]);
            $order->update(['kiot_sync_status' => 'cancel_pending']);

            return $event;
        });

        $this->outbox->process($event->id);

        return $this->result($event, $order);
    }

    private function result(IntegrationOutboxEvent $event, Order $order): Order
    {
        $event->refresh();
        if (in_array($event->status, ['rejected', 'dead_letter'], true)) {
            throw new KiotIntegrationException(
                $event->last_error_code ?? 'INTERNAL_INTEGRATION_ERROR',
                $event->last_error_message ?? 'Không thể hủy đơn hàng trên KIOT.',
                $event->status === 'rejected' ? 'business_rejection' : 'fatal_conflict',
                $event->response_status,
                $event->response_body,
            );
        }

        return $order->fresh();
    }
}
