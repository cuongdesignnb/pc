<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogChannelConnection;
use App\Models\CatalogGoogleSheetPriceColumn;
use App\Models\CatalogPriceBook;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\GoogleSheets\GoogleSheetsClient;
use App\Services\Catalog\GoogleSheets\GoogleSheetsExporter;
use App\Services\Catalog\Pricing\CatalogChannelPriceResolver;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChannelPriceSelectionUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'app.url' => 'https://admin.laptopplus.test',
            'catalog.storefront_url' => 'https://laptopplus.test',
        ]);
    }

    public function test_single_channels_are_independent_and_google_sheets_accepts_multiple_sources(): void
    {
        $admin = $this->admin();
        $book = $this->priceBook(true);

        $this->actingAs($admin)->patch('/admin/integrations/catalog-channels/website/price', [
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
        ])->assertRedirect();
        $this->actingAs($admin)->patch('/admin/integrations/catalog-channels/google_merchant/price', [
            'price_source' => 'selected_price',
            'fallback_policy' => 'none',
        ])->assertRedirect();
        $this->actingAs($admin)->patch('/admin/integrations/catalog-channels/meta_catalog/price', [
            'price_source' => 'price_book:'.$book->id,
            'fallback_policy' => 'none',
        ])->assertRedirect();
        $this->actingAs($admin)->patch('/admin/integrations/catalog-channels/google-sheets/price-columns', [
            'sources' => ['retail_price', 'selected_price', 'price_book:'.$book->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('catalog_channel_price_settings', [
            'channel' => 'website', 'price_source' => 'retail_price', 'fallback_policy' => 'none',
        ]);
        $this->assertDatabaseHas('catalog_channel_price_settings', [
            'channel' => 'google_merchant', 'price_source' => 'selected_price',
        ]);
        $this->assertDatabaseHas('catalog_channel_price_settings', [
            'channel' => 'meta_catalog', 'price_source' => 'price_book:'.$book->id,
        ]);
        $this->assertSame(3, CatalogGoogleSheetPriceColumn::count());
        $this->assertDatabaseHas('catalog_channel_events', ['event' => 'CHANNEL_PRICE_SOURCE_UPDATED']);
        $this->assertDatabaseHas('catalog_channel_events', ['event' => 'GOOGLE_SHEETS_PRICE_COLUMNS_UPDATED']);
    }

    public function test_inactive_and_duplicate_sources_are_rejected_and_fallback_defaults_to_none(): void
    {
        $admin = $this->admin();
        $inactive = $this->priceBook(false);

        $this->actingAs($admin)
            ->patch('/admin/integrations/catalog-channels/meta_catalog/price', [
                'price_source' => 'price_book:'.$inactive->id,
                'fallback_policy' => 'none',
            ])
            ->assertSessionHasErrors('price_source');

        $this->actingAs($admin)
            ->patch('/admin/integrations/catalog-channels/google-sheets/price-columns', [
                'sources' => ['retail_price', 'retail_price'],
            ])
            ->assertSessionHasErrors(['sources.0', 'sources.1']);

        $setting = app(CatalogChannelPriceSettingsService::class)->forChannel('meta_catalog');
        $this->assertSame('none', $setting->fallback_policy);
    }

    public function test_staff_cannot_save_price_selection_and_index_exposes_matrix_props(): void
    {
        $staff = \App\Models\User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)
            ->patch('/admin/integrations/catalog-channels/website/price', [
                'price_source' => 'retail_price', 'fallback_policy' => 'none',
            ])
            ->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)
            ->get('/admin/integrations/catalog-channels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Integrations/CatalogChannels')
                ->has('priceBooks')
                ->has('priceSettings')
                ->has('googleSheetsPriceColumns'));
    }

    public function test_each_channel_resolves_its_own_source_and_google_sheets_exports_selected_columns(): void
    {
        $book = $this->priceBook(true);
        $product = $this->product();
        $product->update(['kiot_selected_price' => 200]);
        $product->catalogPrices()->create([
            'price_book_id' => $book->id,
            'price_source' => 'price_book',
            'price' => 300,
            'currency' => 'VND',
            'checksum' => str_repeat('a', 64),
            'synced_at' => now(),
        ]);

        $settings = app(CatalogChannelPriceSettingsService::class);
        $settings->update('website', 'retail_price', 'none');
        $settings->update('google_merchant', 'selected_price', 'none');
        $settings->update('meta_catalog', 'price_book:'.$book->id, 'none');
        $settings->updateGoogleSheetsSources(['retail_price', 'price_book:'.$book->id]);
        CatalogChannelConnection::where('channel', CatalogChannelConnection::GOOGLE_SHEETS)->update(['is_enabled' => true]);

        $this->assertSame(100, app(CatalogChannelPriceResolver::class)->resolve($product->fresh(), 'website')['value']);
        $this->assertSame(200, app(CatalogChannelPriceResolver::class)->resolve($product->fresh(), 'google_merchant')['value']);
        $this->assertSame(300, app(CatalogChannelPriceResolver::class)->resolve($product->fresh(), 'meta_catalog')['value']);

        $client = Mockery::mock(GoogleSheetsClient::class);
        $client->shouldReceive('readRows')->once()->with(Mockery::type('array'), Mockery::type('int'))->andReturn([]);
        $client->shouldReceive('rowRange')->andReturnUsing(
            fn (array $configuration, int $row, int $columnCount): string => "'Products'!A{$row}:AZ{$row}",
        );
        $client->shouldReceive('writeRows')->once();
        $this->app->instance(GoogleSheetsClient::class, $client);
        $report = app(GoogleSheetsExporter::class)->sync();

        $this->assertSame(1, $report['TOTAL_PRODUCTS']);
        $this->assertSame(['retail_price', 'price_book_'.$book->id], $report['SELECTED_PRICE_COLUMNS']);
    }

    private function admin(): \App\Models\User
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        foreach (['catalog-channels.view', 'catalog-channels.manage'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $admin;
    }

    private function priceBook(bool $active): CatalogPriceBook
    {
        return CatalogPriceBook::create([
            'provider' => 'kiot',
            'remote_price_book_id' => random_int(1000, 9999),
            'name' => $active ? 'Active book' : 'Inactive book',
            'code' => $active ? 'ACTIVE' : 'INACTIVE',
            'is_active' => $active,
            'currency' => 'VND',
            'synced_at' => now(),
            'checksum' => str_repeat('b', 64),
        ]);
    }

    private function product(): Product
    {
        $category = Category::create([
            'provider' => 'kiot', 'remote_category_id' => random_int(1, 100000),
            'name' => 'Laptop', 'slug' => 'laptop-'.uniqid(), 'is_active' => true,
            'show_on_pc_website' => true, 'provider_sync_status' => 'active',
        ]);
        $product = Product::create([
            'category_id' => $category->id, 'provider' => 'kiot',
            'remote_product_id' => random_int(1, 100000), 'name' => 'Laptop',
            'slug' => 'laptop-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'price' => 100,
            'stock_quantity' => 1, 'inventory_source' => 'kiot', 'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available', 'kiot_sellable' => true,
            'kiot_available_quantity' => 1, 'is_active' => true, 'show_on_pc_website' => true,
        ]);
        $product->images()->create(['url' => 'https://cdn.laptopplus.test/image.jpg', 'is_primary' => true]);

        return $product;
    }
}
