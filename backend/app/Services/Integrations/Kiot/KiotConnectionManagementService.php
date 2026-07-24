<?php

namespace App\Services\Integrations\Kiot;

use App\Exceptions\KiotIntegrationException;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KiotConnectionManagementService
{
    public function __construct(
        private readonly SecureKiotUrl $secureUrl,
        private readonly KiotConfigurationResolver $resolver,
        private readonly KiotConfigurationAuditService $audit,
    ) {}

    public function saveManual(array $data, User $actor, ?Request $request = null): IntegrationConnection
    {
        $baseUrl = $this->secureUrl->normalize($data['base_url']);
        $clientId = trim($data['client_id']);
        $apiVersion = $this->supportedVersion($data['api_version'] ?? 'v1');
        $secret = isset($data['secret']) && trim((string) $data['secret']) !== ''
            ? trim((string) $data['secret'])
            : null;

        return DB::transaction(function () use ($baseUrl, $clientId, $apiVersion, $secret, $actor, $request) {
            $connection = IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)
                ->lockForUpdate()
                ->first();
            $created = $connection === null;

            if (! $connection) {
                if ($secret === null) {
                    throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Secret là bắt buộc cho cấu hình mới.');
                }

                $connection = new IntegrationConnection([
                    'provider' => IntegrationConnection::PROVIDER_KIOT,
                    'created_by' => $actor->id,
                    'connection_status' => 'unconfigured',
                ]);
            }

            $secretChanged = $secret !== null
                && ! hash_equals((string) $connection->secret_fingerprint, $this->fingerprint($secret));
            $coreChanged = $created
                || $connection->base_url !== $baseUrl
                || $connection->client_id !== $clientId
                || $connection->api_version !== $apiVersion
                || $secretChanged;

            if (! $created && $secret === null) {
                try {
                    if (! $connection->secret_encrypted) {
                        throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Secret hiện tại không hợp lệ.');
                    }
                } catch (DecryptException) {
                    throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Secret hiện tại không hợp lệ.');
                }
            }

            $connection->fill([
                'configuration_source' => 'manual',
                'base_url' => $baseUrl,
                'client_id' => $clientId,
                'api_version' => $apiVersion,
                'updated_by' => $actor->id,
                'disconnected_at' => null,
            ]);

            if ($secret !== null) {
                $connection->secret_encrypted = $secret;
                $connection->secret_fingerprint = $this->fingerprint($secret);
            }

            if ($coreChanged) {
                $connection->fill($this->pendingVerificationState());
            }

            $connection->save();

            if ($created) {
                $this->audit->record($connection, 'connection.created', $actor, ['source' => 'manual'], $request);
            } elseif ($secretChanged) {
                $this->audit->record($connection, 'connection.credentials_replaced', $actor, ['source' => 'manual'], $request);
            }
            $this->audit->record($connection, 'connection.manual_saved', $actor, ['requires_retest' => $coreChanged], $request);

            return $connection->fresh();
        }, 3);
    }

    public function assertPairingReplacementAllowed(bool $replaceExisting): void
    {
        if (! $replaceExisting && $this->resolver->hasDatabaseConfigurationHistory()) {
            throw new KiotIntegrationException(
                'CREDENTIAL_REPLACEMENT_CONFIRMATION_REQUIRED',
                'Cần xác nhận trước khi thay thế credential hiện tại.',
            );
        }
    }

    public function importEnvironment(User $actor, ?Request $request = null): IntegrationConnection
    {
        if ($this->resolver->hasDatabaseConfigurationHistory()) {
            throw new KiotIntegrationException('DATABASE_CONFIGURATION_EXISTS', 'Cấu hình database đã tồn tại.');
        }

        $runtime = $this->resolver->environmentBootstrap();
        if (! $runtime->configured || ! $runtime->baseUrl || ! $runtime->clientId || ! $runtime->secret) {
            throw new KiotIntegrationException('ENVIRONMENT_CONFIGURATION_INCOMPLETE', 'Cấu hình môi trường chưa đầy đủ.');
        }

        return DB::transaction(function () use ($runtime, $actor, $request) {
            if (IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)->lockForUpdate()->exists()) {
                throw new KiotIntegrationException('DATABASE_CONFIGURATION_EXISTS', 'Cấu hình database đã tồn tại.');
            }

            $connection = IntegrationConnection::create([
                'provider' => IntegrationConnection::PROVIDER_KIOT,
                'configuration_source' => 'environment_import',
                'base_url' => $runtime->baseUrl,
                'client_id' => $runtime->clientId,
                'secret_encrypted' => $runtime->secret,
                'secret_fingerprint' => $this->fingerprint($runtime->secret),
                'api_version' => $this->supportedVersion($runtime->apiVersion),
                'connection_status' => 'pending_verification',
                'is_enabled' => false,
                'product_sync_enabled' => false,
                'order_sync_enabled' => false,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->audit->record($connection, 'connection.created', $actor, ['source' => 'environment_import'], $request);
            $this->audit->record($connection, 'connection.environment_imported', $actor, [], $request);

            return $connection->fresh();
        }, 3);
    }

    public function storePaired(
        string $baseUrl,
        string $clientId,
        string $secret,
        string $apiVersion,
        bool $replaceExisting,
        User $actor,
        ?Request $request = null,
    ): IntegrationConnection {
        $baseUrl = $this->secureUrl->normalize($baseUrl);
        $apiVersion = $this->supportedVersion($apiVersion);

        return DB::transaction(function () use ($baseUrl, $clientId, $secret, $apiVersion, $replaceExisting, $actor, $request) {
            $connection = IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)
                ->lockForUpdate()
                ->first();
            $created = $connection === null;

            if ($connection && ! $replaceExisting) {
                throw new KiotIntegrationException(
                    'CREDENTIAL_REPLACEMENT_CONFIRMATION_REQUIRED',
                    'Cần xác nhận trước khi thay thế credential hiện tại.',
                );
            }

            $connection ??= new IntegrationConnection([
                'provider' => IntegrationConnection::PROVIDER_KIOT,
                'created_by' => $actor->id,
            ]);
            $connection->fill([
                'configuration_source' => 'pairing',
                'base_url' => $baseUrl,
                'client_id' => trim($clientId),
                'secret_encrypted' => $secret,
                'secret_fingerprint' => $this->fingerprint($secret),
                'api_version' => $apiVersion,
                'updated_by' => $actor->id,
                'disconnected_at' => null,
            ] + $this->pendingVerificationState());
            $connection->save();

            if ($created) {
                $this->audit->record($connection, 'connection.created', $actor, ['source' => 'pairing'], $request);
            } else {
                $this->audit->record($connection, 'connection.credentials_replaced', $actor, ['source' => 'pairing'], $request);
            }
            $this->audit->record($connection, 'connection.paired', $actor, [], $request);

            return $connection->fresh();
        }, 3);
    }

    public function updateFlags(array $flags, bool $confirmOrderSync, User $actor, ?Request $request = null): IntegrationConnection
    {
        return DB::transaction(function () use ($flags, $confirmOrderSync, $actor, $request) {
            $connection = IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)
                ->lockForUpdate()
                ->first();
            if (! $connection) {
                throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Tích hợp KIOT chưa được cấu hình.');
            }
            $before = [
                'is_enabled' => $connection->is_enabled,
                'product_sync_enabled' => $connection->product_sync_enabled,
                'order_sync_enabled' => $connection->order_sync_enabled,
            ];
            $master = (bool) ($flags['is_enabled'] ?? false);
            $product = $master && (bool) ($flags['product_sync_enabled'] ?? false);
            $order = $master && (bool) ($flags['order_sync_enabled'] ?? false);

            if ($master) {
                $this->assertCanEnable($connection);
            }
            if ($product && ! (bool) data_get($connection->capabilities, 'products', false)) {
                throw new KiotIntegrationException('CAPABILITY_NOT_SUPPORTED', 'KIOT chưa hỗ trợ đồng bộ sản phẩm.');
            }
            if ($order && ! (bool) data_get($connection->capabilities, 'orders', false)) {
                throw new KiotIntegrationException('CAPABILITY_NOT_SUPPORTED', 'KIOT chưa hỗ trợ đồng bộ đơn hàng.');
            }
            if ($order && ! $connection->order_sync_enabled && ! $confirmOrderSync) {
                throw new KiotIntegrationException('ORDER_SYNC_CONFIRMATION_REQUIRED', 'Cần xác nhận trước khi bật đồng bộ đơn hàng.');
            }

            $connection->update([
                'is_enabled' => $master,
                'product_sync_enabled' => $product,
                'order_sync_enabled' => $order,
                'updated_by' => $actor->id,
            ]);

            $events = [
                'is_enabled' => ['connection.enabled', 'connection.disabled'],
                'product_sync_enabled' => ['connection.product_sync_enabled', 'connection.product_sync_disabled'],
                'order_sync_enabled' => ['connection.order_sync_enabled', 'connection.order_sync_disabled'],
            ];
            foreach ($events as $field => [$enabledEvent, $disabledEvent]) {
                if ($before[$field] !== $connection->{$field}) {
                    $this->audit->record($connection, $connection->{$field} ? $enabledEvent : $disabledEvent, $actor, [], $request);
                }
            }

            return $connection->fresh();
        }, 3);
    }

    public function disconnect(User $actor, ?Request $request = null): IntegrationConnection
    {
        return DB::transaction(function () use ($actor, $request) {
            $connection = IntegrationConnection::where('provider', IntegrationConnection::PROVIDER_KIOT)
                ->lockForUpdate()
                ->first();
            if (! $connection) {
                throw new KiotIntegrationException('INTEGRATION_NOT_CONFIGURED', 'Tích hợp KIOT chưa được cấu hình.');
            }
            $connection->update([
                'secret_encrypted' => null,
                'secret_fingerprint' => null,
                'connection_status' => 'disconnected',
                'is_enabled' => false,
                'product_sync_enabled' => false,
                'order_sync_enabled' => false,
                'capabilities' => null,
                'updated_by' => $actor->id,
                'disconnected_at' => now(),
            ]);
            $this->audit->record($connection, 'connection.disconnected', $actor, [], $request);

            return $connection->fresh();
        }, 3);
    }

    private function pendingVerificationState(): array
    {
        return [
            'connection_status' => 'pending_verification',
            'is_enabled' => false,
            'product_sync_enabled' => false,
            'order_sync_enabled' => false,
            'capabilities' => null,
            'last_error_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ];
    }

    private function assertCanEnable(IntegrationConnection $connection): void
    {
        try {
            $hasSecret = (bool) $connection->secret_encrypted;
        } catch (DecryptException) {
            $hasSecret = false;
        }

        if ($connection->connection_status !== 'connected'
            || ! $connection->base_url
            || ! $connection->client_id
            || ! $hasSecret) {
            throw new KiotIntegrationException('INTEGRATION_NOT_CONNECTED', 'Chỉ có thể bật sau khi kiểm tra kết nối thành công.');
        }
    }

    private function supportedVersion(string $apiVersion): string
    {
        $apiVersion = strtolower(trim($apiVersion));
        if ($apiVersion !== 'v1') {
            throw new KiotIntegrationException('UNSUPPORTED_API_VERSION', 'Phiên bản KIOT API không được hỗ trợ.');
        }

        return $apiVersion;
    }

    private function fingerprint(string $secret): string
    {
        return substr(hash('sha256', $secret), 0, 16);
    }
}
