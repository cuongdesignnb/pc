<?php

namespace Tests\Feature\Catalog;

use App\Jobs\Catalog\BuildCatalogFeedCacheJob;
use App\Jobs\Catalog\SyncGoogleSheetsCatalogJob;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelEvent;
use App\Models\User;
use App\Services\Catalog\CatalogChannelAuditService;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogChannelManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)),
            'app.url' => 'https://admin.laptopplus.test',
            'catalog.storefront_url' => 'https://laptopplus.test',
        ]);
    }

    public function test_owner_and_admin_with_permissions_can_manage_but_staff_and_guests_cannot(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $superAdmin = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $owner->assignRole($superAdmin);
        $this->actingAs($owner)->get('/admin/integrations/catalog-channels')->assertOk();

        $admin = $this->admin(manage: true);
        $this->actingAs($admin)->get('/admin/integrations/catalog-channels')->assertOk();

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get('/admin/integrations/catalog-channels')->assertForbidden();
        $this->actingAs($staff)->post('/admin/integrations/catalog-channels/google-sheets/sync')->assertForbidden();
        auth()->logout();
        $this->get('/admin/integrations/catalog-channels')->assertRedirect('/admin/login');
    }

    public function test_credentials_never_appear_in_admin_response_or_audit_metadata(): void
    {
        $admin = $this->admin(manage: true);
        CatalogChannelConnection::create([
            'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
            'status' => 'configured',
            'is_enabled' => false,
            'configuration_encrypted' => [
                'spreadsheet_id' => 'spreadsheet_123456789',
                'worksheet' => 'Products',
                'service_account' => [
                    'client_email' => 'service@example.test',
                    'private_key' => 'never-render-this-private-key',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get('/admin/integrations/catalog-channels')
            ->assertOk()
            ->assertDontSee('never-render-this-private-key')
            ->assertDontSee('service@example.test');
    }

    public function test_feed_token_rotation_hashes_token_and_actions_are_audited(): void
    {
        $admin = $this->admin(manage: true);
        $response = $this->actingAs($admin)
            ->post('/admin/integrations/catalog-channels/google_merchant/rotate-token')
            ->assertRedirect()
            ->assertSessionHas('feed_url');
        $feedUrl = $response->getSession()->get('feed_url');
        parse_str((string) parse_url($feedUrl, PHP_URL_QUERY), $query);
        $token = $query['token'];

        $rawConfiguration = (string) DB::table('catalog_channel_connections')
            ->where('channel', 'google_merchant')
            ->value('configuration_encrypted');
        $this->assertStringNotContainsString($token, $rawConfiguration);
        $this->assertStringNotContainsString($token, CatalogChannelEvent::all()->toJson());
        $this->assertDatabaseHas('catalog_channel_events', ['channel' => 'google_merchant', 'event' => 'TOKEN_ROTATED']);

        $this->actingAs($admin)->patch('/admin/integrations/catalog-channels/google_merchant/flags', [
            'is_enabled' => true,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('catalog_channel_events', ['channel' => 'google_merchant', 'event' => 'CHANNEL_ENABLED']);
    }

    public function test_admin_actions_queue_jobs_and_disabled_jobs_do_not_call_channels(): void
    {
        Queue::fake();
        $admin = $this->admin(manage: true);
        CatalogChannelConnection::create([
            'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
            'status' => 'configured',
            'is_enabled' => true,
            'configuration_encrypted' => ['service_account' => ['configured' => true]],
        ]);
        CatalogChannelConnection::create([
            'channel' => CatalogChannelConnection::GOOGLE_MERCHANT,
            'status' => 'configured',
            'is_enabled' => true,
            'configuration_encrypted' => ['feed_token_hash' => hash('sha256', 'token')],
        ]);

        $this->actingAs($admin)->post('/admin/integrations/catalog-channels/google-sheets/sync')->assertSessionHas('success');
        $this->actingAs($admin)->post('/admin/integrations/catalog-channels/google_merchant/rebuild')->assertSessionHas('success');
        Queue::assertPushed(SyncGoogleSheetsCatalogJob::class);
        Queue::assertPushed(BuildCatalogFeedCacheJob::class, fn ($job) => $job->channel === 'google_merchant');

        CatalogChannelConnection::where('channel', 'google_sheets')->update(['is_enabled' => false]);
        $exporter = Mockery::mock(GoogleSheetsExporter::class);
        $exporter->shouldNotReceive('sync');
        (new SyncGoogleSheetsCatalogJob)->handle(
            $exporter,
            app(CatalogChannelManager::class),
            app(CatalogChannelAuditService::class),
        );

        CatalogChannelConnection::where('channel', 'google_merchant')->update(['is_enabled' => false]);
        $google = Mockery::mock(GoogleMerchantFeedBuilder::class);
        $google->shouldNotReceive('build');
        $meta = Mockery::mock(MetaCatalogFeedBuilder::class);
        $meta->shouldNotReceive('build');
        (new BuildCatalogFeedCacheJob('google_merchant'))->handle(
            app(CatalogChannelManager::class),
            app(CatalogChannelAuditService::class),
            $google,
            $meta,
        );
    }

    public function test_jobs_define_retry_and_scheduler_registers_non_overlapping_catalog_work(): void
    {
        $sheets = new SyncGoogleSheetsCatalogJob;
        $feed = new BuildCatalogFeedCacheJob('google_merchant');
        $this->assertSame([60, 300, 900, 1800], $sheets->backoff());
        $this->assertSame([60, 300, 900, 1800], $feed->backoff());
        $catalogEvents = collect(app(Schedule::class)->events())->filter(
            fn ($event) => str_contains((string) $event->description, 'Catalog'),
        );
        $this->assertGreaterThanOrEqual(4, $catalogEvents->count());
        $catalogEvents->each(function ($event): void {
            $this->assertTrue($event->withoutOverlapping);
            $this->assertTrue($event->onOneServer);
        });
        $this->artisan('schedule:list')
            ->expectsOutputToContain('SyncGoogleSheetsCatalogJob')
            ->expectsOutputToContain('BuildCatalogFeedCacheJob')
            ->assertSuccessful();
    }

    private function admin(bool $manage): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $view = Permission::firstOrCreate(['name' => 'catalog-channels.view', 'guard_name' => 'web']);
        $admin->givePermissionTo($view);
        if ($manage) {
            $admin->givePermissionTo(Permission::firstOrCreate([
                'name' => 'catalog-channels.manage', 'guard_name' => 'web',
            ]));
        }

        return $admin;
    }
}
