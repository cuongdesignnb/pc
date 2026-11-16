<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Services\Orders\OrderHistoryRecorder;
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
        // Invoice (deliver) and receipt (payments) for an order already in KIOT.
        'SERIAL_NOT_ALLOCATED', 'COST_PENDING', 'ORDER_NOT_PROCESSABLE', 'ORDER_NOT_FOUND',
        'OVERPAYMENT', 'CUSTOMER_REQUIRED',
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

        if ($this->waitsForEarlierFulfillment($eventId)) {
            return;
        }

        $event = $this->claim($eventId, $runtime->outboxMaxAttempts);
        if (! $event) {
            return;
        }

        $started = microtime(true);
        try {
            $response = match ($event->event_type) {
                'order.cancel' => $this->client->cancelOrder($event->aggregate_id, $event->raw_body, $event->idempotency_key),
                KiotFulfillmentService::EVENT_DELIVER => $this->client->deliverOrder($event->aggregate_id, $event->raw_body, $event->idempotency_key),
                KiotFulfillmentService::EVENT_PAYMENT => $this->client->recordOrderPayment($event->aggregate_id, $event->raw_body, $event->idempotency_key),
                default => $this->client->createOrder($event->raw_body, $event->idempotency_key),
            };

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
                if (self::isFulfillment($event)) {
                    OrderHistoryRecorder::kiot((int) $event->aggregate_id, self::fulfillmentLabel($event)." chưa gửi được sang KIOT sau {$maxAttempts} lần thử, dừng tự gửi.");

                    return null;
                }
                Order::whereKey($event->aggregate_id)->update(['kiot_sync_status' => 'failed', 'kiot_sync_error_code' => 'MAX_ATTEMPTS_EXCEEDED']);
                OrderHistoryRecorder::kiot((int) $event->aggregate_id, "Chưa vào KIOT: đã thử gửi {$maxAttempts} lần không thành công, dừng tự gửi.");

                return null;
            }

            $event->update(['status' => 'processing', 'attempt_count' => $attempt, 'locked_at' => now(), 'last_attempt_at' => now()]);
            // Invoices and receipts belong to an order already in KIOT: its sync status stays "synced".
            if (self::isFulfillment($event)) {
                return $event->fresh();
            }
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

            if (self::isFulfillment($event)) {
                OrderHistoryRecorder::kiot($order, $this->fulfillmentSentMessage($event, $response->data()), 'kiot');
            } elseif ($event->event_type === 'order.cancel') {
                $order->update([
                    'order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled',
                    'kiot_sync_error_code' => null, 'kiot_sync_error_message' => null,
                ]);
                OrderHistoryRecorder::kiot($order, 'KIOT đã huỷ phiếu đặt hàng.', 'kiot');
            } else {
                $data = $response->data();
                $order->update([
                    'kiot_sync_status' => 'synced', 'kiot_order_id' => $data['kiot_order_id'] ?? null,
                    'kiot_order_code' => $data['kiot_order_code'] ?? null, 'kiot_synced_at' => now(),
                    'kiot_response' => $response->body, 'kiot_sync_error_code' => null,
                    'kiot_sync_error_message' => null,
                ]);
                OrderHistoryRecorder::kiot($order, 'Đã vào KIOT'.($order->kiot_order_code ? ' với mã '.$order->kiot_order_code : '').'.', 'kiot');
            }

            return ['order' => $order->fresh(), 'skus' => $order->items->pluck('sku')->all()];
        }, 3);

        if ($event->event_type === 'order.create') {
            $this->payments->reconcileOrder($result['order']);
        }

        if ($event->event_type !== KiotFulfillmentService::EVENT_PAYMENT && $this->resolver->resolve()->productSyncEnabled) {
            SyncKiotProductsBySku::dispatch($result['skus'])->afterCommit();
        }
        if ($event->event_type === KiotFulfillmentService::EVENT_PAYMENT) {
            $this->processNextFulfillment($event);
        }
    }

    /** Receipts go before the invoice: a later event for the same order waits for earlier ones. */
    private function waitsForEarlierFulfillment(int $eventId): bool
    {
        $event = IntegrationOutboxEvent::find($eventId, ['id', 'event_type', 'aggregate_id']);
        if (! $event || ! self::isFulfillment($event)) {
            return false;
        }

        return IntegrationOutboxEvent::where('integration', 'kiot')
            ->where('aggregate_id', $event->aggregate_id)
            ->whereIn('event_type', KiotFulfillmentService::EVENT_TYPES)
            ->where('id', '<', $event->id)
            ->whereIn('status', ['pending', 'retrying', 'processing'])
            ->exists();
    }

    private function processNextFulfillment(IntegrationOutboxEvent $event): void
    {
        $next = IntegrationOutboxEvent::where('integration', 'kiot')
            ->where('aggregate_id', $event->aggregate_id)
            ->whereIn('event_type', KiotFulfillmentService::EVENT_TYPES)
            ->where('id', '>', $event->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->value('id');
        if ($next) {
            ProcessKiotOutboxEvent::dispatch($next)->afterCommit();
        }
    }

    private static function isFulfillment(IntegrationOutboxEvent $event): bool
    {
        return in_array($event->event_type, KiotFulfillmentService::EVENT_TYPES, true);
    }

    private static function fulfillmentLabel(IntegrationOutboxEvent $event): string
    {
        return $event->event_type === KiotFulfillmentService::EVENT_DELIVER
            ? 'Hoá đơn KIOT (đơn đã giao)'
            : 'Phiếu thu KIOT '.self::money((int) data_get($event->payload, 'amount'));
    }

    private static function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    /** @param  array<string, mixed>  $data */
    private function fulfillmentSentMessage(IntegrationOutboxEvent $event, array $data): string
    {
        if ($event->event_type === KiotFulfillmentService::EVENT_DELIVER) {
            $invoice = $data['kiot_invoice_code'] ?? null;

            return $invoice ? "KIOT đã tạo hoá đơn {$invoice}." : 'KIOT đã ghi nhận đơn đã giao.';
        }
        $code = $data['cash_flow_code'] ?? null;
        $message = 'KIOT đã ghi phiếu thu'.($code ? " {$code}" : '').' '.self::money((int) data_get($event->payload, 'amount'));
        if (isset($data['amount_paid_total'])) {
            $message .= ' (tổng đã thu '.self::money((int) $data['amount_paid_total']).')';
        }

        return $message.'.';
    }

    private function fulfillmentRejectedMessage(IntegrationOutboxEvent $event, string $code, string $message): string
    {
        $what = $event->event_type === KiotFulfillmentService::EVENT_DELIVER
            ? 'KIOT chưa tạo hoá đơn'
            : 'KIOT chưa ghi phiếu thu '.self::money((int) data_get($event->payload, 'amount'));
        $reason = match ($code) {
            'SERIAL_NOT_ALLOCATED' => 'cần chọn Serial/IMEI trong KIOT',
            'COST_PENDING' => 'sản phẩm chưa có giá vốn trong KIOT',
            'ORDER_NOT_PROCESSABLE' => 'phiếu đặt hàng KIOT không còn xử lý được',
            'ORDER_NOT_FOUND' => 'KIOT không tìm thấy phiếu đặt hàng của đơn',
            'OVERPAYMENT' => 'số tiền vượt quá số còn phải thu',
            'CUSTOMER_REQUIRED' => 'phiếu đặt hàng chưa có khách hàng',
            default => null,
        };

        return mb_substr($what.': '.($reason ? "{$reason} ({$code}: {$message})" : "{$code}: {$message}"), 0, 900);
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

            if (self::isFulfillment($event)) {
                $this->markFulfillmentFailure($locked, $order, $code, $message, $status, $body, $classification, $maxAttempts);

                return;
            }

            if ($classification === 'retryable' && $locked->attempt_count < $maxAttempts) {
                $locked->update([
                    'status' => 'retrying', 'locked_at' => null,
                    'next_attempt_at' => now()->addSeconds($this->backoffSeconds($locked->attempt_count))->ceilSecond(),
                    'last_error_code' => $code, 'last_error_message' => $message,
                    'response_status' => $status, 'response_body' => $body,
                ]);
                $order->update(['kiot_sync_status' => 'retrying', 'kiot_sync_error_code' => $code, 'kiot_sync_error_message' => $message]);
                OrderHistoryRecorder::kiot($order, "Gửi sang KIOT lần {$locked->attempt_count} lỗi ({$code}: {$message}), hệ thống sẽ tự thử lại.");

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
                OrderHistoryRecorder::kiot($order, "KIOT không huỷ được phiếu ({$code}: {$message}).");
            } else {
                // Never cancel the customer's order because KIOT refused it: the
                // order stays as it is and the shop sorts it out by hand.
                $reason = $this->describe($code, $message, $body);
                $order->update([
                    'kiot_sync_status' => $classification === 'business_rejection' ? 'rejected' : 'failed',
                    'kiot_sync_error_code' => $code, 'kiot_sync_error_message' => 'Chưa vào KIOT: '.$reason,
                ]);
                OrderHistoryRecorder::kiot($order, "Chưa vào KIOT ({$code}): {$reason}");
            }
        }, 3);
    }

    /** Receipts and invoices: 4xx is final (rejected), connection/5xx/429 retry; the order's sync status is left alone. */
    private function markFulfillmentFailure(IntegrationOutboxEvent $locked, Order $order, string $code, string $message, ?int $status, ?array $body, string $classification, int $maxAttempts): void
    {
        if ($classification === 'retryable' && $locked->attempt_count < $maxAttempts) {
            $locked->update([
                'status' => 'retrying', 'locked_at' => null,
                'next_attempt_at' => now()->addSeconds($this->backoffSeconds($locked->attempt_count))->ceilSecond(),
                'last_error_code' => $code, 'last_error_message' => $message,
                'response_status' => $status, 'response_body' => $body,
            ]);
            OrderHistoryRecorder::kiot($order, self::fulfillmentLabel($locked)." lần {$locked->attempt_count} lỗi ({$code}: {$message}), hệ thống sẽ tự thử lại.");

            return;
        }

        $rejected = $classification === 'business_rejection' || ($status !== null && $status >= 400 && $status < 500 && $status !== 429);
        $locked->update([
            'status' => $rejected ? 'rejected' : 'dead_letter', 'locked_at' => null, 'next_attempt_at' => null,
            'last_error_code' => $code, 'last_error_message' => $message,
            'response_status' => $status, 'response_body' => $body,
        ]);
        OrderHistoryRecorder::kiot($order, $this->fulfillmentRejectedMessage($locked, $code, $message));
        if ($locked->event_type === KiotFulfillmentService::EVENT_PAYMENT) {
            $this->processNextFulfillment($locked);
        }
    }

    /** KIOT's message plus the SKUs it names, e.g. "SKU không có trong KIOT: WEB-NEW-1". */
    private function describe(string $code, string $message, ?array $body): string
    {
        $skus = collect((array) data_get($body, 'error.details', []))
            ->map(fn ($detail) => is_array($detail) ? ($detail['sku'] ?? null) : null)
            ->filter()->unique()->implode(', ');

        return mb_substr($skus !== '' ? "{$message} (SKU: {$skus})" : $message, 0, 900);
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
