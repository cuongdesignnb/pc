<?php

namespace App\Services\Integrations\Kiot;

use App\Models\IntegrationConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Schema;
use Throwable;

class KiotConfigurationResolver
{
    public function __construct(private readonly SecureKiotUrl $secureUrl) {}

    public function resolve(): KiotRuntimeConfiguration
    {
        try {
            if (! Schema::hasTable('integration_connections')) {
                return $this->fromEnvironment();
            }

            $connection = IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)->first();
        } catch (Throwable) {
            return $this->none();
        }

        return $connection ? $this->fromDatabase($connection) : $this->fromEnvironment();
    }

    public function hasDatabaseConfigurationHistory(): bool
    {
        try {
            return Schema::hasTable('integration_connections')
                && IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)->exists();
        } catch (Throwable) {
            return true;
        }
    }

    public function environmentBootstrap(): KiotRuntimeConfiguration
    {
        return $this->fromEnvironment();
    }

    private function fromDatabase(IntegrationConnection $connection): KiotRuntimeConfiguration
    {
        try {
            $secret = $connection->secret_encrypted;
        } catch (DecryptException) {
            $secret = null;
        }

        $baseUrl = $this->safeNormalize($connection->base_url);
        $clientId = $this->nonEmpty($connection->client_id);
        $secret = $this->nonEmpty($secret);
        $configured = $baseUrl !== null && $clientId !== null && $secret !== null
            && ! in_array($connection->connection_status, ['unconfigured', 'disconnected', 'revoked'], true);
        $connected = $configured && $connection->connection_status === 'connected';

        return $this->runtime(
            source: 'database',
            databaseConnectionId: $connection->id,
            baseUrl: $baseUrl,
            clientId: $clientId,
            secret: $secret,
            apiVersion: $connection->api_version ?: 'v1',
            enabled: $configured && $connected && $connection->is_enabled,
            productSyncEnabled: $configured && $connected && $connection->is_enabled && $connection->product_sync_enabled,
            orderSyncEnabled: $configured && $connected && $connection->is_enabled && $connection->order_sync_enabled,
            configured: $configured,
            connected: $connected,
            capabilities: is_array($connection->capabilities) ? $connection->capabilities : [],
        );
    }

    private function fromEnvironment(): KiotRuntimeConfiguration
    {
        $baseUrl = $this->safeNormalize(config('integrations.kiot.base_url'));
        $clientId = $this->nonEmpty(config('integrations.kiot.client_id'));
        $secret = $this->nonEmpty(config('integrations.kiot.secret'));
        $configured = $baseUrl !== null && $clientId !== null && $secret !== null;

        return $this->runtime(
            source: $configured ? 'environment' : 'none',
            databaseConnectionId: null,
            baseUrl: $baseUrl,
            clientId: $clientId,
            secret: $secret,
            apiVersion: (string) config('integrations.kiot.api_version', 'v1'),
            enabled: $configured && (bool) config('integrations.kiot.enabled'),
            productSyncEnabled: $configured && (bool) config('integrations.kiot.enabled') && (bool) config('integrations.kiot.product_sync_enabled'),
            orderSyncEnabled: $configured && (bool) config('integrations.kiot.enabled') && (bool) config('integrations.kiot.order_sync_enabled'),
            configured: $configured,
            connected: $configured,
            capabilities: $configured ? ['products' => true, 'orders' => true, 'price_books' => false, 'google_sheets' => false] : [],
        );
    }

    private function none(): KiotRuntimeConfiguration
    {
        return $this->runtime(
            source: 'none',
            databaseConnectionId: null,
            baseUrl: null,
            clientId: null,
            secret: null,
            apiVersion: 'v1',
            enabled: false,
            productSyncEnabled: false,
            orderSyncEnabled: false,
            configured: false,
            connected: false,
            capabilities: [],
        );
    }

    private function runtime(
        string $source,
        ?int $databaseConnectionId,
        ?string $baseUrl,
        ?string $clientId,
        ?string $secret,
        string $apiVersion,
        bool $enabled,
        bool $productSyncEnabled,
        bool $orderSyncEnabled,
        bool $configured,
        bool $connected,
        array $capabilities,
    ): KiotRuntimeConfiguration {
        return new KiotRuntimeConfiguration(
            source: $source,
            databaseConnectionId: $databaseConnectionId,
            baseUrl: $baseUrl,
            clientId: $clientId,
            secret: $secret,
            apiVersion: $apiVersion,
            enabled: $enabled,
            productSyncEnabled: $productSyncEnabled,
            orderSyncEnabled: $orderSyncEnabled,
            connectTimeoutSeconds: max(1, (int) config('integrations.kiot.connect_timeout_seconds', 3)),
            requestTimeoutSeconds: max(1, (int) config('integrations.kiot.request_timeout_seconds', 10)),
            productSyncLimit: min(100, max(1, (int) config('integrations.kiot.product_sync_limit', 100))),
            productSyncOverlapSeconds: max(0, (int) config('integrations.kiot.product_sync_overlap_seconds', 120)),
            productStaleAfterMinutes: max(1, (int) config('integrations.kiot.product_stale_after_minutes', 15)),
            outboxMaxAttempts: max(1, (int) config('integrations.kiot.outbox_max_attempts', 10)),
            outboxRetryBaseSeconds: max(1, (int) config('integrations.kiot.outbox_retry_base_seconds', 30)),
            configured: $configured,
            connected: $connected,
            capabilities: $capabilities,
        );
    }

    private function safeNormalize(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        try {
            return $this->secureUrl->normalize($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
