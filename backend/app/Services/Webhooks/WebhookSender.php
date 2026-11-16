<?php

namespace App\Services\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends one webhook delivery, signed with the shared secret:
 * X-Laptopplus-Signature: sha256=HMAC_SHA256(secret, timestamp + "." + raw body).
 * 2xx = sent; anything else retries after 1m, 5m, 30m, 2h, 6h, then "dead".
 */
class WebhookSender
{
    public const BACKOFF_SECONDS = [60, 300, 1800, 7200, 21600];

    public const TIMEOUT_SECONDS = 10;

    public function deliver(int $deliveryId): void
    {
        // Claim: only one worker sends a delivery at a time.
        $claimed = WebhookDelivery::whereKey($deliveryId)
            ->whereIn('status', ['pending', 'failed'])
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->update(['locked_until' => now()->addSeconds(self::TIMEOUT_SECONDS + 50)]);
        if (! $claimed) {
            return;
        }
        $delivery = WebhookDelivery::findOrFail($deliveryId);
        if ($delivery->next_attempt_at?->isFuture()) {
            $delivery->update(['locked_until' => null]);

            return;
        }

        $endpoint = WebhookEndpoint::current();
        $ping = $delivery->event === 'ping';
        if (blank($endpoint->url) || blank($endpoint->secret) || (! $ping && ! $endpoint->enabled)) {
            $delivery->update(['status' => 'dead', 'locked_until' => null, 'next_attempt_at' => null, 'last_error' => 'Webhook đang tắt hoặc chưa có URL/secret.']);

            return;
        }

        $raw = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $status = null;
        $error = null;
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(5)
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => 'LaptopPlus-Webhook/1.0',
                    'X-Laptopplus-Event' => $delivery->event,
                    'X-Laptopplus-Delivery' => $delivery->uuid,
                    'X-Laptopplus-Timestamp' => $timestamp,
                    'X-Laptopplus-Signature' => 'sha256='.self::sign((string) $endpoint->secret, $timestamp, (string) $raw),
                ])
                ->withBody((string) $raw, 'application/json')
                ->post((string) $endpoint->url);
            $status = $response->status();
            if (! $response->successful()) {
                $error = 'HTTP '.$status.': '.mb_substr(trim((string) $response->body()), 0, 300);
            }
        } catch (Throwable $exception) {
            $error = mb_substr(class_basename($exception).': '.$exception->getMessage(), 0, 300);
        }

        $attempts = $delivery->attempt_count + 1;
        if ($error === null) {
            $delivery->update([
                'status' => 'sent', 'attempt_count' => $attempts, 'response_status' => $status,
                'last_error' => null, 'sent_at' => now(), 'next_attempt_at' => null, 'locked_until' => null,
            ]);

            return;
        }

        $dead = $ping || $attempts > count(self::BACKOFF_SECONDS);
        $delivery->update([
            'status' => $dead ? 'dead' : 'failed', 'attempt_count' => $attempts, 'response_status' => $status,
            'last_error' => $error, 'locked_until' => null,
            'next_attempt_at' => $dead ? null : now()->addSeconds(self::BACKOFF_SECONDS[$attempts - 1]),
        ]);
    }

    public static function sign(string $secret, string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }
}
