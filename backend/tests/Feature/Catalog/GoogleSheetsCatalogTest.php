<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelItemState;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\GoogleSheets\GoogleSheetsClient;
use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class GoogleSheetsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32)),
            'catalog.storefront_url' => 'https://laptopplus.test',
            'catalog.sync_chunk_size' => 100,
        ]);
        Cache::flush();
    }

    public function test_google_client_authenticates_with_service_account_and_batch_reads(): void
    {
        $configuration = $this->configurationWithRealTestKey();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
                $this->assertNotEmpty($request['assertion']);

                return Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]);
            }

            $this->assertSame('Bearer fake-access-token', $request->header('Authorization')[0]);

            return Http::response([
                'spreadsheetId' => 'spreadsheet_123456789',
                'sheets' => [['properties' => ['title' => 'Products']]],
            ]);
        });

        $result = app(GoogleSheetsClient::class)->test($configuration);

        $this->assertSame('spreadsheet_123456789', $result['spreadsheet_id']);
        Http::assertSentCount(2);
    }

    public function test_google_client_auth_failure_is_safe(): void
    {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 401)]);

        try {
            app(GoogleSheetsClient::class)->test($this->configurationWithRealTestKey());
            $this->fail('Invalid Google credentials must fail closed.');
        } catch (CatalogChannelException $exception) {
            $this->assertSame('GOOGLE_AUTH_FAILED', $exception->errorCode);
            $this->assertStringNotContainsString('invalid_grant', $exception->getMessage());
        }
    }

    public function test_sync_batches_upserts_escapes_formula_text_and_records_item_state(): void
    {
        $this->connection(enabled: true);
        $category = $this->category();
        $product = $this->product($category, 1, ['name' => '=IMPORTXML("https://evil.test")']);
        $product->images()->create(['url' => 'https://cdn.laptopplus.test/image.jpg', 'is_primary' => true]);
        $invalid = $this->product($category, 2, ['sku' => 'ZERO-2', 'slug' => 'zero-2', 'price' => 0]);
        $invalid->images()->create(['url' => 'https://cdn.laptopplus.test/zero.jpg', 'is_primary' => true]);
        $deleted = $this->product($category, 3, ['sku' => 'DELETED-3', 'slug' => 'deleted-3']);
        $deleted->images()->create(['url' => 'https://cdn.laptopplus.test/deleted.jpg', 'is_primary' => true]);
        $deleted->delete();

        $written = [];
        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readRows')->once()->andReturn([]);
        $client->shouldReceive('rowRange')->andReturnUsing(fn (array $configuration, int $row) => "'Products'!A{$row}:S{$row}");
        $client->shouldReceive('writeRows')->once()->andReturnUsing(function (array $configuration, array $ranges) use (&$written): void {
            $written = $ranges;
        });
        $this->app->instance(GoogleSheetsClient::class, $client);

        $report = app(GoogleSheetsExporter::class)->sync();

        $this->assertSame(3, $report['TOTAL_PRODUCTS']);
        $this->assertSame(1, $report['VALID_PRODUCTS']);
        $this->assertSame(2, $report['INVALID_PRODUCTS']);
        $this->assertCount(4, $written);
        $this->assertSame(GoogleSheetsExporter::HEADERS, $written[0]['values'][0]);
        $this->assertStringStartsWith("'=", $written[1]['values'][0][2]);
        $this->assertSame('INVALID', $written[2]['values'][0][14]);
        $this->assertStringContainsString('PRICE_MISSING', $written[2]['values'][0][15]);
        $this->assertSame('DELETED', $written[3]['values'][0][14]);
        $this->assertSame(3, CatalogChannelItemState::where('channel', 'google_sheets')->count());
    }

    public function test_dry_run_never_calls_write_api_or_creates_item_state(): void
    {
        $this->connection(enabled: false);
        $category = $this->category();
        $product = $this->product($category, 10);
        $product->images()->create(['url' => 'https://cdn.laptopplus.test/image.jpg', 'is_primary' => true]);

        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readRows')->once()->andReturn([GoogleSheetsExporter::HEADERS]);
        $client->shouldReceive('rowRange')->once()->andReturn("'Products'!A2:S2");
        $client->shouldNotReceive('writeRows');
        $this->app->instance(GoogleSheetsClient::class, $client);

        $report = app(GoogleSheetsExporter::class)->dryRun();

        $this->assertSame(1, $report['CREATE_CANDIDATES']);
        $this->assertDatabaseCount('catalog_channel_item_states', 0);
        $this->assertDatabaseCount('catalog_channel_sync_conflicts', 0);
        $this->assertDatabaseHas('catalog_channel_sync_runs', ['mode' => 'dry_run', 'status' => 'completed']);
    }

    private function connection(bool $enabled): CatalogChannelConnection
    {
        return CatalogChannelConnection::create([
            'channel' => CatalogChannelConnection::GOOGLE_SHEETS,
            'status' => 'configured',
            'is_enabled' => $enabled,
            'configuration_encrypted' => [
                'spreadsheet_id' => 'spreadsheet_123456789',
                'worksheet' => 'Products',
                'service_account' => ['client_email' => 'service@example.test', 'private_key' => 'test-key'],
            ],
        ]);
    }

    private function configurationWithRealTestKey(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        return [
            'spreadsheet_id' => 'spreadsheet_123456789',
            'worksheet' => 'Products',
            'service_account' => [
                'client_email' => 'service@example.test',
                'private_key' => $privateKey,
            ],
        ];
    }

    private function category(): Category
    {
        return Category::create([
            'provider' => 'kiot', 'remote_category_id' => 1, 'name' => 'Laptop', 'slug' => 'laptop',
            'is_active' => true, 'show_on_pc_website' => true, 'provider_sync_status' => 'active',
        ]);
    }

    private function product(Category $category, int $remoteId, array $overrides = []): Product
    {
        return Product::create($overrides + [
            'category_id' => $category->id,
            'provider' => 'kiot',
            'remote_product_id' => $remoteId,
            'name' => 'Laptop '.$remoteId,
            'slug' => 'laptop-'.$remoteId,
            'sku' => 'SKU-'.$remoteId,
            'price' => 10000000,
            'stock_quantity' => 2,
            'inventory_source' => 'kiot',
            'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available',
            'kiot_sellable' => true,
            'kiot_available_quantity' => 2,
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }
}
