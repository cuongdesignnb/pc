<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Laptopplus 25/11: promotions, warranty labels, footer link, category SEO, KIOT products to fix. */
class StorefrontBatchNovemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotions_typed_on_a_category_show_on_its_products_and_a_product_can_override_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $laptop = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);
        $dell = Category::create(['name' => 'Dell', 'slug' => 'dell', 'is_active' => true, 'parent_id' => $laptop->id]);

        $this->actingAs($admin)->put("/admin/categories/{$laptop->id}", [
            'name' => 'Laptop', 'slug' => 'laptop', 'sort_order' => 0,
            'promotion_text' => "- Tặng túi chống sốc\n\n• Tặng chuột không dây\r\n<b>Tặng bàn di chuột</b>",
        ])->assertSessionHasNoErrors();
        $this->assertSame("Tặng túi chống sốc\nTặng chuột không dây\nTặng bàn di chuột", $laptop->fresh()->promotion_text);

        $product = $this->product($dell);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('product.promotions', ['Tặng túi chống sốc', 'Tặng chuột không dây', 'Tặng bàn di chuột']);

        $product->update(['promotion_text' => 'Giảm 200.000đ khi mua kèm RAM']);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('product.promotions', ['Giảm 200.000đ khi mua kèm RAM']);

        $other = $this->product(Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true]));
        $this->getJson('/api/v1/products/'.$other->slug)->assertOk()->assertJsonPath('product.promotions', []);
    }

    public function test_warranty_package_label_is_saved_and_returned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Laptop', 'slug' => 'laptop', 'sort_order' => 0,
            'warranty_packages' => [['months' => 12, 'price' => 400000, 'label' => '  BH vàng 12 tháng '], ['months' => 6, 'price' => 0, 'label' => '']],
        ])->assertSessionHasNoErrors();
        $this->assertEquals([['months' => 6, 'price' => 0], ['months' => 12, 'price' => 400000, 'label' => 'BH vàng 12 tháng']], $category->fresh()->warranty_packages);

        $product = $this->product($category);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('product.warranty_packages.0.label', null)
            ->assertJsonPath('product.warranty_packages.1.label', 'BH vàng 12 tháng')
            ->assertJsonPath('product.warranty_packages.1.price', 400000);
    }

    public function test_footer_installment_line_links_to_the_installment_page(): void
    {
        $migration = require database_path('migrations/2026_11_25_000001_add_promotions_category_seo_and_footer_link.php');
        $migration->up();

        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('payment_footer_extra_url', '/mua-laptop-tra-gop');
    }

    public function test_category_seo_fields_and_plain_text_description(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'Linh kiện', 'slug' => 'linh-kien', 'is_active' => true, 'show_on_pc_website' => true,
            'description' => '<p>Linh kiện laptop&nbsp;chính hãng.</p><p>Bảo hành <strong>12 tháng</strong>.</p>',
        ]);

        $this->getJson('/api/v1/categories/linh-kien')->assertOk()
            ->assertJsonPath('category.seo.title', null)
            ->assertJsonPath('category.seo.description', 'Linh kiện laptop chính hãng. Bảo hành 12 tháng.')
            ->assertJsonPath('category.seo.robots', 'index,follow');

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Linh kiện', 'slug' => 'linh-kien', 'sort_order' => 0,
            'meta_robots' => 'index', 'canonical_url' => '',
        ])->assertSessionHasErrors('meta_robots');

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Linh kiện', 'slug' => 'linh-kien', 'sort_order' => 0, 'show_on_pc_website' => true, 'is_active' => true,
            'description' => $category->description,
            'meta_title' => 'Linh kiện laptop giá tốt', 'meta_description' => 'Mua linh kiện laptop',
            'meta_robots' => 'noindex,follow', 'canonical_url' => 'https://laptopplus.vn/linh-kien-laptop',
        ])->assertSessionHasNoErrors();

        $this->getJson('/api/v1/categories/linh-kien')->assertOk()
            ->assertJsonPath('category.seo.title', 'Linh kiện laptop giá tốt')
            ->assertJsonPath('category.seo.description', 'Mua linh kiện laptop')
            ->assertJsonPath('category.seo.robots', 'noindex,follow')
            ->assertJsonPath('category.seo.canonical_url', 'https://laptopplus.vn/linh-kien-laptop');
    }

    public function test_kiot_page_lists_products_to_fix_and_resends_them(): void
    {
        config()->set('integrations.kiot', [
            'enabled' => true, 'product_sync_enabled' => true, 'order_sync_enabled' => false,
            'base_url' => 'https://kiot.test', 'client_id' => 'pc-website', 'secret' => 'test-secret',
            'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 2,
            'product_sync_limit' => 100, 'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15, 'outbox_max_attempts' => 3, 'outbox_retry_base_seconds' => 1,
        ]);
        Bus::fake([SyncKiotProductsBySku::class]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->givePermissionTo(Permission::create(['name' => 'settings.view', 'guard_name' => 'web']));
        $admin->givePermissionTo(Permission::create(['name' => 'settings.edit', 'guard_name' => 'web']));
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);

        $this->product($category, ['sku' => 'KV-ERR', 'inventory_source' => 'kiot', 'kiot_synced_at' => now(), 'kiot_sync_error_code' => 'NOT_FOUND', 'kiot_sync_error_message' => 'Không thấy mã']);
        $this->product($category, ['sku' => 'KV-NEW', 'inventory_source' => 'kiot', 'kiot_synced_at' => null]);
        $this->product($category, ['sku' => 'KV-OK', 'inventory_source' => 'kiot', 'kiot_synced_at' => now()]);
        $this->product($category, ['sku' => 'WEB-NEW-1', 'inventory_source' => 'local', 'kiot_code' => null]);
        $this->product($category, ['sku' => 'WEB-NEW-2', 'inventory_source' => 'local', 'kiot_code' => 'KV-123']);

        $this->actingAs($admin)->get('/admin/integrations/kiot')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('attentionProducts', 3)
                ->where('attentionProducts', fn ($rows) => collect($rows)->pluck('reason', 'sku')->all() === ['KV-ERR' => 'error', 'KV-NEW' => 'stale', 'WEB-NEW-1' => 'unlinked']));

        $this->actingAs($admin)->post('/admin/integrations/kiot/sync-many', ['skus' => ['KV-ERR', 'KV-NEW', 'KV-NEW']])
            ->assertRedirect()->assertSessionHas('success');
        Bus::assertDispatched(SyncKiotProductsBySku::class, fn (SyncKiotProductsBySku $job) => $job->skus === ['KV-ERR', 'KV-NEW']);

        $this->actingAs($admin)->post('/admin/integrations/kiot/sync-many', ['skus' => []])->assertSessionHasErrors('skus');
    }

    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Dell Latitude '.Str::random(4), 'slug' => 'dell-'.Str::lower(Str::random(8)), 'sku' => 'SKU-'.Str::random(6),
            'category_id' => $category->id, 'price' => 5_000_000, 'stock_quantity' => 5, 'warranty_months' => 6,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ], $overrides));
    }
}
