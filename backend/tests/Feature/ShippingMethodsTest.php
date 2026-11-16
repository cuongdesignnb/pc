<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Orders\OrderApiPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Batch 10 đợt 4, item 14: GHTK, Ahamove, store pickup and free shipping for laptops. */
class ShippingMethodsTest extends TestCase
{
    use RefreshDatabase;

    private Category $laptops;

    private Category $parts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('X-Cart-Session', 'shipping-methods-session');
        // Charge shipping on every order so the free-shipping rule is visible.
        Setting::set('shipping_free_threshold', '0');
        Setting::set('shipping_default_fee', '30000');
        Setting::set('contact_address', "12 Thái Hà\nĐống Đa, Hà Nội");
        Setting::clearCache();
        $this->laptops = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true, 'show_on_pc_website' => true]);
        $this->parts = Category::create(['name' => 'Linh kiện', 'slug' => 'linh-kien', 'is_active' => true, 'show_on_pc_website' => true]);
    }

    public function test_only_standard_is_offered_until_the_shop_turns_methods_on(): void
    {
        $part = $this->product($this->parts, 1_000_000);

        $response = $this->quote($part, '79')->assertJsonPath('shipping_method', 'standard')
            ->assertJsonPath('summary.shipping_fee', 30000);
        $this->assertSame(['standard'], array_column($response->json('shipping_methods'), 'code'));

        $this->postJson('/api/v1/orders', $this->orderPayload($part))->assertCreated();
        $this->assertSame('standard', Order::latest('id')->first()->shipping_method);
    }

    public function test_enabled_methods_fees_and_ahamove_area(): void
    {
        $this->enable(['ghtk', 'ahamove', 'pickup'], standard: false);
        Setting::set('shipping_ghtk_use_standard_fee', '0');
        Setting::set('shipping_ghtk_fee', '25000');
        Setting::clearCache();
        $part = $this->product($this->parts, 1_000_000);

        // Older clients send "standard": it now means the first enabled delivery method.
        $response = $this->quote($part, '79', 'standard')->assertJsonPath('shipping_method', 'ghtk')
            ->assertJsonPath('summary.shipping_fee', 25000);
        $methods = collect($response->json('shipping_methods'))->keyBy('code');
        $this->assertSame(['ghtk', 'ahamove', 'pickup'], $methods->keys()->all());
        $this->assertFalse($methods['ahamove']['available']);
        $this->assertStringContainsString('Hà Nội', $methods['ahamove']['disabled_reason']);
        $this->assertSame(0, $methods['pickup']['fee']);
        $this->assertFalse($methods['pickup']['requires_address']);
        $this->assertSame('12 Thái Hà, Đống Đa, Hà Nội', $methods['pickup']['pickup_address']);

        $this->quote($part, '01', 'ahamove')->assertJsonPath('summary.shipping_fee', 50000)->assertJsonPath('can_place_order', true);
        $this->quote($part, '79', 'ahamove')->assertJsonPath('can_place_order', false)
            ->assertJsonPath('issues.0.code', 'SHIPPING_METHOD_UNAVAILABLE');
        $this->postJson('/api/v1/orders', $this->orderPayload($part, ['shipping_method' => 'ahamove']))
            ->assertUnprocessable()->assertJsonValidationErrors('shipping_method');
    }

    public function test_laptops_from_the_set_amount_ship_free(): void
    {
        $this->enable(['ghtk', 'ahamove']);
        Setting::set('shipping_free_laptop_min_subtotal', '5000000');
        Setting::clearCache();
        $cheapLaptop = $this->product($this->laptops, 4_000_000);
        $laptop = $this->product($this->laptops, 5_000_000);
        $part = $this->product($this->parts, 6_000_000);

        $this->quote($cheapLaptop, '01', 'ghtk')->assertJsonPath('summary.shipping_fee', 30000);
        $this->quote($part, '01', 'ghtk')->assertJsonPath('summary.shipping_fee', 30000);
        $free = $this->quote($laptop, '01', 'ahamove')->assertJsonPath('summary.shipping_fee', 0);
        $this->assertSame([0, 0, 0], array_column($free->json('shipping_methods'), 'fee'));

        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, [
            'shipping_method' => 'ahamove', 'shipping_province_code' => '01', 'shipping_ward_code' => '09877',
            'quote_id' => $free->json('quote_id'),
        ]))->assertCreated()->assertJsonPath('order.shipping_method', 'ahamove')->assertJsonPath('order.shipping_fee', '0');
    }

    public function test_store_pickup_needs_no_address_and_reaches_kiot_admin_and_api(): void
    {
        $this->enable(['pickup']);
        $part = $this->product($this->parts, 1_000_000);
        $quote = $this->quote($part, '', 'pickup')->assertJsonPath('summary.shipping_fee', 0)
            ->assertJsonPath('summary.total', 1_000_000)->assertJsonPath('can_place_order', true);

        $payload = $this->orderPayload($part, ['shipping_method' => 'pickup', 'quote_id' => $quote->json('quote_id')]);
        unset($payload['shipping_address'], $payload['shipping_province_code'], $payload['shipping_ward_code']);
        $this->postJson('/api/v1/orders', $payload)->assertCreated()
            ->assertJsonPath('order.shipping_method', 'pickup')
            ->assertJsonPath('order.shipping_method_label', 'Nhận tại cửa hàng');

        $order = Order::latest('id')->first();
        $this->assertSame(0, (int) $order->shipping_fee);
        $this->assertSame('Nhận tại cửa hàng: 12 Thái Hà, Đống Đa, Hà Nội', $order->shipping_address);
        $this->assertNull($order->shipping_city);

        $kiot = (fn () => $this->createPayload($order->load('items'), 0))->call(app(KiotOrderService::class));
        $this->assertFalse($kiot['delivery']['is_delivery']);
        $this->assertSame('pickup', $kiot['delivery']['method']);
        $this->assertStringContainsString('Nhận hàng: Nhận tại cửa hàng - miễn phí ship', $kiot['note']);

        $api = app(OrderApiPresenter::class)->present($order);
        $this->assertSame(['pickup', 'Nhận tại cửa hàng'], [$api['shipping_method'], $api['shipping_method_label']]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get("/admin/orders/{$order->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.shipping_method_label', 'Nhận tại cửa hàng'));
    }

    public function test_delivery_still_requires_an_address(): void
    {
        $this->enable(['pickup']);
        $part = $this->product($this->parts, 1_000_000);
        $payload = $this->orderPayload($part);
        unset($payload['shipping_address']);

        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('shipping_address');
    }

    public function test_admin_saves_the_ahamove_area(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put('/admin/settings', ['settings' => [
            ['key' => 'shipping_ahamove_province_codes', 'value' => json_encode(['01', '79', '01', 'x', ''])],
        ]])->assertRedirect();
        Setting::clearCache();

        $this->assertSame(['01', '79'], Setting::get('shipping_ahamove_province_codes'));
    }

    private function enable(array $methods, bool $standard = true): void
    {
        foreach ($methods as $method) {
            Setting::set("shipping_{$method}_enabled", '1');
        }
        Setting::set('shipping_standard_enabled', $standard ? '1' : '0');
        Setting::clearCache();
    }

    private function quote(Product $product, string $province, ?string $method = null)
    {
        $wards = ['01' => '09877', '79' => '27316', '' => ''];

        return $this->postJson('/api/v1/checkout/quote', array_filter([
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
            'shipping_province_code' => $province,
            'shipping_ward_code' => $wards[$province],
            'shipping_method' => $method,
            'payment_method' => 'cod',
        ], fn ($value) => $value !== null))->assertOk();
    }

    private function orderPayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_province_code' => '79', 'shipping_ward_code' => '27316',
            'payment_method' => 'cod',
        ], $overrides);
    }

    private function product(Category $category, int $price): Product
    {
        return Product::create([
            'name' => 'SP '.Str::random(6), 'slug' => 'sp-'.Str::lower(Str::random(10)), 'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'category_id' => $category->id, 'price' => $price, 'stock_quantity' => 10, 'is_active' => true,
            'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }
}
