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
        $client->shouldReceive('readRows')
            ->once()
            ->with(Mockery::type('array'), count(GoogleSheetsExporter::HEADERS))
            ->andReturn([]);
        $client->shouldReceive('rowRange')->andReturnUsing(
            fn (array $configuration, int $row, int $columnCount) => "'Products'!A{$row}:AE{$row}",
        );
        $client->shouldReceive('writeRows')->once()->andReturnUsing(function (array $configuration, array $ranges, int $columnCount) use (&$written): void {
            $this->assertSame(count(GoogleSheetsExporter::HEADERS), $columnCount);
            $written = $ranges;
        });
        $this->app->instance(GoogleSheetsClient::class, $client);

        $report = app(GoogleSheetsExporter::class)->sync();

        $this->assertSame(3, $report['TOTAL_PRODUCTS']);
        $this->assertSame(1, $report['VALID_PRODUCTS']);
        $this->assertSame(2, $report['INVALID_PRODUCTS']);
        $this->assertCount(4, $written);
        $this->assertSame("'Products'!A1:AE1", $written[0]['range']);
        $this->assertSame("'Products'!A2:AE2", $written[1]['range']);
        $this->assertSame('Mã ngoài', $written[0]['values'][0][0]);
        $this->assertSame('SKU', $written[0]['values'][0][1]);
        $this->assertSame('Tên sản phẩm', $written[0]['values'][0][2]);
        $this->assertSame('Tình trạng tồn kho', $written[0]['values'][0][8]);
        $this->assertSame('Hiển thị', $written[0]['values'][0][9]);
        $this->assertStringStartsWith("'=", $written[1]['values'][0][2]);
        $this->assertSame('Còn hàng', $written[1]['values'][0][8]);
        $this->assertSame('Có', $written[1]['values'][0][9]);
        $this->assertSame('Không hợp lệ', $written[2]['values'][0][14]);
        $this->assertStringContainsString('Chưa có giá', $written[2]['values'][0][15]);
        $this->assertSame('Đã xóa', $written[3]['values'][0][14]);
        $this->assertSame(3, CatalogChannelItemState::where('channel', 'google_sheets')->count());
    }

    public function test_unconfigured_dry_run_stays_local_and_creates_no_item_state(): void
    {
        $connection = $this->connection(enabled: false);
        $connection->update(['configuration_encrypted' => ['worksheet' => 'Products']]);
        $category = $this->category();
        $product = $this->product($category, 10);
        $product->images()->create(['url' => 'https://cdn.laptopplus.test/image.jpg', 'is_primary' => true]);

        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldNotReceive('readRows');
        $client->shouldReceive('rowRange')->twice()->andReturnUsing(
            fn (array $configuration, int $row, int $columnCount): string => "'Products'!A{$row}:AE{$row}",
        );
        $client->shouldNotReceive('writeRows');
        $this->app->instance(GoogleSheetsClient::class, $client);

        $report = app(GoogleSheetsExporter::class)->dryRun();

        $this->assertSame(1, $report['CREATE_CANDIDATES']);
        $this->assertDatabaseCount('catalog_channel_item_states', 0);
        $this->assertDatabaseCount('catalog_channel_sync_conflicts', 0);
        $this->assertDatabaseHas('catalog_channel_sync_runs', ['mode' => 'dry_run', 'status' => 'completed']);
    }

    public function test_selection_sync_exports_only_selected_products(): void
    {
        $this->connection(enabled: true);
        $category = $this->category();
        $selected = $this->product($category, 20);
        $notSelected = $this->product($category, 21);
        $selected->images()->create(['url' => 'https://cdn.laptopplus.test/selected.jpg', 'is_primary' => true]);
        $notSelected->images()->create(['url' => 'https://cdn.laptopplus.test/not-selected.jpg', 'is_primary' => true]);

        $written = [];
        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readRows')->once()->andReturn([]);
        $client->shouldReceive('rowRange')->andReturnUsing(
            fn (array $configuration, int $row, int $columnCount): string => "'Products'!A{$row}:AE{$row}",
        );
        $client->shouldReceive('writeRows')->once()->andReturnUsing(function (array $configuration, array $ranges, int $columnCount) use (&$written): void {
            $written = $ranges;
        });
        $this->app->instance(GoogleSheetsClient::class, $client);

        $report = app(GoogleSheetsExporter::class)->syncSelection([
            'mode' => 'ids',
            'product_ids' => [$selected->id],
        ]);

        $this->assertSame(1, $report['TOTAL_PRODUCTS']);
        $this->assertCount(2, $written);
        $this->assertSame('SKU-20', $written[1]['values'][0][1]);
        $this->assertDatabaseCount('catalog_channel_item_states', 1);
    }

    public function test_existing_english_headers_and_values_are_localized_in_place(): void
    {
        $this->connection(enabled: true);
        $category = $this->category();
        $product = $this->product($category, 20);

        $legacy = array_fill(0, count(GoogleSheetsExporter::HEADERS), '');
        $legacy[0] = 'kiot:999';
        $legacy[1] = 'LEGACY-999';
        $legacy[8] = 'out_of_stock';
        $legacy[9] = false;
        $legacy[10] = false;
        $legacy[14] = 'INVALID';
        $legacy[15] = 'PRICE_MISSING|IMAGE_MISSING';
        $legacy[19] = 'new';

        $written = [];
        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readRows')->once()->andReturn([GoogleSheetsExporter::HEADERS, $legacy]);
        $client->shouldReceive('rowRange')->andReturnUsing(
            fn (array $configuration, int $row, int $columnCount): string => "'Products'!A{$row}:AE{$row}",
        );
        $client->shouldReceive('writeRows')->once()->andReturnUsing(function (array $configuration, array $ranges) use (&$written): void {
            $written = $ranges;
        });
        $this->app->instance(GoogleSheetsClient::class, $client);

        app(GoogleSheetsExporter::class)->syncSelection(['mode' => 'ids', 'product_ids' => [$product->id]]);

        $legacyRow = collect($written)->firstWhere('range', "'Products'!A2:AE2");
        $this->assertNotNull($legacyRow);
        $this->assertSame('Hết hàng', $legacyRow['values'][0][8]);
        $this->assertSame('Không', $legacyRow['values'][0][9]);
        $this->assertSame('Không hợp lệ', $legacyRow['values'][0][14]);
        $this->assertSame('Chưa có giá, Thiếu ảnh sản phẩm', $legacyRow['values'][0][15]);
        $this->assertSame('Mới', $legacyRow['values'][0][19]);
    }

    public function test_google_client_builds_ranges_for_all_export_columns(): void
    {
        $client = app(GoogleSheetsClient::class);
        $configuration = ['worksheet' => 'Products'];

        $this->assertSame("'Products'!A1:AE1", $client->rowRange($configuration, 1, 31));
    }

    public function test_google_client_reads_all_export_columns(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]);
            }

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame("'Products'!A:AE", $query['ranges'] ?? null);

            return Http::response(['valueRanges' => [['values' => [GoogleSheetsExporter::HEADERS]]]]);
        });

        $rows = app(GoogleSheetsClient::class)->readRows($this->configurationWithRealTestKey(), 31);

        $this->assertSame([GoogleSheetsExporter::HEADERS], $rows);
    }

    public function test_google_client_expands_worksheet_columns_before_writing(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]);
            }

            if (array_key_exists('requests', $request->data())) {
                $this->assertSame([
                    'requests' => [[
                        'appendDimension' => [
                            'sheetId' => 42,
                            'dimension' => 'COLUMNS',
                            'length' => 5,
                        ],
                    ]],
                ], $request->data());

                return Http::response(['replies' => [[]]]);
            }

            if (array_key_exists('valueInputOption', $request->data())) {
                return Http::response(['responses' => [[]]]);
            }

            $this->assertSame('spreadsheet_123456789', basename((string) parse_url($request->url(), PHP_URL_PATH)));

            return Http::response([
                'sheets' => [[
                    'properties' => [
                        'sheetId' => 42,
                        'title' => 'Products',
                        'gridProperties' => ['columnCount' => 26],
                    ],
                ]],
            ]);
        });

        app(GoogleSheetsClient::class)->writeRows(
            $this->configurationWithRealTestKey(),
            [['range' => "'Products'!A1:AE1", 'values' => [GoogleSheetsExporter::HEADERS]]],
            31,
        );

        Http::assertSentCount(4);
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
