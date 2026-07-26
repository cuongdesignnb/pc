<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class KiotClient
{
    private const BASE_PATH = '/api/integrations/v1/pc';

    public function __construct(
        private readonly KiotConfigurationResolver $resolver,
        private readonly KiotSignatureService $signature,
    ) {}

    public function connection(): KiotResponse
    {
        return $this->request('GET', self::BASE_PATH.'/connection', guard: 'configured');
    }

    public function products(array $query = [], bool $requireProductSync = true): KiotResponse
    {
        return $this->request(
            'GET',
            self::BASE_PATH.'/products',
            $query,
            guard: $requireProductSync ? 'product' : 'connected',
        );
    }

    public function categories(array $query = [], bool $requireProductSync = true): KiotResponse
    {
        return $this->request(
            'GET',
            self::BASE_PATH.'/categories',
            $query,
            guard: $requireProductSync ? 'product' : 'connected',
        );
    }

    public function priceBooks(array $query = [], bool $requireProductSync = true): KiotResponse
    {
        return $this->request(
            'GET',
            self::BASE_PATH.'/price-books',
            $query,
            guard: $requireProductSync ? 'product' : 'connected',
        );
    }

    public function product(string $sku, bool $requireProductSync = true): KiotResponse
    {
        return $this->request(
            'GET',
            self::BASE_PATH.'/products/'.rawurlencode(trim($sku)),
            guard: $requireProductSync ? 'product' : 'connected',
        );
    }

    public function createOrder(string $rawBody, string $idempotencyKey): KiotResponse
    {
        return $this->request('POST', self::BASE_PATH.'/orders', [], $rawBody, $idempotencyKey, 'order');
    }

    public function order(string|int $externalOrderId): KiotResponse
    {
        return $this->request('GET', self::BASE_PATH.'/orders/'.rawurlencode((string) $externalOrderId), guard: 'order');
    }

    public function cancelOrder(string|int $externalOrderId, string $rawBody, string $idempotencyKey): KiotResponse
    {
        return $this->request('POST', self::BASE_PATH.'/orders/'.rawurlencode((string) $externalOrderId).'/cancel', [], $rawBody, $idempotencyKey, 'order');
    }

    public function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new KiotIntegrationException('INVALID_PAYLOAD', $exception->getMessage(), 'fatal_conflict', previous: $exception);
        }
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        string $rawBody = '',
        ?string $idempotencyKey = null,
        string $guard = 'integration',
    ): KiotResponse {
        $runtime = $this->resolver->resolve();
        switch ($guard) {
            case 'configured':
                $this->assertConfigured($runtime);
                break;
            case 'connected':
                $this->assertConnected($runtime);
                break;
            case 'product':
                $this->assertProductSyncEnabled($runtime);
                break;
            case 'order':
                $this->assertOrderSyncEnabled($runtime);
                break;
            default:
                $this->assertIntegrationEnabled($runtime);
        }

        $url = $runtime->baseUrl.$path;
        $headers = $this->signature->headers($runtime, $method, $path, $rawBody, $idempotencyKey);
        $request = Http::connectTimeout($runtime->connectTimeoutSeconds)
            ->timeout($runtime->requestTimeoutSeconds)
            ->withoutRedirecting()
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

    public function assertConfigured(?KiotRuntimeConfiguration $runtime = null): void
    {
        $runtime ??= $this->resolver->resolve();
        if (! $runtime->configured || ! $runtime->baseUrl || ! $runtime->clientId || ! $runtime->secret) {
            throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Tích hợp KIOT chưa được cấu hình đầy đủ.');
        }

    }

    public function assertConnected(?KiotRuntimeConfiguration $runtime = null): KiotRuntimeConfiguration
    {
        $runtime ??= $this->resolver->resolve();
        $this->assertConfigured($runtime);
        if (! $runtime->connected) {
            throw new KiotIntegrationException('INTEGRATION_NOT_CONNECTED', 'Kết nối KIOT chưa được xác minh.');
        }

        return $runtime;
    }

    public function assertIntegrationEnabled(?KiotRuntimeConfiguration $runtime = null): KiotRuntimeConfiguration
    {
        $runtime = $this->assertConnected($runtime);
        if (! $runtime->enabled) {
            throw new KiotIntegrationException('INTEGRATION_DISABLED', 'Tích hợp KIOT đang tắt.');
        }

        return $runtime;
    }

    public function assertProductSyncEnabled(?KiotRuntimeConfiguration $runtime = null): KiotRuntimeConfiguration
    {
        $runtime = $this->assertIntegrationEnabled($runtime);
        if (! $runtime->productSyncEnabled) {
            throw new KiotIntegrationException('PRODUCT_SYNC_DISABLED', 'Đồng bộ sản phẩm KIOT đang tắt.');
        }

        return $runtime;
    }

    public function assertOrderSyncEnabled(?KiotRuntimeConfiguration $runtime = null): KiotRuntimeConfiguration
    {
        $runtime = $this->assertIntegrationEnabled($runtime);
        if (! $runtime->orderSyncEnabled) {
            throw new KiotIntegrationException('ORDER_SYNC_DISABLED', 'Đồng bộ đơn hàng KIOT đang tắt.');
        }

        return $runtime;
    }
}
