<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KiotPairingService
{
    private const PAIRING_PATH = '/api/integrations/v1/pc/pair';

    public function __construct(
        private readonly SecureKiotUrl $secureUrl,
        private readonly KiotConnectionManagementService $management,
        private readonly KiotConnectionTestService $connectionTest,
    ) {}

    public function pair(array $data, User $actor, Request $request): array
    {
        $baseUrl = $this->secureUrl->normalize($data['base_url']);
        $websiteUrl = rtrim((string) config('app.url'), '/');
        $replaceExisting = (bool) ($data['replace_existing_credentials'] ?? false);
        $this->management->assertPairingReplacementAllowed($replaceExisting);

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withoutRedirecting()
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.self::PAIRING_PATH, [
                    'reference' => trim($data['reference']),
                    'pairing_code' => $data['pairing_code'],
                    'website_url' => $websiteUrl,
                ]);
        } catch (ConnectionException $exception) {
            throw new KiotIntegrationException('CONNECTION_ERROR', 'Không thể kết nối KIOT.', 'retryable', previous: $exception);
        }

        $body = $response->json() ?? [];
        if (! $response->successful()) {
            $code = $body['error']['code'] ?? 'CONNECTION_ERROR';
            throw new KiotIntegrationException($this->safeProviderCode($code), $this->pairingErrorMessage($code), httpStatus: $response->status());
        }

        $payload = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        foreach (['client_id', 'secret', 'provider_url', 'api_version'] as $field) {
            if (! is_string($payload[$field] ?? null) || trim($payload[$field]) === '') {
                throw new KiotIntegrationException('INVALID_RESPONSE', 'KIOT trả về phản hồi ghép nối không hợp lệ.');
            }
        }

        $this->secureUrl->assertSameOrigin($baseUrl, $payload['provider_url']);
        if (strtolower($payload['api_version']) !== 'v1') {
            throw new KiotIntegrationException('UNSUPPORTED_API_VERSION', 'Phiên bản KIOT API không được hỗ trợ.');
        }

        $connection = $this->management->storePaired(
            baseUrl: $baseUrl,
            clientId: $payload['client_id'],
            secret: $payload['secret'],
            apiVersion: $payload['api_version'],
            replaceExisting: $replaceExisting,
            actor: $actor,
            request: $request,
        );
        $test = $this->connectionTest->test($actor, $request);

        return ['connection' => $connection->fresh(), 'test' => $test];
    }

    private function safeProviderCode(string $code): string
    {
        $allowed = [
            'INVALID_PAIRING_TOKEN',
            'PAIRING_ATTEMPTS_EXCEEDED',
            'PAIRING_TOKEN_USED',
            'PAIRING_TOKEN_EXPIRED',
            'PAIRING_ORIGIN_MISMATCH',
            'INTEGRATION_REVOKED',
        ];

        return in_array($code, $allowed, true) ? $code : 'CONNECTION_ERROR';
    }

    private function pairingErrorMessage(string $code): string
    {
        return match ($code) {
            'INVALID_PAIRING_TOKEN' => 'Mã ghép nối không hợp lệ.',
            'PAIRING_ATTEMPTS_EXCEEDED' => 'Đã vượt quá số lần thử ghép nối.',
            'PAIRING_TOKEN_USED' => 'Mã ghép nối đã được sử dụng.',
            'PAIRING_TOKEN_EXPIRED' => 'Mã ghép nối đã hết hạn.',
            'PAIRING_ORIGIN_MISMATCH' => 'Website URL không khớp với mã ghép nối.',
            'INTEGRATION_REVOKED' => 'Kết nối KIOT đã bị thu hồi.',
            default => 'Không thể ghép nối với KIOT.',
        };
    }
}
