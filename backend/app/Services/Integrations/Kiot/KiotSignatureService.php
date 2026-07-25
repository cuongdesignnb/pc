<?php

namespace App\Services\Integrations\Kiot;

use Illuminate\Support\Str;

class KiotSignatureService
{
    public function canonical(string $method, string $path, int $timestamp, string $nonce, string $rawBody): string
    {
        $path = '/'.ltrim((string) parse_url($path, PHP_URL_PATH), '/');

        return implode("\n", [
            strtoupper($method),
            $path,
            (string) $timestamp,
            $nonce,
            hash('sha256', $rawBody),
        ]);
    }

    public function sign(string $method, string $path, int $timestamp, string $nonce, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $this->canonical($method, $path, $timestamp, $nonce, $rawBody), $secret);
    }

    public function headers(
        KiotRuntimeConfiguration $runtime,
        string $method,
        string $path,
        string $rawBody,
        ?string $idempotencyKey = null,
    ): array {
        $timestamp = now()->timestamp;
        $nonce = (string) Str::uuid();

        $headers = [
            'X-Integration-Key' => (string) $runtime->clientId,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $this->sign($method, $path, $timestamp, $nonce, $rawBody, (string) $runtime->secret),
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }
}
