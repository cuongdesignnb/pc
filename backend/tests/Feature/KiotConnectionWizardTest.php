<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\IntegrationConnectionEvent;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KiotConnectionWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('w', 32)));
        config()->set('app.url', 'https://laptopplus.test');
        config()->set('integrations.kiot', [
            'enabled' => false,
            'product_sync_enabled' => false,
            'order_sync_enabled' => false,
            'base_url' => null,
            'client_id' => 'pc-website',
            'secret' => null,
            'api_version' => 'v1',
            'connect_timeout_seconds' => 1,
            'request_timeout_seconds' => 2,
            'product_sync_limit' => 100,
            'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15,
            'outbox_max_attempts' => 3,
            'outbox_retry_base_seconds' => 1,
        ]);
    }

    public function test_pairing_encrypts_secret_discovers_capabilities_and_keeps_all_flags_off(): void
    {
        $admin = $this->admin(edit: true);
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/pair')) {
                $this->assertSame('pair-reference', $request['reference']);
                $this->assertSame('one-time-code', $request['pairing_code']);
                $this->assertSame('https://laptopplus.test', $request['website_url']);
                $this->assertStringNotContainsString('one-time-code', $request->url());
                $this->assertEmpty($request->header('X-Signature'));

                return Http::response([
                    'client_id' => 'pc-website',
                    'secret' => 'paired-secret',
                    'provider_url' => 'https://kiot.test',
                    'api_version' => 'v1',
                ]);
            }

            $this->assertStringEndsWith('/api/integrations/v1/pc/connection', $request->url());
            $this->assertSame('pc-website', $request->header('X-Integration-Key')[0]);
            $this->assertNotEmpty($request->header('X-Signature')[0]);

            return Http::response([
                'status' => 'ok',
                'provider' => 'kiot',
                'api_version' => 'v1',
                'server_time' => '2026-07-25T08:00:00Z',
                'client_id' => 'pc-website',
                'configuration_source' => 'database',
                'capabilities' => [
                    'products' => true,
                    'orders' => true,
                    'price_books' => false,
                    'google_sheets' => false,
                ],
            ]);
        });

        $this->actingAs($admin)->post('/admin/integrations/kiot/pair', [
            'base_url' => 'https://kiot.test',
            'reference' => 'pair-reference',
            'pairing_code' => 'one-time-code',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = IntegrationConnection::firstOrFail();
        $this->assertSame('connected', $connection->connection_status);
        $this->assertFalse($connection->is_enabled);
        $this->assertFalse($connection->product_sync_enabled);
        $this->assertFalse($connection->order_sync_enabled);
        $this->assertTrue($connection->capabilities['products']);
        $this->assertSame('paired-secret', $connection->secret_encrypted);
        $this->assertNotSame('paired-secret', DB::table('integration_connections')->value('secret_encrypted'));
        $this->assertArrayNotHasKey('secret_encrypted', $connection->toArray());

        $serializedEvents = IntegrationConnectionEvent::all()->toJson();
        $this->assertStringNotContainsString('paired-secret', $serializedEvents);
        $this->assertStringNotContainsString('one-time-code', $serializedEvents);
        $this->actingAs($admin)->get('/admin/integrations/kiot')
            ->assertOk()
            ->assertDontSee('paired-secret')
            ->assertDontSee('one-time-code');
    }

    #[DataProvider('pairingErrors')]
    public function test_pairing_provider_errors_are_mapped_without_persisting_credentials(string $providerCode): void
    {
        $admin = $this->admin(edit: true);
        Http::fake(['*' => Http::response(['error' => ['code' => $providerCode, 'message' => 'unsafe provider detail']], 422)]);

        $response = $this->actingAs($admin)->post('/admin/integrations/kiot/pair', [
            'base_url' => 'https://kiot.test',
            'reference' => 'reference',
            'pairing_code' => 'sensitive-code',
        ]);

        $response->assertRedirect()->assertSessionHasErrors('kiot');
        $this->assertDatabaseCount('integration_connections', 0);
        $messages = session('errors')->getBag('default')->get('kiot');
        $this->assertStringNotContainsString('unsafe provider detail', implode(' ', $messages));
    }

    public static function pairingErrors(): array
    {
        return [
            ['INVALID_PAIRING_TOKEN'],
            ['PAIRING_ATTEMPTS_EXCEEDED'],
            ['PAIRING_TOKEN_USED'],
            ['PAIRING_TOKEN_EXPIRED'],
            ['PAIRING_ORIGIN_MISMATCH'],
            ['INTEGRATION_REVOKED'],
        ];
    }

    public function test_pairing_rejects_provider_origin_mismatch_before_saving_secret(): void
    {
        $admin = $this->admin(edit: true);
        Http::fake(['*' => Http::response([
            'client_id' => 'pc-website', 'secret' => 'must-not-save',
            'provider_url' => 'https://other.test', 'api_version' => 'v1',
        ])]);

        $this->actingAs($admin)->post('/admin/integrations/kiot/pair', [
            'base_url' => 'https://kiot.test', 'reference' => 'reference', 'pairing_code' => 'code',
        ])->assertSessionHasErrors('kiot');

        $this->assertDatabaseCount('integration_connections', 0);
    }

    public function test_pairing_requires_replacement_confirmation_before_calling_provider(): void
    {
        $admin = $this->admin(edit: true);
        $this->connectedConnection();
        Http::fake();

        $this->actingAs($admin)->post('/admin/integrations/kiot/pair', [
            'base_url' => 'https://kiot.test',
            'reference' => 'reference',
            'pairing_code' => 'must-not-be-consumed',
            'replace_existing_credentials' => false,
        ])->assertSessionHasErrors('kiot');

        Http::assertNothingSent();
    }

    public function test_pairing_connection_failure_is_safe_and_does_not_persist_pairing_code(): void
    {
        $admin = $this->admin(edit: true);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/pair')
            ? Http::response([
                'client_id' => 'pc-website', 'secret' => 'paired-secret',
                'provider_url' => 'https://kiot.test', 'api_version' => 'v1',
            ])
            : throw new ConnectionException('internal hostname and credential detail'));

        $this->actingAs($admin)->post('/admin/integrations/kiot/pair', [
            'base_url' => 'https://kiot.test', 'reference' => 'reference', 'pairing_code' => 'never-persist',
        ])->assertSessionHasErrors('kiot');

        $connection = IntegrationConnection::firstOrFail();
        $this->assertSame('error', $connection->connection_status);
        $this->assertSame('CONNECTION_ERROR', $connection->last_error_code);
        $this->assertSame('Không thể kết nối KIOT.', $connection->last_error_message);
        $this->assertStringNotContainsString('never-persist', IntegrationConnectionEvent::all()->toJson());
    }

    public function test_manual_configuration_and_environment_import_never_enable_flags(): void
    {
        $admin = $this->admin(edit: true);

        $this->actingAs($admin)->post('/admin/integrations/kiot/manual', [
            'base_url' => 'https://manual.kiot.test',
            'client_id' => 'manual-client',
            'secret' => 'manual-secret',
            'api_version' => 'v1',
        ])->assertSessionHas('success');

        $connection = IntegrationConnection::firstOrFail();
        $this->assertSame('pending_verification', $connection->connection_status);
        $this->assertFalse($connection->is_enabled);
        $this->assertFalse($connection->product_sync_enabled);
        $this->assertFalse($connection->order_sync_enabled);

        IntegrationConnectionEvent::query()->delete();
        $connection->delete();
        config()->set('integrations.kiot.base_url', 'https://environment.kiot.test');
        config()->set('integrations.kiot.client_id', 'environment-client');
        config()->set('integrations.kiot.secret', 'environment-secret');
        config()->set('integrations.kiot.enabled', true);
        config()->set('integrations.kiot.product_sync_enabled', true);
        config()->set('integrations.kiot.order_sync_enabled', true);

        $this->actingAs($admin)->post('/admin/integrations/kiot/import-environment')->assertSessionHas('success');
        $imported = IntegrationConnection::firstOrFail();
        $this->assertSame('environment_import', $imported->configuration_source);
        $this->assertFalse($imported->is_enabled);
        $this->assertFalse($imported->product_sync_enabled);
        $this->assertFalse($imported->order_sync_enabled);
    }

    public function test_flag_invariants_and_disconnect_are_atomic_and_fail_closed(): void
    {
        $admin = $this->admin(edit: true);
        $connection = $this->connectedConnection();

        $this->actingAs($admin)->patch('/admin/integrations/kiot/flags', [
            'is_enabled' => true,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
            'confirm_order_sync' => false,
        ])->assertSessionHasErrors('kiot');
        $this->assertFalse($connection->fresh()->is_enabled);

        $this->actingAs($admin)->patch('/admin/integrations/kiot/flags', [
            'is_enabled' => true,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
            'confirm_order_sync' => true,
        ])->assertSessionHas('success');
        $this->assertTrue($connection->fresh()->order_sync_enabled);

        $this->actingAs($admin)->patch('/admin/integrations/kiot/flags', [
            'is_enabled' => false,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
        ])->assertSessionHas('success');
        $connection->refresh();
        $this->assertFalse($connection->is_enabled);
        $this->assertFalse($connection->product_sync_enabled);
        $this->assertFalse($connection->order_sync_enabled);

        config()->set('integrations.kiot.base_url', 'https://environment.kiot.test');
        config()->set('integrations.kiot.client_id', 'environment-client');
        config()->set('integrations.kiot.secret', 'environment-secret');
        config()->set('integrations.kiot.enabled', true);
        $this->actingAs($admin)->post('/admin/integrations/kiot/disconnect', ['confirm_disconnect' => 'yes'])
            ->assertSessionHas('success');

        $runtime = app(KiotConfigurationResolver::class)->resolve();
        $this->assertSame('database', $runtime->source);
        $this->assertFalse($runtime->configured);
        $this->assertFalse($runtime->enabled);
        $this->assertNull($connection->fresh()->secret_encrypted);
    }

    public function test_view_permission_cannot_mutate_connection(): void
    {
        $admin = $this->admin(edit: false);

        $this->actingAs($admin)->get('/admin/integrations/kiot')->assertOk();
        $this->actingAs($admin)->post('/admin/integrations/kiot/manual', [
            'base_url' => 'https://kiot.test', 'client_id' => 'client', 'secret' => 'secret',
        ])->assertForbidden();
        $this->assertDatabaseCount('integration_connections', 0);
    }

    public function test_guest_is_redirected_from_connection_wizard(): void
    {
        $this->get('/admin/integrations/kiot')->assertRedirect('/admin/login');
    }

    private function admin(bool $edit): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $view = Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
        $admin->givePermissionTo($view);
        if ($edit) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        }

        return $admin;
    }

    private function connectedConnection(): IntegrationConnection
    {
        return IntegrationConnection::create([
            'provider' => 'kiot',
            'configuration_source' => 'manual',
            'base_url' => 'https://kiot.test',
            'client_id' => 'pc-website',
            'secret_encrypted' => 'database-secret',
            'secret_fingerprint' => substr(hash('sha256', 'database-secret'), 0, 16),
            'api_version' => 'v1',
            'connection_status' => 'connected',
            'is_enabled' => false,
            'product_sync_enabled' => false,
            'order_sync_enabled' => false,
            'capabilities' => ['products' => true, 'orders' => true],
        ]);
    }
}
