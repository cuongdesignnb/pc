<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Services\Payments\SepayPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class KiotOutboxService
{
    private const BUSINESS_REJECTIONS = [
        'UNKNOWN_SKU', 'DUPLICATE_SKU_IN_KIOT', 'PRODUCT_INACTIVE', 'PRODUCT_NOT_SELLABLE',
        'INSUFFICIENT_AVAILABLE_STOCK', 'ORDER_TOTAL_MISMATCH', 'ORDER_ALREADY_INVOICED',
        'ORDER_NOT_CANCELLABLE', 'ORDER_ALREADY_CANCELLED',
    ];

    private const FATAL_CONFLICTS = ['EXTERNAL_ORDER_CONFLICT', 'IDEMPOTENCY_KEY_CONFLICT'];

    private const AUTH_FAILURES = ['INVALID_INTEGRATION_CLIENT', 'INVALID_SIGNATURE', 'EXPIRED_TIMESTAMP', 'REPLAYED_NONCE'];

    private const CONFIG_FAILURES = ['INTEGRATION_DISABLED', 'INTEGRATION_NOT_CONFIGURED'];

    public function __construct(
        private readonly KiotClient $client,
        private readonly KiotConfigurationResolver $resolver,
        private readonly SepayPaymentService $payments,
    ) {}

    public function process(int $eventId): void
    {
        $runtime = $this->resolver->resolve();
        if (! $runtime->orderSyncEnabled) {
            return;
        }

        $event = $this->claim($eventId, $runtime->outboxMaxAttempts);
        if (! $event) {
            return;
        }

        $started = microtime(true);
        try {
            $response = $event->event_type === 'order.cancel'
                ? $this->client->cancelOrder($event->aggregate_id, $event->raw_body, $event->idempotency_key)
                : $this->client->createOrder($event->raw_body, $event->idempotency_key);

            if ($response->successful()) {
                $this->markSent($event, $response);
            } else {
                $this->markFailure($event, $response->errorCode() ?? 'INTERNAL_INTEGRATION_ERROR', $response->errorMessage(), $response->status, $response->body, maxAttempts: $runtime->outboxMaxAttempts);
            }
        } catch (KiotIntegrationException $exception) {
            $this->markFailure($event, $exception->errorCode, $exception->getMessage(), $exception->httpStatus, $exception->responseBody, $exception->classification, $runtime->outboxMaxAttempts);
        } catch (Throwable $exception) {
            $this->markFailure($event, 'INTERNAL_INTEGRATION_ERROR', 'Lỗi tạm thời khi gửi KIOT.', null, null, 'retryable', $runtime->outboxMaxAttempts);
            report($exception);
        } finally {
            Log::info('KIOT outbox processed', [
                'order_id' => $event->aggregate_id, 'event_id' => $event->event_id,
                'attempt' => $event->attempt_count, 'payload_hash' => $event->payload_hash,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        }
    }

    private function claim(int $eventId, int $maxAttempts): ?IntegrationOutboxEvent
    {
        return DB::transaction(function () use ($eventId, $maxAttempts) {
            $event = IntegrationOutboxEvent::lockForUpdate()->find($eventId);
            if (! $event || in_array($event->status, ['sent', 'rejected', 'dead_letter', 'cancelled'], true)) {
                return null;
            }
            if ($event->status === 'processing' && $event->locked_at?->isAfter(now()->subMinutes(5))) {
                return null;
            }
            if (in_array($event->status, ['pending', 'retrying'], true)
                && $event->next_attempt_at?->isFuture()) {
                return null;
            }

            $attempt = $event->attempt_count + 1;
            if ($attempt > $maxAttempts) {
                $event->update(['status' => 'dead_letter', 'locked_at' => null, 'last_error_code' => 'MAX_ATTEMPTS_EXCEEDED']);
                Order::whereKey($event->aggregate_id)->update(['kiot_sync_status' => 'failed', 'kiot_sync_error_code' => 'MAX_ATTEMPTS_EXCEEDED']);

                return null;
            }

            $event->update(['status' => 'processing', 'attempt_count' => $attempt, 'locked_at' => now(), 'last_attempt_at' => now()]);
            Order::whereKey($event->aggregate_id)->update([
                'kiot_sync_status' => $event->event_type === 'order.cancel' ? 'cancel_pending' : 'sending',
                'kiot_sync_attempt_count' => $attempt, 'kiot_last_attempt_at' => now(),
            ]);

            return $event->fresh();
        }, 3);
    }

    private function markSent(IntegrationOutboxEvent $event, KiotResponse $response): void
    {
        $result = DB::transaction(function () use ($event, $response) {
            $locked = IntegrationOutboxEvent::lockForUpdate()->findOrFail($event->id);
            $order = Order::with('items')->lockForUpdate()->findOrFail($event->aggregate_id);
            $locked->update([
                'status' => 'sent', 'locked_at' => null, 'next_attempt_at' => null,
                'response_status' => $response->status, 'response_body' => $response->body,
                'last_error_code' => null, 'last_error_message' => null, 'sent_at' => now(),
            ]);

            if ($event->event_type === 'order.cancel') {
                $order->update([
                    'order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled',
                    'kiot_sync_error_code' => null, 'kiot_sync_error_message' => null,
                ]);
            } else {
                $data = $response->data();
                $order->update([
                    'kiot_sync_status' => 'synced', 'kiot_order_id' => $data['kiot_order_id'] ?? null,
                    'kiot_order_code' => $data['kiot_order_code'] ?? null, 'kiot_synced_at' => now(),
                    'kiot_response' => $response->body, 'kiot_sync_error_code' => null,
                    'kiot_sync_error_message' => null,
                ]);
            }

            return ['order' => $order->fresh(), 'skus' => $order->items->pluck('sku')->all()];
        }, 3);

        if ($event->event_type === 'order.create') {
            $this->payments->reconcileOrder($result['order']);
        }

        if ($this->resolver->resolve()->productSyncEnabled) {
            SyncKiotProductsBySku::dispatch($result['skus'])->afterCommit();
        }
    }

    private function markFailure(
        IntegrationOutboxEvent $event,
        string $code,
        string $message,
        ?int $status,
        ?array $body,
        ?string $classification = null,
        ?int $maxAttempts = null,
    ): void {
        $classification ??= $this->classify($code, $status);
        $maxAttempts ??= $this->resolver->resolve()->outboxMaxAttempts;
        DB::transaction(function () use ($event, $code, $message, $status, $body, $classification, $maxAttempts) {
            $locked = IntegrationOutboxEvent::lockForUpdate()->findOrFail($event->id);
            $order = Order::lockForUpdate()->findOrFail($event->aggregate_id);
            $terminal = in_array($classification, ['business_rejection', 'authentication_failure', 'configuration_failure', 'fatal_conflict'], true);

            if ($classification === 'retryable' && $locked->attempt_count < $maxAttempts) {
                $locked->update([
                    'status' => 'retrying', 'locked_at' => null,
                    'next_attempt_at' => now()->addSeconds($this->backoffSeconds($locked->attempt_count))->ceilSecond(),
                    'last_error_code' => $code, 'last_error_message' => $message,
                    'response_status' => $status, 'response_body' => $body,
                ]);
                $order->update(['kiot_sync_status' => 'retrying', 'kiot_sync_error_code' => $code, 'kiot_sync_error_message' => $message]);

                return;
            }

            $outboxStatus = $classification === 'business_rejection' ? 'rejected' : 'dead_letter';
            $locked->update([
                'status' => $outboxStatus, 'locked_at' => null, 'next_attempt_at' => null,
                'last_error_code' => $code, 'last_error_message' => $message,
                'response_status' => $status, 'response_body' => $body,
            ]);

            if ($event->event_type === 'order.cancel') {
                $order->update([
                    'kiot_sync_status' => $terminal ? 'synced' : 'failed',
                    'kiot_sync_error_code' => $code, 'kiot_sync_error_message' => $message,
                ]);
            } else {
                $order->update([
                    'kiot_sync_status' => $classification === 'business_rejection' ? 'rejected' : 'failed',
                    'order_status' => $classification === 'business_rejection' ? 'cancelled' : $order->order_status,
                    'kiot_sync_error_code' => $code, 'kiot_sync_error_message' => $message,
                ]);
            }
        }, 3);
    }

    private function classify(string $code, ?int $status): string
    {
        if (in_array($code, self::BUSINESS_REJECTIONS, true)) {
            return 'business_rejection';
        }
        if (in_array($code, self::FATAL_CONFLICTS, true)) {
            return 'fatal_conflict';
        }
        if (in_array($code, self::AUTH_FAILURES, true)) {
            return 'authentication_failure';
        }
        if (in_array($code, self::CONFIG_FAILURES, true)) {
            return 'configuration_failure';
        }
        if ($status === 429 || ($status !== null && $status >= 500) || $status === null) {
            return 'retryable';
        }

        return 'fatal_conflict';
    }

    private function backoffSeconds(int $attempt): int
    {
        $factors = [1, 2, 4, 10, 20, 60, 120];

        return $this->resolver->resolve()->outboxRetryBaseSeconds * ($factors[min($attempt - 1, count($factors) - 1)] ?? 120);
    }
}
