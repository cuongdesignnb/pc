<?php

namespace App\Services\Integrations\Kiot;

use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Orders\OrderHistoryRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Tự tạo hoá đơn KIOT khi đơn web Đã giao và phiếu thu khi Đã thanh toán".
 *
 * When the switch is on and the order is in KIOT, money recorded on the
 * website (admin, API token, SePay) becomes a KIOT receipt for the new amount
 * only, and the order reaching "delivered" becomes a KIOT invoice, once.
 * Receipts are queued before the invoice so the deposit counts at conversion.
 * Events go through the KIOT outbox (retries, history) and never touch the
 * order's own KIOT sync status.
 */
class KiotFulfillmentService
{
    public const SETTING_KEY = 'kiot_auto_fulfillment_enabled';

    public const EVENT_DELIVER = 'order.deliver';

    public const EVENT_PAYMENT = 'order.payment';

    public const EVENT_TYPES = [self::EVENT_PAYMENT, self::EVENT_DELIVER];

    public const PAYMENT_METHODS = ['bank_transfer', 'cash', 'cod', 'sepay', 'other'];

    /** @var list<array{method?: string, reference?: ?string, paid_at?: ?string}> */
    private static array $paymentContext = [];

    public function __construct(
        private readonly KiotClient $client,
        private readonly KiotConfigurationResolver $resolver,
    ) {}

