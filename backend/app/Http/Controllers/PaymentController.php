<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Show checkout form that auto-submits to SePay Payment Gateway.
     * This is used for the redirect-based payment flow (SePay Checkout).
     */
    public function checkout(string $invoice)
    {
        $order = Order::where('order_number', $invoice)
            ->where('payment_status', 'unpaid')
            ->firstOrFail();

        $sepay = config('services.sepay');
        $env = $sepay['env'] ?? 'sandbox';
        $endpoint = $sepay['endpoints'][$env];

        return view('payment.checkout', [
            'endpoint' => $endpoint,
            'merchant_id' => $sepay['merchant_id'],
            'order' => $order,
            'success_url' => route('payment.success'),
            'error_url' => route('payment.error'),
            'cancel_url' => route('payment.cancel'),
            'ipn_url' => route('payment.ipn'),
        ]);
    }

    /**
     * Payment success callback page.
     */
    public function success(Request $request)
    {
        return view('payment.success', [
            'order_number' => $request->query('order_invoice_number'),
        ]);
    }

    /**
     * Payment error callback page.
     */
    public function error(Request $request)
    {
        return view('payment.error', [
            'order_number' => $request->query('order_invoice_number'),
        ]);
    }

    /**
     * Payment cancel callback page.
     */
    public function cancel(Request $request)
    {
        return view('payment.cancel', [
            'order_number' => $request->query('order_invoice_number'),
        ]);
    }

    /**
     * IPN (Instant Payment Notification) from SePay Payment Gateway.
     * This is different from the webhook/bank transfer callback.
     * SePay Gateway sends this when checkout payment is completed.
     */
    public function ipn(Request $request)
    {
        Log::channel('daily')->info('SePay Gateway IPN received', $request->all());

        $data = $request->json()->all();

        // Verify the notification type
        if (isset($data['notification_type']) && $data['notification_type'] === 'ORDER_PAID') {
            $invoiceNumber = $data['order']['order_invoice_number'] ?? null;

            if ($invoiceNumber) {
                $order = Order::where('order_number', $invoiceNumber)
                    ->where('payment_status', 'unpaid')
                    ->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                        'paid_at' => now(),
                    ]);

                    // Create transaction record if transaction data is available
                    if (isset($data['transaction'])) {
                        $order->transaction()->create([
                            'sepay_transaction_id' => $data['transaction']['id'] ?? 0,
                            'gateway' => 'sepay_gateway',
                            'amount' => $data['order']['order_amount'] ?? $order->total,
                            'reference_code' => $data['transaction']['reference_code'] ?? null,
                            'content' => json_encode($data),
                            'transaction_date' => now(),
                        ]);
                    }

                    Log::info('SePay Gateway IPN: Order paid', [
                        'order_number' => $invoiceNumber,
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
