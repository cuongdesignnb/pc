<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogChannelSyncRun;
use App\Models\CatalogChannelSyncRunItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CatalogProductSelectionPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
            'app.url' => 'https://admin.laptopplus.test',
            'catalog.storefront_url' => 'https://laptopplus.test',
        ]);
    }

    public function test_selection_filters_page_select_all_exclusion_and_preview_are_backend_computed(): void
    {
        $admin = $this->admin(['catalog_channels.view', 'catalog_channels.preview', 'catalog_channels.sync', 'catalog_channels.bulk_manage', 'catalog_channels.export_validation']);
        $first = $this->product('SKU-ONE', 'https://cdn.laptopplus.test/one.jpg', 100);
        $second = $this->product('SKU-TWO', '', 0);

        $page = $this->actingAs($admin)->getJson('/admin/integrations/catalog-products?channel=meta_catalog&per_page=1');
        $page->assertOk()->assertJsonPath('data.0.sku', $first->sku)->assertJsonPath('next_cursor', $first->id);

        $preview = $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/preview', [
            'channel' => 'meta_catalog',
            'selection' => [
                'mode' => 'filtered',
                'filters' => ['price_status' => 'zero'],
                'excluded_product_ids' => [],
            ],
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
        ]);
        $preview->assertOk()
            ->assertJsonPath('summary.SELECTED_COUNT', 1)
            ->assertJsonPath('summary.ELIGIBLE_COUNT', 0)
            ->assertJsonPath('summary.IMAGE_MISSING_COUNT', 1)
            ->assertJsonPath('items.0.image_status', 'missing')
            ->assertJsonPath('items.0.action', 'INVALID');
        $this->assertDatabaseHas('catalog_channel_events', ['event' => 'CATALOG_SELECTION_PREVIEWED']);

        $all = $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/preview', [
            'channel' => 'google_sheets',
            'selection' => ['mode' => 'filtered', 'filters' => [], 'excluded_product_ids' => [$second->id]],
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
        ]);
        $all->assertOk()->assertJsonPath('summary.SELECTED_COUNT', 1)->assertJsonPath('summary.ELIGIBLE_COUNT', 1);
    }

    public function test_google_sheets_accepts_invalid_rows_while_merchant_and_meta_block_them(): void
    {
        $admin = $this->admin(['catalog_channels.preview', 'catalog_channels.sync']);
        $product = $this->product('SKU-INVALID', '', 0);
        $payload = [
            'selection' => ['mode' => 'ids', 'product_ids' => [$product->id]],
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
        ];

        $sheets = $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/preview', $payload + ['channel' => 'google_sheets']);
        $sheets->assertOk()->assertJsonPath('summary.INVALID_ROWS', 1)->assertJsonPath('summary.ELIGIBLE_COUNT', 1);

        $merchant = $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/preview', $payload + ['channel' => 'google_merchant']);
        $merchant->assertOk()->assertJsonPath('summary.INVALID_ROWS', 1)->assertJsonPath('summary.ELIGIBLE_COUNT', 0);

        $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/sync', $payload + [
            'channel' => 'google_merchant', 'confirmed' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('eligible_count');
    }

    public function test_confirmed_google_sheets_bulk_sync_submits_selected_products(): void
    {
        $admin = $this->admin(['catalog_channels.preview', 'catalog_channels.sync']);
        $product = $this->product('SKU-SHEET', 'https://cdn.laptopplus.test/sheet.jpg', 120);
        $run = CatalogChannelSyncRun::create([
            'channel' => 'google_sheets', 'mode' => 'bulk_sync', 'status' => 'completed',
            'started_at' => now(), 'completed_at' => now(), 'items_total' => 1,
        ]);
        $exporter = Mockery::mock(\App\Services\Catalog\GoogleSheets\GoogleSheetsExporter::class);
        $exporter->shouldReceive('syncSelection')->once()->andReturn(['run_id' => $run->id]);
        $this->app->instance(\App\Services\Catalog\GoogleSheets\GoogleSheetsExporter::class, $exporter);
        $response = $this->actingAs($admin)->postJson('/admin/integrations/catalog-products/sync', [
            'channel' => 'google_sheets',
            'selection' => ['mode' => 'ids', 'product_ids' => [$product->id]],
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
            'confirmed' => true,
        ]);

        $response->assertOk()->assertJsonPath('remote_submitted', true)->assertJsonPath('summary.SELECTED_COUNT', 1);
        $this->assertDatabaseCount('catalog_channel_sync_run_items', 1);
        $this->assertInstanceOf(CatalogChannelSyncRunItem::class, CatalogChannelSyncRunItem::first());
        $this->assertDatabaseHas('catalog_channel_sync_run_items', ['result_status' => 'submitted']);
        $this->assertDatabaseHas('catalog_channel_events', ['event' => 'CATALOG_BULK_SYNC_REQUESTED']);
    }

    public function test_staff_is_denied_and_manager_can_preview_and_export(): void
    {
        $staff = \App\Models\User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->getJson('/admin/integrations/catalog-products')->assertForbidden();

        $manager = $this->admin(['catalog_channels.preview', 'catalog_channels.export_validation']);
        $product = $this->product('SKU-MANAGER', 'https://cdn.laptopplus.test/manager.jpg', 100);
        $payload = [
            'channel' => 'meta_catalog',
            'selection' => ['mode' => 'ids', 'product_ids' => [$product->id]],
            'price_source' => 'retail_price',
            'fallback_policy' => 'none',
        ];
        $this->actingAs($manager)->postJson('/admin/integrations/catalog-products/preview', $payload)->assertOk();
        $this->actingAs($manager)->postJson('/admin/integrations/catalog-products/export-validation', $payload)->assertOk();
        $this->actingAs($manager)->postJson('/admin/integrations/catalog-products/sync', $payload + ['confirmed' => true])->assertForbidden();
    }

    private function admin(array $permissions): \App\Models\User
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $admin;
    }

    private function product(string $sku, string $image, int $price): Product
    {
        $category = Category::create([
            'provider' => 'kiot', 'remote_category_id' => random_int(1, 100000), 'name' => 'Laptop',
            'slug' => 'laptop-'.uniqid(), 'is_active' => true, 'show_on_pc_website' => true,
            'provider_sync_status' => 'active',
        ]);
        $product = Product::create([
            'category_id' => $category->id, 'provider' => 'kiot', 'remote_product_id' => random_int(1, 100000),
            'name' => $sku, 'slug' => strtolower($sku), 'sku' => $sku, 'price' => $price, 'stock_quantity' => 1,
            'inventory_source' => 'kiot', 'kiot_sync_status' => 'active', 'kiot_availability_status' => 'available',
            'kiot_sellable' => true, 'kiot_available_quantity' => 1, 'is_active' => true, 'show_on_pc_website' => true,
        ]);
        if ($image !== '') {
            $product->images()->create(['url' => $image, 'is_primary' => true]);
        }

        return $product;
    }
}
