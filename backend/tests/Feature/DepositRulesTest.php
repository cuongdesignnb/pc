<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Payments\DepositPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Batch 10 đợt 4, item 11: deposit rules by province, category, product and minimum total. */
class DepositRulesTest extends TestCase
{
    use RefreshDatabase;

    private Category $laptops;

    private Category $dell;

    private Category $parts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('X-Cart-Session', 'deposit-rules-session');
        Setting::set('payment_bank_name', 'BIDV');
        Setting::set('payment_bank_account', '8602206668');
        Setting::set('payment_bank_holder', 'LAPTOP PLUS');
        Setting::set('payment_deposit_enabled', '1');
        Setting::set('payment_deposit_type', 'percent');
        Setting::set('payment_deposit_value', '10');
        Setting::set('shipping_free_threshold', '500000');
        Setting::clearCache();
        $this->laptops = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true, 'show_on_pc_website' => true]);
        $this->dell = Category::create(['name' => 'Dell', 'slug' => 'laptop-cu-dell', 'parent_id' => $this->laptops->id, 'is_active' => true, 'show_on_pc_website' => true]);
        $this->parts = Category::create(['name' => 'Linh kiện', 'slug' => 'linh-kien', 'is_active' => true, 'show_on_pc_website' => true]);
    }

    public function test_without_rules_the_deposit_is_the_general_rule_on_the_total(): void
    {
        $laptop = $this->product($this->dell, 12_345_000);
        $policy = app(DepositPolicy::class);

        $this->assertSame($policy->amountFor(12_345_000), $policy->amountForOrder(12_345_000, [$this->line($laptop)], '79'));
        $this->assertSame(1_235_000, $this->quote([$laptop], '79')['deposit']);
    }

    public function test_province_rules_none_value_and_cod(): void
    {
        $part = $this->product($this->parts, 2_000_000);
        $this->setRules(['01' => ['type' => 'none'], '79' => ['type' => 'percent', 'value' => 30], '92' => ['type' => 'fixed', 'value' => 500000, 'cod' => false]]);

        $this->assertNull($this->quote([$part], '01')['deposit']);
        $this->assertSame(600_000, $this->quote([$part], '79')['deposit']);
        $this->assertSame(200_000, $this->quote([$part], '96')['deposit']);

        $farAway = $this->quote([$part], '92');
        $this->assertSame(500_000, $farAway['deposit']);
        $cod = collect($farAway['response']->json('payment_methods'))->firstWhere('code', 'cod');
        $this->assertFalse($cod['available']);
        $this->assertNotNull($cod['disabled_reason']);

        // The order is refused too, whatever the client sends.
        $this->postJson('/api/v1/orders', $this->orderPayload($part, '92', '31150', 'cod'))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_category_rule_reaches_sub_categories_and_product_rule_wins(): void
    {
        $this->laptops->update(['deposit_rule' => ['type' => 'percent', 'value' => 20]]);
        $this->parts->update(['deposit_rule' => ['type' => 'none']]);
        $laptop = $this->product($this->dell, 10_000_000);
        $part = $this->product($this->parts, 1_000_000);

        // Laptop 20% of 10tr; the part needs no deposit.
        $this->assertSame(2_000_000, $this->quote([$laptop, $part], '79')['deposit']);

        $laptop->update(['deposit_rule' => ['type' => 'fixed', 'value' => 500000]]);
        $this->assertSame(1_000_000, $this->quote([[$laptop, 2], $part], '79')['deposit']);

        // A province set to "Không cọc" still wins over category and product.
        $this->setRules(['01' => ['type' => 'none']]);
        $this->assertNull($this->quote([$laptop], '01')['deposit']);
    }

    public function test_orders_under_the_minimum_get_no_deposit(): void
    {
        Setting::set('payment_deposit_min_total', '1000000');
        Setting::clearCache();
        $cheap = $this->product($this->parts, 900_000);

        $this->assertNull($this->quote([$cheap], '79')['deposit']);
        $this->postJson('/api/v1/orders', $this->orderPayload($cheap, '79', '27316', 'sepay', 'deposit'))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_option');
    }

    public function test_quote_and_order_save_the_same_deposit_and_kiot_note_shows_it(): void
    {
        $this->dell->update(['deposit_rule' => ['type' => 'percent', 'value' => 25]]);
        $laptop = $this->product($this->dell, 8_000_000);
        $quote = $this->quote([$laptop], '79');
        $this->assertSame(2_000_000, $quote['deposit']);

        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', '27316', 'sepay', 'deposit') + [
            'quote_id' => $quote['response']->json('quote_id'),
        ])->assertCreated()->assertJsonPath('order.deposit_amount', '2000000')
            ->assertJsonPath('payment.amount', 2_000_000);

        $order = Order::latest('id')->first();
        $payload = (fn () => $this->createPayload($order->load('items'), 0))->call(app(KiotOrderService::class));
        $this->assertStringContainsString('Đặt cọc: 2.000.000đ', $payload['note']);
        $this->assertSame((int) $order->total, $payload['totals']['total']);
    }

    public function test_admin_saves_province_rules_and_category_rule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->where('provinces', fn ($provinces) => collect($provinces)->contains('code', '01')));

        $this->actingAs($admin)->put('/admin/settings', ['settings' => [
            ['key' => 'payment_deposit_province_rules', 'value' => json_encode([
                '01' => ['type' => 'none', 'value' => 0, 'cod' => true],
                '79' => ['type' => 'percent', 'value' => 150, 'cod' => false],
                'xx' => ['type' => 'percent', 'value' => 5],
                '48' => ['type' => null, 'cod' => true],
            ])],
        ]])->assertRedirect();
        Setting::clearCache();
        $this->assertSame([
            '01' => ['type' => 'none', 'value' => 0, 'cod' => true],
            '79' => ['type' => 'percent', 'value' => 100, 'cod' => false],
        ], Setting::get('payment_deposit_province_rules'));

        $this->actingAs($admin)->put("/admin/categories/{$this->parts->id}", [
            'name' => 'Linh kiện', 'slug' => 'linh-kien', 'sort_order' => 0, 'is_active' => true,
            'deposit_rule' => ['type' => 'fixed', 'value' => 200000],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['type' => 'fixed', 'value' => 200000], $this->parts->fresh()->deposit_rule);

        $this->actingAs($admin)->put("/admin/categories/{$this->parts->id}", [
            'name' => 'Linh kiện', 'slug' => 'linh-kien', 'sort_order' => 0, 'is_active' => true,
            'deposit_rule' => ['type' => '', 'value' => ''],
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->parts->fresh()->deposit_rule);
    }

    /**
     * @param  array<int, Product|array{0: Product, 1: int}>  $products
     * @return array{deposit: int|null, response: \Illuminate\Testing\TestResponse}
     */
    private function quote(array $products, string $province): array
    {
        $wards = ['01' => '09877', '79' => '27316', '92' => '31150', '96' => '31988'];
        $items = array_map(function ($entry): array {
            [$product, $quantity] = is_array($entry) ? $entry : [$entry, 1];

            return ['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity];
        }, $products);
        $response = $this->postJson('/api/v1/checkout/quote', [
            'checkout_mode' => 'buy_now',
            'items' => $items,
            'shipping_province_code' => $province,
            'shipping_ward_code' => $wards[$province],
            'payment_method' => 'sepay',
        ])->assertOk();

        return [
            'deposit' => collect($response->json('payment_methods'))->firstWhere('code', 'sepay')['deposit_amount'] ?? null,
            'response' => $response,
        ];
    }

    private function orderPayload(Product $product, string $province, string $ward, string $method, ?string $option = null): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_province_code' => $province, 'shipping_ward_code' => $ward,
            'payment_method' => $method, 'payment_option' => $option,
        ];
    }

    private function setRules(array $rules): void
    {
        Setting::set('payment_deposit_province_rules', $rules);
        Setting::clearCache();
    }

    /** @return array{product_id: int, quantity: int, line_total: int} */
    private function line(Product $product): array
    {
        return ['product_id' => $product->id, 'quantity' => 1, 'line_total' => (int) $product->price];
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
