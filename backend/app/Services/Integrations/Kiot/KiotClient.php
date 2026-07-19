<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class KiotClient
{
    private const BASE_PATH = '/api/integrations/v1/pc';

    public function __construct(private readonly KiotSignatureService $signature) {}

    public function products(array $query = []): KiotResponse
    {
        return $this->request('GET', self::BASE_PATH.'/products', $query);
    }

    public function product(string $sku): KiotResponse
    {
        return $this->request('GET', self::BASE_PATH.'/products/'.rawurlencode(trim($sku)));
    }

    public function createOrder(string $rawBody, string $idempotencyKey): KiotResponse
    {
        return $this->request('POST', self::BASE_PATH.'/orders', [], $rawBody, $idempotencyKey);
    }

    public function order(string|int $externalOrderId): KiotResponse
    {
        return $this->request('GET', self::BASE_PATH.'/orders/'.rawurlencode((string) $externalOrderId));
    }

    public function cancelOrder(string|int $externalOrderId, string $rawBody, string $idempotencyKey): KiotResponse
    {
        return $this->request('POST', self::BASE_PATH.'/orders/'.rawurlencode((string) $externalOrderId).'/cancel', [], $rawBody, $idempotencyKey);
    }

    public function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new KiotIntegrationException('INVALID_PAYLOAD', $exception->getMessage(), 'fatal_conflict', previous: $exception);
        }
    }

    private function request(string $method, string $path, array $query = [], string $rawBody = '', ?string $idempotencyKey = null): KiotResponse
    {
        $this->assertConfigured();
        $url = rtrim((string) config('integrations.kiot.base_url'), '/').$path;
        $headers = $this->signature->headers($method, $path, $rawBody, $idempotencyKey);
        $request = Http::connectTimeout((int) config('integrations.kiot.connect_timeout_seconds'))
            ->timeout((int) config('integrations.kiot.request_timeout_seconds'))
            ->withHeaders($headers);

        try {
            $response = $method === 'GET'
                ? $request->get($url, $query)
                : $request->withBody($rawBody, 'application/json')->post($url);
        } catch (ConnectionException $exception) {
            throw new KiotIntegrationException('CONNECTION_ERROR', 'Không thể kết nối KIOT.', 'retryable', previous: $exception);
        }

        return KiotResponse::fromHttp($response);
    }

    public function assertConfigured(): void
    {
        if (! config('integrations.kiot.enabled')) {
            throw new KiotIntegrationException('INTEGRATION_DISABLED', 'Tích hợp KIOT đang tắt.');
        }
        if (! config('integrations.kiot.base_url') || ! config('integrations.kiot.client_id') || ! config('integrations.kiot.secret')) {
            throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Tích hợp KIOT chưa được cấu hình đầy đủ.');
        }
    }
}
