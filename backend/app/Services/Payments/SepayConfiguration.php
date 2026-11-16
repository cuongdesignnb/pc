<?php

namespace App\Services\Payments;

use App\Models\Setting;

/**
 * SePay keys: entered in Cài đặt → Thanh toán (stored encrypted), else the
 * server .env (SEPAY_WEBHOOK_API_KEY, SEPAY_SECRET_KEY).
 *
 *   webhookKey   "API Key" of the SePay bank-transfer webhook. SePay sends it as
 *                "Authorization: Apikey <key>" to /api/v1/sepay/callback.
 *   gatewaySecret Secret key of the SePay payment gateway (checkout), sent as
 *                "X-Secret-Key" to /payment/ipn.
 */
class SepayConfiguration
{
    public function webhookKey(): ?string
    {
        return $this->secret('sepay_webhook_api_key', config('services.sepay.webhook_key'));
    }

    public function gatewaySecret(): ?string
    {
        return $this->secret('sepay_gateway_secret_key', config('services.sepay.secret_key'));
    }

    private function secret(string $key, mixed $fallback): ?string
    {
        $value = Setting::get($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }
}
