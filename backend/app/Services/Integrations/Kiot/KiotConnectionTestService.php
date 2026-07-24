<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationConnection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class KiotConnectionTestService
{
    public function __construct(
        private readonly KiotClient $client,
        private readonly KiotConfigurationResolver $resolver,
        private readonly KiotConfigurationAuditService $audit,
    ) {}

    public function test(?User $actor = null, ?Request $request = null): array
    {
        $runtime = $this->resolver->resolve();

        try {
            $this->client->assertConfigured($runtime);
            $response = $this->client->connection();
            $result = $this->validateResponse($response, $runtime);
            $this->recordSuccess($runtime, $result, $actor, $request);

            return ['success' => true] + $result;
        } catch (Throwable $exception) {
            [$code, $message] = $this->safeFailure($exception);
            $this->recordFailure($runtime, $code, $message, $actor, $request);

            return ['success' => false, 'error_code' => $code, 'message' => $message];
        }
    }

    private function validateResponse(KiotResponse $response, KiotRuntimeConfiguration $runtime): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            throw new KiotIntegrationException($response->errorCode() ?? 'CONNECTION_ERROR', 'KIOT từ chối kiểm tra kết nối.');
        }

        $payload = isset($response->body['data']) && is_array($response->body['data'])
            ? $response->body['data']
            : $response->body;
        $capabilities = $payload['capabilities'] ?? null;

        if (($payload['status'] ?? null) !== 'ok' || ! is_string($payload['client_id'] ?? null) || ! is_array($capabilities)) {
            throw new KiotIntegrationException('INVALID_RESPONSE', 'KIOT trả về phản hồi kết nối không hợp lệ.');
        }
        if (($payload['provider'] ?? null) !== 'kiot') {
            throw new KiotIntegrationException('INVALID_PROVIDER', 'Nhà cung cấp kết nối không hợp lệ.');
        }
        if (! hash_equals((string) $runtime->clientId, $payload['client_id'])) {
            throw new KiotIntegrationException('CLIENT_ID_MISMATCH', 'Client ID không khớp.');
        }
        if (($payload['api_version'] ?? null) !== 'v1') {
            throw new KiotIntegrationException('UNSUPPORTED_API_VERSION', 'Phiên bản KIOT API không được hỗ trợ.');
        }

        try {
            CarbonImmutable::parse((string) ($payload['server_time'] ?? ''));
        } catch (Throwable) {
            throw new KiotIntegrationException('INVALID_RESPONSE', 'KIOT trả về thời gian máy chủ không hợp lệ.');
        }

        return [
            'provider' => 'kiot',
            'api_version' => 'v1',
            'client_id' => $payload['client_id'],
            'server_time' => $payload['server_time'],
            'capabilities' => [
                'products' => (bool) ($capabilities['products'] ?? false),
                'orders' => (bool) ($capabilities['orders'] ?? false),
                'price_books' => (bool) ($capabilities['price_books'] ?? false),
                'google_sheets' => (bool) ($capabilities['google_sheets'] ?? false),
            ],
        ];
    }

    private function recordSuccess(
        KiotRuntimeConfiguration $runtime,
        array $result,
        ?User $actor,
        ?Request $request,
    ): void {
        if (! $runtime->databaseConnectionId) {
            return;
        }

        DB::transaction(function () use ($runtime, $result, $actor, $request) {
            $connection = IntegrationConnection::lockForUpdate()->findOrFail($runtime->databaseConnectionId);
            $connection->update([
                'connection_status' => 'connected',
                'api_version' => $result['api_version'],
                'capabilities' => $result['capabilities'],
                'last_tested_at' => now(),
                'last_connected_at' => now(),
                'last_error_at' => null,
                'last_error_code' => null,
                'last_error_message' => null,
                'updated_by' => $actor?->id ?? $connection->updated_by,
            ]);
            $this->audit->record($connection, 'connection.test_succeeded', $actor, [
                'api_version' => $result['api_version'],
                'capabilities' => $result['capabilities'],
            ], $request);
        }, 3);
    }

    private function recordFailure(
        KiotRuntimeConfiguration $runtime,
        string $code,
        string $message,
        ?User $actor,
        ?Request $request,
    ): void {
        if (! $runtime->databaseConnectionId) {
            return;
        }

        DB::transaction(function () use ($runtime, $code, $message, $actor, $request) {
            $connection = IntegrationConnection::lockForUpdate()->find($runtime->databaseConnectionId);
            if (! $connection) {
                return;
            }
            $connection->update([
                'connection_status' => 'error',
                'is_enabled' => false,
                'product_sync_enabled' => false,
                'order_sync_enabled' => false,
                'last_tested_at' => now(),
                'last_error_at' => now(),
                'last_error_code' => $code,
                'last_error_message' => Str::limit($message, 500, ''),
                'updated_by' => $actor?->id ?? $connection->updated_by,
            ]);
            $this->audit->record($connection, 'connection.test_failed', $actor, ['error_code' => $code], $request);
        }, 3);
    }

    private function safeFailure(Throwable $exception): array
    {
        $code = $exception instanceof KiotIntegrationException
            ? $exception->errorCode
            : 'CONNECTION_ERROR';
        $messages = [
            'INTEGRATION_NOT_CONFIGURED' => 'Tích hợp KIOT chưa được cấu hình đầy đủ.',
            'CONNECTION_ERROR' => 'Không thể kết nối KIOT.',
            'CONNECTION_TIMEOUT' => 'Kết nối KIOT đã hết thời gian chờ.',
            'INVALID_RESPONSE' => 'KIOT trả về phản hồi không hợp lệ.',
            'INVALID_PROVIDER' => 'Nhà cung cấp kết nối không hợp lệ.',
            'UNSUPPORTED_API_VERSION' => 'Phiên bản KIOT API không được hỗ trợ.',
            'CLIENT_ID_MISMATCH' => 'Client ID không khớp.',
            'INTEGRATION_REVOKED' => 'Kết nối KIOT đã bị thu hồi.',
        ];

        if (! array_key_exists($code, $messages)) {
            $code = 'CONNECTION_ERROR';
        }

        return [$code, $messages[$code]];
    }
}
