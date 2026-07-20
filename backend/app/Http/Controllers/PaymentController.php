<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\SepayPaymentService;
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
            ->where('kiot_sync_status', 'synced')
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
    public function ipn(Request $request, SepayPaymentService $payments)
    {
        $secret = config('services.sepay.secret_key');
        if (! is_string($secret) || $secret === '') {
            Log::error('SePay Gateway IPN rejected: authentication is not configured');

            return response()->json(['success' => false, 'message' => 'IPN is not configured'], 503);
        }
        $providedSecret = (string) $request->header('X-Secret-Key');
        if ($providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            Log::warning('SePay Gateway IPN rejected: invalid secret');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->json()->all();

        // Verify the notification type
        if (isset($data['notification_type']) && $data['notification_type'] === 'ORDER_PAID') {
            $invoiceNumber = $data['order']['order_invoice_number'] ?? null;
            $transactionId = $data['transaction']['id'] ?? null;

            if ($invoiceNumber && is_numeric($transactionId)) {
                $result = $payments->record(
                    'sepay_gateway',
                    (int) $transactionId,
                    (string) $invoiceNumber,
                    (int) ($data['order']['order_amount'] ?? 0),
                    $data['transaction']['reference_code'] ?? null,
                );

                return response()->json([
                    'success' => true,
                    'duplicate' => $result['duplicate'],
                    'payment_status' => $result['processed'] ? 'paid' : 'pending_reconciliation',
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
