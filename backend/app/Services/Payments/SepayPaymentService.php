<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\SepayPaymentEvent;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SepayPaymentService
{
    public function record(
        string $source,
        int $transactionId,
        string $orderNumber,
        int $amount,
        ?string $referenceCode = null,
    ): array {
        try {
            return DB::transaction(function () use ($source, $transactionId, $orderNumber, $amount, $referenceCode) {
                $event = SepayPaymentEvent::query()
                    ->where('source', $source)
                    ->where('external_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->first();

                if ($event) {
                    return ['event' => $event, 'duplicate' => true, 'processed' => $event->status === 'processed'];
                }

                $order = Order::where('order_number', $orderNumber)->lockForUpdate()->first();
                $event = SepayPaymentEvent::create([
                    'order_id' => $order?->id,
                    'source' => $source,
                    'external_transaction_id' => $transactionId,
                    'amount' => $amount,
                    'reference_code' => $referenceCode,
                    'status' => $order ? 'pending' : 'unmatched',
                    'failure_code' => $order ? null : 'ORDER_NOT_FOUND',
                    'received_at' => now(),
                ]);

                $processed = $order ? $this->processLocked($event, $order) : false;

                return ['event' => $event->fresh(), 'duplicate' => false, 'processed' => $processed];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $event = SepayPaymentEvent::where([
                'source' => $source,
                'external_transaction_id' => $transactionId,
            ])->first();
            if (! $event) {
                throw $exception;
            }

            return ['event' => $event, 'duplicate' => true, 'processed' => $event->status === 'processed'];
        }
    }

    public function reconcileOrder(Order $order): int
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $events = SepayPaymentEvent::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $processed = 0;

            foreach ($events as $event) {
                if ($this->processLocked($event, $lockedOrder)) {
                    $processed++;
                }
            }

            return $processed;
        }, 3);
    }

    /**
     * Every confirmed transfer is added to the order's amount_paid. Reaching
     * the deposit marks the order "deposited", reaching the total marks it
     * "paid"; a smaller transfer is still recorded so admin sees the money.
     */
    private function processLocked(SepayPaymentEvent $event, Order $order): bool
    {
        if ($event->status !== 'pending' || ! $order->acceptsPayments()) {
            return false;
        }
        if (Transaction::where('sepay_transaction_id', $event->external_transaction_id)->exists()) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return false;
        }

        $previousStatus = $order->payment_status;
        $amountPaid = (int) $order->amount_paid + (int) $event->amount;
        $deposit = (int) $order->deposit_amount;
        $updates = ['amount_paid' => $amountPaid];
        $kind = 'PARTIAL';

        if (in_array($previousStatus, ['paid', 'refunded'], true)) {
            $kind = 'EXTRA';
        } elseif ($amountPaid >= (int) $order->total) {
            $kind = $previousStatus === 'deposited' ? 'REMAINDER_PAID' : 'ORDER_PAID';
            $updates += ['payment_status' => 'paid', 'paid_at' => now()];
        } elseif ($deposit > 0 && $amountPaid >= $deposit) {
            $kind = $previousStatus === 'deposited' ? 'PARTIAL' : 'DEPOSIT';
            if ($previousStatus === 'unpaid') {
                $updates += ['payment_status' => 'deposited', 'deposit_paid_at' => now()];
            }
        }
        if (isset($updates['payment_status']) && $order->order_status === 'pending') {
            $updates['order_status'] = 'confirmed';
        }

        $order->update($updates);
        Transaction::create([
            'order_id' => $order->id,
            'sepay_transaction_id' => $event->external_transaction_id,
            'gateway' => $event->source,
            'amount' => $event->amount,
            'reference_code' => $event->reference_code,
            'content' => $kind,
            'transaction_date' => $event->received_at,
        ]);
        $event->update([
            'status' => 'processed',
            'failure_code' => $kind === 'PARTIAL' ? 'BELOW_REQUIRED_AMOUNT' : null,
            'processed_at' => now(),
        ]);

        return true;
    }
}
