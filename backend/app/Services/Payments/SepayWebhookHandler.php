<?php

namespace App\Services\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SePay bank-transfer webhook ("Tích hợp WebHooks" in SePay): every incoming
 * transfer to the shop's account is posted here with
 * "Authorization: Apikey <API Key>". A transfer whose content holds an order
 * number (DH + 12 digits) is added to that order's amount paid.
 */
class SepayWebhookHandler
{
    public function __construct(
        private readonly SepayConfiguration $configuration,
        private readonly SepayPaymentService $payments,
    ) {}

    /** Payload shape of the bank-transfer webhook (as opposed to the gateway IPN). */
    public static function looksLikeBankTransfer(Request $request): bool
    {
        return $request->has('transferAmount') && ! $request->has('notification_type');
    }

    public function handle(Request $request): JsonResponse
    {
        $webhookKey = $this->configuration->webhookKey();
        if ($webhookKey === null) {
            Log::error('SePay webhook rejected: the webhook API key is not configured (Cài đặt → Thanh toán → sepay_webhook_api_key or SEPAY_WEBHOOK_API_KEY)');

            return response()->json([
                'success' => false,
                'error_code' => 'SEPAY_WEBHOOK_NOT_CONFIGURED',
                'message' => 'Webhook is not configured: set "API Key webhook SePay" in Cài đặt → Thanh toán (or SEPAY_WEBHOOK_API_KEY).',
            ], 503);
        }
        $authorization = trim((string) $request->header('Authorization'));
        $providedKey = preg_match('/^Apikey\s+(.+)$/i', $authorization, $match) ? trim($match[1]) : '';
        if ($providedKey === '' || ! hash_equals($webhookKey, $providedKey)) {
            Log::warning('SePay webhook rejected: invalid API key');

            return response()->json([
                'success' => false,
                'error_code' => 'SEPAY_WEBHOOK_UNAUTHORIZED',
                'message' => 'Unauthorized: the "Authorization: Apikey ..." header does not match the API key in Cài đặt → Thanh toán.',
            ], 401);
        }

        $request->validate([
            'id' => 'required|integer',
            'transferAmount' => 'required|numeric|min:0',
            'transferType' => 'nullable|string|max:10',
            'content' => 'nullable|string',
            'gateway' => 'nullable|string|max:100',
            'referenceCode' => 'nullable|string|max:255',
            'referenceNumber' => 'nullable|string|max:255',
            'transactionDate' => 'nullable|date',
        ]);

        // Money leaving the account never pays an order.
        if ($request->input('transferType') === 'out') {
            return response()->json(['success' => true, 'message' => 'Outgoing transfer ignored']);
        }

        $content = (string) $request->input('content', '');
        if (! preg_match('/DH\d{12}/i', $content, $matches)) {
            return response()->json(['success' => true, 'message' => 'No matching order']);
        }

        $result = $this->payments->record(
            'sepay_webhook',
            (int) $request->input('id'),
            strtoupper($matches[0]),
            (int) $request->input('transferAmount'),
            // SePay sends referenceCode; referenceNumber is kept for older payloads.
            $request->input('referenceCode') ?? $request->input('referenceNumber'),
        );

        return response()->json([
            'success' => true,
            'duplicate' => $result['duplicate'],
            'payment_status' => $result['processed']
                ? $result['event']->order?->payment_status
                : 'pending_reconciliation',
        ]);
    }
}