    public static function enabled(): bool
    {
        return filter_var(Setting::get(self::SETTING_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function setEnabled(bool $enabled): void
    {
        if ($enabled && ! self::enabled()) {
            self::baselineExistingOrders();
        }
        Setting::updateOrCreate(['key' => self::SETTING_KEY], [
            'value' => $enabled ? '1' : '0', 'group' => 'kiot_integration', 'type' => 'boolean',
            'label' => 'Tự tạo hoá đơn KIOT khi đơn web Đã giao và phiếu thu khi Đã thanh toán', 'is_public' => false,
        ]);
        Cache::forget('setting.'.self::SETTING_KEY);
        Cache::forget('settings.all');
    }

    /**
     * Turning the switch on only covers what happens from now on: money and
     * deliveries already on the website were handled in KIOT by hand, so
     * they must never be sent again as new receipts or invoices.
     */
    public static function baselineExistingOrders(): int
    {
        $count = Order::query()
            ->whereColumn('kiot_paid_synced_amount', '<', 'amount_paid')
            ->update(['kiot_paid_synced_amount' => DB::raw('amount_paid')]);

        return $count + Order::query()
            ->whereNull('kiot_delivery_synced_at')
            ->whereIn('order_status', ['delivered', 'cancelled'])
            ->update(['kiot_delivery_synced_at' => now()]);
    }

    /**
     * How the money just recorded was paid, for the KIOT receipt.
     *
     * @template T
     *
     * @param  array{method?: string, reference?: ?string, paid_at?: ?string}  $context
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withPayment(array $context, callable $callback): mixed
    {
        self::$paymentContext[] = $context;
        try {
            return $callback();
        } finally {
            array_pop(self::$paymentContext);
        }
    }

    /** Called from the Order "updated" hook: failures are logged, never thrown. */
    public function syncSafely(Order $order): void
    {
        try {
            $this->sync($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Queue whatever KIOT has not received yet for this order (receipt first, then invoice). */
    public function sync(Order $order): void
    {
        if (! $this->ready($order)) {
            return;
        }

        $context = end(self::$paymentContext) ?: [];
        $eventIds = DB::transaction(function () use ($order, $context) {
            $locked = Order::lockForUpdate()->find($order->id);
            if (! $locked || ! $this->ready($locked)) {
                return [];
            }
            $ids = [];
            $updates = [];
            $delta = (int) $locked->amount_paid - (int) $locked->kiot_paid_synced_amount;
            if ($delta > 0 && $locked->order_status !== 'cancelled') {
                $ids[] = $this->enqueue($locked, self::EVENT_PAYMENT, [
                    'amount' => $delta,
                    'paid_at' => $this->time($context['paid_at'] ?? null) ?? now()->toIso8601String(),
                    'method' => $this->method($locked, $context['method'] ?? null),
                    'reference' => filled($context['reference'] ?? null) ? mb_substr((string) $context['reference'], 0, 100) : null,
                ]);
                $updates['kiot_paid_synced_amount'] = (int) $locked->amount_paid;
            }
            if ($locked->order_status === 'delivered' && ! $locked->kiot_delivery_synced_at) {
                $ids[] = $this->enqueue($locked, self::EVENT_DELIVER, [
                    'delivered_at' => ($locked->delivered_at ?? now())->toIso8601String(),
                    'tracking_code' => $locked->tracking_code,
                    'carrier' => $locked->shipping_carrier,
                ]);
                $updates['kiot_delivery_synced_at'] = now();
            }
            if ($updates) {
                // A query update: no model events, so this does not trigger itself again.
                Order::whereKey($locked->id)->update($updates);
                $order->forceFill($updates)->syncOriginalAttributes(array_keys($updates));
            }

            return $ids;
        }, 3);

        foreach ($eventIds as $eventId) {
            // After the surrounding transaction commits; kiot:retry-outbox picks up the rest.
            ProcessKiotOutboxEvent::dispatch($eventId)->afterCommit();
        }
    }

    /**
     * Money confirmed on the website before KIOT had the order (the deposit
     * or full transfer of a "Chờ cọc"/"Chờ thanh toán" order) goes to KIOT as
     * one receipt right after KIOT creates the order, whatever the auto
     * receipt switch: the KIOT order then shows the deposit and the rest to
     * collect (total − deposit). kiot_paid_synced_amount covers it afterwards,
     * so the automatic receipts never send the same money twice.
     */
    /** Called from the Order "updated" hook: failures are logged, never thrown. */
    public function sendPrepaidSafely(Order $order): void
    {
        try {
            $this->sendPrepaid($order);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function sendPrepaid(Order $order): ?int
    {
        if (! $order->requires_prepayment) {
            return null;
        }
        $runtime = $this->resolver->resolve();
        if (! $runtime->enabled || ! $runtime->orderSyncEnabled) {
            return null;
        }

        $eventId = DB::transaction(function () use ($order) {
            $locked = Order::lockForUpdate()->find($order->id);
            if (! $locked || $locked->kiot_sync_status !== 'synced' || ! $locked->kiot_order_id || $locked->order_status === 'cancelled') {
                return null;
            }
            $delta = (int) $locked->amount_paid - (int) $locked->kiot_paid_synced_amount;
            if ($delta <= 0) {
                return null;
            }
            $transaction = $locked->transactions()->latest('id')->first();
            $id = $this->enqueue($locked, self::EVENT_PAYMENT, [
                'amount' => $delta,
                'paid_at' => ($locked->deposit_paid_at ?? $locked->paid_at ?? now())->toIso8601String(),
                'method' => $this->method($locked, null),
                'reference' => filled($transaction?->reference_code) ? mb_substr((string) $transaction->reference_code, 0, 100) : null,
            ]);
            Order::whereKey($locked->id)->update(['kiot_paid_synced_amount' => (int) $locked->amount_paid]);
            OrderHistoryRecorder::kiot($locked, 'Gửi tiền đã cọc/đã trả '.number_format($delta, 0, ',', '.').'đ sang KIOT (phiếu thu cho đơn).', 'kiot');

            return $id;
        }, 3);

        if ($eventId) {
            ProcessKiotOutboxEvent::dispatch($eventId)->afterCommit();
        }

        return $eventId;
    }

    /**
     * A refused receipt or invoice (e.g. no Serial/IMEI chosen in KIOT yet)
     * is final. After fixing it in KIOT, staff send a fresh event.
     */
    public function requeue(IntegrationOutboxEvent $event): IntegrationOutboxEvent
    {
        return DB::transaction(function () use ($event) {
            $order = Order::lockForUpdate()->findOrFail($event->aggregate_id);
            $payload = collect((array) $event->payload)->except('event_id')->all();
            $fresh = IntegrationOutboxEvent::find($this->enqueue($order, $event->event_type, $payload));
            $event->update(['status' => 'cancelled', 'locked_at' => null]);
            OrderHistoryRecorder::kiot($order, 'Nhân viên gửi lại '.($event->event_type === self::EVENT_DELIVER ? 'yêu cầu tạo hoá đơn' : 'phiếu thu').' sang KIOT.', 'admin');

            return $fresh;
        });
    }

    private function ready(Order $order): bool
    {
        if ($order->kiot_sync_status !== 'synced' || ! $order->kiot_order_id || ! self::enabled()) {
            return false;
        }
        $runtime = $this->resolver->resolve();

        return $runtime->enabled && $runtime->orderSyncEnabled;
    }

    /** @param  array<string, mixed>  $fields */
    private function enqueue(Order $order, string $type, array $fields): int
    {
        $eventId = (string) Str::uuid();
        $payload = ['event_id' => $eventId] + array_filter($fields, fn ($value) => $value !== null);
        $rawBody = $this->client->encode($payload);

        return IntegrationOutboxEvent::create([
            'integration' => 'kiot', 'event_type' => $type,
            'aggregate_type' => Order::class, 'aggregate_id' => $order->id,
            'event_id' => $eventId, 'idempotency_key' => (string) Str::uuid(),
            'payload' => $payload, 'raw_body' => $rawBody, 'payload_hash' => hash('sha256', $rawBody),
            'status' => 'pending', 'next_attempt_at' => now(),
        ])->id;
    }

    private function method(Order $order, ?string $method): string
    {
        if ($method && in_array($method, self::PAYMENT_METHODS, true)) {
            return $method;
        }

        return match ($order->payment_method) {
            'sepay' => 'sepay',
            'cod' => 'cod',
            'bank_transfer' => 'bank_transfer',
            'cash' => 'cash',
            default => 'other',
        };
    }

    private function time(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
