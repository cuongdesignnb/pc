<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationSyncRun;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LaptopPlusBatchSevenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['catalog.storefront_url' => 'https://laptopplus.test']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true, 'show_on_pc_website' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Chuột Logitech', 'slug' => 'chuot-'.Str::lower(Str::random(6)), 'sku' => 'WEB-NEW-'.Str::upper(Str::random(5)),
            'category_id' => $this->category->id, 'price' => 500_000, 'stock_quantity' => 4,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    public function test_admin_api_sets_prices_only_on_web_new_local_products(): void
    {
        $web = $this->product();
        $kiot = $this->product(['sku' => 'WEB-NEW-KIOT', 'inventory_source' => 'kiot', 'provider' => 'kiot']);
        $other = $this->product(['sku' => 'D7420']);
        $token = app(AdminApiTokens::class)->issue($this->admin, 'n8n', 'content')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$web->id}", [
            'price' => 650_000, 'sale_price' => 590_000,
            'specifications' => ['Kết nối' => 'Bluetooth', 'DPI' => '4000'],
        ])->assertOk()
            ->assertJsonPath('data.price', 650000)
            ->assertJsonPath('data.sale_price', 590000)
            ->assertJsonPath('data.price_editable', true)
            ->assertJsonPath('data.specifications.0', ['label' => 'Kết nối', 'value' => 'Bluetooth']);

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$web->id}", ['sale_price' => 700_000])
            ->assertUnprocessable()->assertJsonValidationErrors('sale_price');
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$web->id}", ['sale_price' => null])
            ->assertOk()->assertJsonPath('data.sale_price', null);

        foreach ([$kiot, $other] as $locked) {
            $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$locked->id}", ['price' => 1])
                ->assertUnprocessable()->assertJsonValidationErrors('price');
            $this->assertSame(500000, (int) $locked->fresh()->price);
        }
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$web->id}", ['stock_quantity' => 9])->assertUnprocessable();
    }

    public function test_web_new_local_products_are_projected_into_catalog_feeds(): void
    {
        $web = $this->product(['sale_price' => 450_000]);
        $web->images()->create(['url' => 'https://cdn.laptopplus.test/chuot.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $this->product(['sku' => 'LOCAL-OTHER']);

        $service = app(CatalogProductProjectionService::class);
        $ids = $service->query()->pluck('id')->all();
        $this->assertSame([$web->id], $ids);

        $projection = $service->project($web->fresh());
        $this->assertTrue($projection->isVisible);
        $this->assertTrue($projection->isSellable);
        $this->assertSame(4, $projection->inventory);
        $this->assertSame('in_stock', $projection->availability);
        $this->assertSame(500000, $projection->price);
        $this->assertSame(450000, $projection->salePrice);
        $this->assertSame(500000, $projection->selectedPrice);
    }

    public function test_order_keeps_utm_and_video_code_and_sends_them_in_the_kiot_note(): void
    {
        $product = $this->product();

        $this->withHeader('X-Cart-Session', 'utm-'.Str::random(6))->postJson('/api/v1/orders', [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Nguyễn Văn B', 'customer_email' => 'b@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
            'shipping_province_code' => '79', 'shipping_ward_code' => '27316',
            'notes' => 'Giao giờ hành chính',
            'utm_source' => 'tiktok', 'utm_campaign' => 'thang11', 'utm_content' => 'review-dell', 'video_code' => 'V123',
        ])->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertSame(['tiktok', 'thang11', 'review-dell', 'V123'], [$order->utm_source, $order->utm_campaign, $order->utm_content, $order->video_code]);

        $payload = (fn () => $this->createPayload($order->load('items'), 0))->call(app(KiotOrderService::class));
        $this->assertStringStartsWith('Giao giờ hành chính', $payload['note']);
        $this->assertStringContainsString("Nguồn: Website {$order->order_number} (utm_source=tiktok, utm_campaign=thang11, utm_content=review-dell)", $payload['note']);
        $this->assertStringContainsString('Mã video: V123', $payload['note']);
        // KIOT picks the warranty package lasting this many months.
        $this->assertSame($order->items->first()->warranty_months, $payload['items'][0]['warranty_months']);

        $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.utm_source', 'tiktok')->where('order.video_code', 'V123'));
    }

    public function test_kiot_stale_count_trusts_a_recent_successful_incremental_run(): void
    {
        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']));
        $this->product(['inventory_source' => 'kiot', 'provider' => 'kiot', 'sku' => 'K1', 'kiot_synced_at' => now()->subDay()]);
        $this->product(['inventory_source' => 'kiot', 'provider' => 'kiot', 'sku' => 'K2', 'kiot_synced_at' => null]);
        $stale = fn () => $this->actingAs($this->admin)->get('/admin/integrations/kiot')
            ->assertOk()->viewData('page')['props']['counts']['products_stale'];

        $this->assertSame(2, $stale());

        IntegrationSyncRun::create([
            'provider' => 'kiot', 'resource' => 'products', 'mode' => 'incremental',
            'status' => 'completed', 'started_at' => now()->subMinute(), 'completed_at' => now(),
        ]);
        // Only the product never seen from KIOT is still stale.
        $this->assertSame(1, $stale());

        $this->travel(1)->hours();
        $this->assertSame(2, $stale());
    }

    public function test_zalo_mini_app_may_read_products_without_credentials(): void
    {
        $product = $this->product();
        $origin = 'https://h5.zdn.vn';

        $this->withHeader('Origin', $origin)->getJson('/api/v1/products')->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->withHeader('Origin', 'https://mini.zalo.me')->getJson("/api/v1/products/{$product->slug}")
            ->assertHeader('Access-Control-Allow-Origin', 'https://mini.zalo.me');
        $this->call('OPTIONS', '/api/v1/products', [], [], [], [
            'HTTP_ORIGIN' => 'https://abc.zdn.vn', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://abc.zdn.vn');

        // Other routes and look-alike origins are not opened.
        $this->withHeader('Origin', $origin)->getJson('/api/v1/settings')->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->withHeader('Origin', 'https://zdn.vn.evil.com')->getJson('/api/v1/products')->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_public_settings_expose_topbar_text_address_parts_and_sepay_state(): void
    {
        config(['services.sepay.bank_code' => null, 'services.sepay.bank_account' => null, 'services.sepay.account_name' => null]);
        Setting::set('storefront_topbar_text', 'Thu cũ đổi mới – trợ giá 500k');
        Setting::set('payment_footer_extra', 'Hỗ trợ trả góp');

        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('storefront_topbar_text', 'Thu cũ đổi mới – trợ giá 500k')
            ->assertJsonPath('contact_address_locality', 'Phường Xuân Phương')
            ->assertJsonPath('contact_address_region', 'Hà Nội')
            ->assertJsonPath('payment_footer_extra', 'Hỗ trợ trả góp')
            ->assertJsonPath('payment_sepay_enabled', false);
    }

    public function test_product_video_has_an_upload_date_for_video_schema(): void
    {
        $product = $this->product();
        $product->forceFill(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])->save();

        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.video.id', 'dQw4w9WgXcQ')
            ->assertJsonPath('product.video.upload_date', $product->created_at->toIso8601String());
    }
}
