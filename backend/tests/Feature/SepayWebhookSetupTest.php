<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Batch 10 đợt 4, item 12: SePay auto-confirmation configured from Cài đặt → Thanh toán. */
class SepayWebhookSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Nothing in .env: the keys come from the admin settings.
        config()->set('services.sepay.webhook_key', null);
        config()->set('services.sepay.secret_key', null);
        Setting::set('payment_bank_name', 'BIDV');
        Setting::set('payment_bank_account', '8602206668');
        Setting::set('payment_bank_holder', 'LAPTOP PLUS');
        Setting::clearCache();
    }

    public function test_unconfigured_webhook_and_ipn_say_what_is_missing(): void
    {
        $this->postJson('/api/v1/sepay/callback', ['id' => 1, 'transferAmount' => 1000, 'content' => 'DH202611170001'])
            ->assertStatus(503)->assertJsonPath('error_code', 'SEPAY_WEBHOOK_NOT_CONFIGURED');
        $this->postJson('/payment/ipn', ['notification_type' => 'ORDER_PAID'])
            ->assertStatus(503)->assertJsonPath('error_code', 'SEPAY_IPN_NOT_CONFIGURED');
    }

    public function test_key_from_settings_confirms_the_transfer_and_records_history(): void
    {
        Setting::set('sepay_webhook_api_key', 'key-from-admin');
        Setting::clearCache();
        $this->assertNotSame('key-from-admin', DB::table('settings')->where('key', 'sepay_webhook_api_key')->value('value'));
        $order = $this->order();

        $this->withHeader('Authorization', 'Apikey wrong')->postJson('/api/v1/sepay/callback', $this->transfer($order, 1))
            ->assertUnauthorized();
        $this->withHeader('Authorization', 'Apikey key-from-admin')
            ->postJson('/api/v1/sepay/callback', $this->transfer($order, 2, ['content' => strtolower($order->order_number).' thanh toan']))
            ->assertOk()->assertJsonPath('payment_status', 'paid');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame((int) $order->total, (int) $order->amount_paid);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertTrue(OrderHistory::where('order_id', $order->id)->where('actor_type', 'payment')->exists());
    }

    public function test_bank_transfer_webhook_posted_to_payment_ipn_is_handled_too(): void
    {
        Setting::set('sepay_webhook_api_key', 'key-from-admin');
        Setting::clearCache();
        $order = $this->order();

        $this->withHeader('Authorization', 'Apikey key-from-admin')->postJson('/payment/ipn', $this->transfer($order, 3))
            ->assertOk()->assertJsonPath('payment_status', 'paid');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_gateway_secret_from_settings_guards_the_ipn(): void
    {
        Setting::set('sepay_gateway_secret_key', 'gateway-secret');
        Setting::clearCache();
        $order = $this->order();

        $this->withHeader('X-Secret-Key', 'wrong')->postJson('/payment/ipn', ['notification_type' => 'ORDER_PAID'])
            ->assertUnauthorized();
        $this->withHeader('X-Secret-Key', 'gateway-secret')->postJson('/payment/ipn', [
            'notification_type' => 'ORDER_PAID',
            'order' => ['order_invoice_number' => $order->order_number, 'order_amount' => (int) $order->total],
            'transaction' => ['id' => 99, 'reference_code' => 'GW1'],
        ])->assertOk()->assertJsonPath('payment_status', 'paid');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_admin_secret_fields_keep_the_old_value_when_left_blank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('sepay_webhook_api_key', 'old-key');
        Setting::clearCache();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertInertia(fn ($page) => $page->where(
            'settings.payment',
            fn ($items) => collect($items)->firstWhere('key', 'sepay_webhook_api_key')['value'] === '********',
        ));

        foreach (['', '********'] as $blank) {
            $this->actingAs($admin)->put('/admin/settings', ['settings' => [['key' => 'sepay_webhook_api_key', 'value' => $blank]]])->assertRedirect();
            Setting::clearCache();
            $this->assertSame('old-key', Setting::get('sepay_webhook_api_key'));
        }

        $this->actingAs($admin)->put('/admin/settings', ['settings' => [
            ['key' => 'sepay_webhook_api_key', 'value' => 'new-key'],
            ['key' => 'sepay_gateway_secret_key', 'value' => 'new-secret'],
        ]])->assertRedirect();
        Setting::clearCache();
        $this->assertSame('new-key', Setting::get('sepay_webhook_api_key'));
        $this->assertSame('new-secret', Setting::get('sepay_gateway_secret_key'));
    }

    private function transfer(Order $order, int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id, 'gateway' => 'BIDV', 'transactionDate' => '2026-11-17 10:00:00', 'accountNumber' => '8602206668',
            'content' => "{$order->order_number} chuyen tien", 'transferType' => 'in',
            'transferAmount' => (int) $order->total, 'referenceCode' => 'FT'.$id,
        ], $overrides);
    }

    private function order(): Order
    {
        $product = Product::create([
            'name' => 'Laptop Dell', 'slug' => 'laptop-'.Str::lower(Str::random(6)), 'sku' => 'SKU-'.Str::random(5),
            'price' => 10_000_000, 'stock_quantity' => 5, 'is_active' => true, 'inventory_source' => 'local',
        ]);

        return app(KiotOrderService::class)->create([
            'checkout_idempotency_key' => (string) Str::uuid(), 'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'sepay', 'payment_option' => 'full',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], null)['order']->fresh();
    }
}
