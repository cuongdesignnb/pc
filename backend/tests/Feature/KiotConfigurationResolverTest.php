<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KiotConfigurationResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        config()->set('integrations.kiot', [
            'enabled' => true,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
            'base_url' => 'https://environment.kiot.test',
            'client_id' => 'environment-client',
            'secret' => 'environment-secret',
            'api_version' => 'v1',
            'connect_timeout_seconds' => 1,
            'request_timeout_seconds' => 2,
            'product_sync_limit' => 50,
            'product_sync_overlap_seconds' => 60,
            'product_stale_after_minutes' => 15,
            'outbox_max_attempts' => 3,
            'outbox_retry_base_seconds' => 1,
        ]);
    }

    public function test_complete_environment_is_used_only_when_database_history_is_empty(): void
    {
        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('environment', $runtime->source);
        $this->assertTrue($runtime->configured);
        $this->assertTrue($runtime->enabled);
        $this->assertSame('environment-secret', $runtime->secret);
    }

    public function test_incomplete_environment_fails_closed(): void
    {
        config()->set('integrations.kiot.secret', null);

        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('none', $runtime->source);
        $this->assertFalse($runtime->configured);
        $this->assertFalse($runtime->enabled);
    }

    public function test_database_configuration_overrides_environment_and_secret_is_encrypted_and_hidden(): void
    {
        $connection = $this->connection();

        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('database', $runtime->source);
        $this->assertSame($connection->id, $runtime->databaseConnectionId);
        $this->assertSame('database-secret', $runtime->secret);
        $this->assertSame('database-client', $runtime->clientId);
        $this->assertNotSame('database-secret', DB::table('integration_connections')->value('secret_encrypted'));
        $this->assertArrayNotHasKey('secret_encrypted', $connection->fresh()->toArray());
    }

    public function test_disabled_database_configuration_never_falls_back_to_enabled_environment(): void
    {
        $this->connection(['is_enabled' => false, 'product_sync_enabled' => false, 'order_sync_enabled' => false]);

        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('database', $runtime->source);
        $this->assertTrue($runtime->configured);
        $this->assertFalse($runtime->enabled);
    }

    public function test_disconnected_database_history_fails_closed_and_forbids_environment_fallback(): void
    {
        $this->connection([
            'connection_status' => 'disconnected',
            'secret_encrypted' => null,
            'secret_fingerprint' => null,
            'is_enabled' => false,
            'product_sync_enabled' => false,
            'order_sync_enabled' => false,
        ]);

        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('database', $runtime->source);
        $this->assertFalse($runtime->configured);
        $this->assertFalse($runtime->connected);
        $this->assertNull($runtime->secret);
    }

    public function test_invalid_database_ciphertext_fails_closed_without_environment_fallback(): void
    {
        $connection = $this->connection();
        DB::table('integration_connections')->where('id', $connection->id)->update(['secret_encrypted' => 'not-valid-ciphertext']);

        $runtime = app(KiotConfigurationResolver::class)->resolve();

        $this->assertSame('database', $runtime->source);
        $this->assertFalse($runtime->configured);
        $this->assertNull($runtime->secret);
    }

    private function connection(array $overrides = []): IntegrationConnection
    {
        return IntegrationConnection::create(array_merge([
            'provider' => 'kiot',
            'configuration_source' => 'manual',
            'base_url' => 'https://database.kiot.test',
            'client_id' => 'database-client',
            'secret_encrypted' => 'database-secret',
            'secret_fingerprint' => substr(hash('sha256', 'database-secret'), 0, 16),
            'api_version' => 'v1',
            'connection_status' => 'connected',
            'is_enabled' => true,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
            'capabilities' => ['products' => true, 'orders' => true],
        ], $overrides));
    }
}
