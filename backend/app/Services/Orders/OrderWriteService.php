<?php

namespace App\Services\Orders;

use App\Exceptions\KiotIntegrationException;
use App\Exceptions\OrderWriteException;
use App\Models\Order;
use App\Services\Integrations\Kiot\KiotFulfillmentService;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Order changes made outside the admin's own buttons (admin API tokens, the
 * shipment box on the admin order page). Callers wrap these in
 * OrderHistoryRecorder::actingAs() so the history says who did it.
 */
class OrderWriteService
{
    public const API_STATUSES = ['confirmed', 'processing', 'shipping', 'delivered', 'cancelled'];

    public const PAYMENT_METHODS = KiotFulfillmentService::PAYMENT_METHODS;

    private const RANK = ['pending' => 0, 'confirmed' => 1, 'processing' => 2, 'shipping' => 3, 'delivered' => 4];

    public function __construct(private readonly KiotOrderCancellationService $cancellation) {}

    /** Forward moves only (pending → confirmed → processing → shipping → delivered), or a cancel before delivery. */
    public function changeStatus(Order $order, string $status, ?string $reason): Order
    {
        $current = (string) $order->order_status;
        if ($current === $status) {
            return $order;
        }
        if ($current === 'cancelled') {
            throw new OrderWriteException('ORDER_CANCELLED', 'Đơn đã huỷ, không đổi được trạng thái.');
        }
        if ($status === 'cancelled') {
            if ($current === 'delivered') {
                throw new OrderWriteException('INVALID_TRANSITION', 'Đơn đã giao, không huỷ được qua API.');
            }
            try {
                return $this->cancellation->cancel($order, $reason ?? 'Huỷ qua API');
            } catch (KiotIntegrationException $exception) {
                $notCancellable = in_array($exception->errorCode, ['ORDER_ALREADY_INVOICED', 'ORDER_NOT_CANCELLABLE'], true);
                throw new OrderWriteException(
                    $notCancellable ? 'ORDER_NOT_CANCELLABLE' : 'KIOT_'.$exception->errorCode,
                    $notCancellable
                        ? 'Đơn hàng đã được xử lý trong hệ thống kho và không thể hủy.'
                        : 'Chưa huỷ được đơn: '.$exception->getMessage(),
                );
            }
        }
        if (! isset(self::RANK[$status], self::RANK[$current]) || self::RANK[$status] <= self::RANK[$current]) {
            throw new OrderWriteException('INVALID_TRANSITION', 'Không chuyển được trạng thái từ "'.OrderApiPresenter::statusLabel($current).'" sang "'.OrderApiPresenter::statusLabel($status).'" (chỉ chuyển tiếp).');
        }
        $order->update(['order_status' => $status]);

        return $order;
    }

    /**
     * Adds money received outside SePay (transfer, cash, COD). Reaching the
     * total marks the order paid, reaching the deposit marks it deposited.
     */
    public function recordPayment(Order $order, int $amount, ?string $paidAt, ?string $method, ?string $reference): Order
    {
        return DB::transaction(function () use ($order, $amount, $paidAt, $method, $reference) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->order_status === 'cancelled') {
                throw new OrderWriteException('ORDER_CANCELLED', 'Đơn đã huỷ, không ghi nhận thanh toán được.');
            }
            if ($locked->payment_status === 'refunded') {
                throw new OrderWriteException('ORDER_REFUNDED', 'Đơn đã hoàn tiền, không ghi nhận thanh toán được.');
            }
            if ($locked->payment_method === 'sepay' && ! $locked->acceptsPayments()) {
                throw new OrderWriteException('PAYMENT_NOT_ACCEPTED', 'Chỉ có thể xác nhận thanh toán sau khi KIOT chấp nhận đơn hàng.');
            }
            $total = (int) $locked->total;
            $newPaid = (int) $locked->amount_paid + $amount;
            if ($newPaid > $total) {
                throw new OrderWriteException('OVERPAYMENT', 'Số tiền vượt quá số còn phải thu ('.self::money(max($total - (int) $locked->amount_paid, 0)).').');
            }

            $time = $this->time($paidAt) ?? now();
            $updates = ['amount_paid' => $newPaid];
            if ($newPaid >= $total) {
                $updates += ['payment_status' => 'paid', 'paid_at' => $locked->paid_at ?? $time];
            } elseif ($locked->payment_status === 'unpaid' && $newPaid >= (int) $locked->deposit_amount) {
                $updates += ['payment_status' => 'deposited', 'deposit_paid_at' => $locked->deposit_paid_at ?? $time];
            }

            $details = array_filter([$method, filled($reference) ? 'ref '.$reference : null]);
            OrderHistoryRecorder::note($locked, 'payment', 'Ghi nhận thanh toán '.self::money($amount).($details ? ' ('.implode(', ', $details).')' : '')
                .'. Đã thu '.self::money($newPaid).'/'.self::money($total).'.');
            KiotFulfillmentService::withPayment(
                ['method' => $method, 'reference' => $reference, 'paid_at' => $time->toIso8601String()],
                fn () => $locked->update($updates),
            );

            return $locked;
        });
    }

    /**
     * Saves the carrier and tracking code; "delivered" also moves the order
     * to delivered (which sends the KIOT invoice when that switch is on).
     *
     * @param  array{tracking_code?: ?string, shipping_carrier?: ?string, delivered?: bool}  $data
     */
    public function updateShipment(Order $order, array $data): Order
    {
        $delivered = (bool) ($data['delivered'] ?? false);
        if ($order->order_status === 'cancelled' && $delivered) {
            throw new OrderWriteException('ORDER_CANCELLED', 'Đơn đã huỷ, không chuyển sang đã giao được.');
        }

        $fields = [];
        foreach (['tracking_code', 'shipping_carrier'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) $data[$key]);
                $fields[$key] = $value === '' ? null : $value;
            }
        }
        $order->fill($fields);
        if ($order->isDirty(['tracking_code', 'shipping_carrier'])) {
            $parts = array_filter([
                $order->shipping_carrier ? 'đơn vị '.$order->shipping_carrier : null,
                $order->tracking_code ? 'mã vận đơn '.$order->tracking_code : null,
            ]);
            $order->save();
            OrderHistoryRecorder::note($order, 'shipment', $parts ? 'Cập nhật vận đơn: '.implode(', ', $parts).'.' : 'Xoá thông tin vận đơn.');
        }
        if ($delivered && $order->order_status !== 'delivered') {
            $order->update(['order_status' => 'delivered']);
        }

        return $order;
    }

    public static function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    private function time(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
