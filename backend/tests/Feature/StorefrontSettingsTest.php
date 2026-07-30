<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);
    }

    public function test_public_settings_endpoint_exposes_public_values_only(): void
    {
        $this->setting('site_name', 'HPCOM Việt Nam');
        $this->setting('google_analytics_id', 'G-ABC123');
        $this->setting('chatgpt_api_key', 'secret', isPublic: false);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('site_name', 'HPCOM Việt Nam')
            ->assertJsonPath('google_analytics_id', 'G-ABC123')
            ->assertJsonMissingPath('chatgpt_api_key');
    }

    public function test_order_totals_use_shipping_settings(): void
    {
        $this->setting('shipping_free_threshold', '2000', 'number');
        $this->setting('shipping_default_fee', '150', 'number');
        $product = $this->product(['price' => 1000]);

        $charged = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null)['order'];

        $this->assertSame('150', $charged->shipping_fee);
        $this->assertSame('1150', $charged->total);

        $free = app(KiotOrderService::class)->create(
            $this->checkoutPayload($product->id, quantity: 2),
            null,
        )['order'];

        $this->assertSame('0', $free->shipping_fee);
        $this->assertSame('2000', $free->total);
    }

    public function test_homepage_product_limit_uses_setting(): void
    {
        $this->setting('homepage_products_per_section', '2', 'number');
        $category = Category::create([
            'name' => 'Máy tính',
            'slug' => 'may-tinh',
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
        $this->product(['category_id' => $category->id]);
        $this->product(['category_id' => $category->id]);
        $this->product(['category_id' => $category->id]);

        $this->getJson('/api/v1/categories/homepage-sections')
            ->assertOk()
            ->assertJsonCount(2, '0.products');
    }

    public function test_disabled_cod_is_rejected_by_checkout(): void
    {
        $this->setting('payment_cod_enabled', '0', 'boolean');
        $product = $this->product();

        $this->postJson('/api/v1/orders', $this->checkoutPayload($product->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_payment_response_uses_bank_settings(): void
    {
        $this->setting('payment_bank_name', 'BIDV');
        $this->setting('payment_bank_account', '8602206668');
        $this->setting('payment_bank_holder', 'HPCOM VIET NAM');
        $product = $this->product();
        $payload = $this->checkoutPayload($product->id, paymentMethod: 'sepay');

        $order = app(KiotOrderService::class)->create($payload, null)['order'];
        $order->update(['kiot_sync_status' => 'synced']);

        $this->withHeader('X-Order-Access-Token', $payload['order_access_token'])
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('payment.bank_code', 'BIDV')
            ->assertJsonPath('payment.bank_account', '8602206668')
            ->assertJsonPath('payment.account_name', 'HPCOM VIET NAM');
    }

    private function setting(string $key, string $value, string $type = 'text', bool $isPublic = true): Setting
    {
        $setting = Setting::updateOrCreate(['key' => $key], [
            'value' => $value,
            'group' => 'test',
            'type' => $type,
            'label' => $key,
            'is_public' => $isPublic,
        ]);
        Setting::clearCache();

        return $setting;
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test product',
            'slug' => 'test-'.Str::lower(Str::random(8)),
            'sku' => 'SKU-'.Str::random(5),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'is_featured' => false,
            'inventory_source' => 'local',
            'kiot_sellable' => false,
        ], $overrides));
    }

    private function checkoutPayload(int $productId, int $quantity = 1, string $paymentMethod = 'cod'): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'Hà Nội',
            'payment_method' => $paymentMethod,
            'items' => [['product_id' => $productId, 'quantity' => $quantity]],
        ];
    }
}
