<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\Category;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Bắt buộc đặt cọc với đơn giao đi tỉnh": delivery outside the exempt
 * provinces pays a deposit by SePay first ("Chờ cọc"), the order reaches KIOT
 * only when the money arrives, with the deposit as one KIOT receipt.
 */
class RequiredDepositTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $sent = [];

    private int $transactionId = 7000;

    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('X-Cart-Session', 'required-deposit-session');
        config()->set('integrations.kiot', [
            'enabled' => true, 'product_sync_enabled' => false, 'order_sync_enabled' => true,
            'base_url' => 'https://kiot.test', 'client_id' => 'pc-website', 'secret' => 'test-secret',
            'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 2,
            'product_sync_limit' => 100, 'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15, 'outbox_max_attempts' => 3,
            'outbox_retry_base_seconds' => 1,
        ]);
        config()->set('services.sepay.webhook_key', 'webhook-secret');
        Bus::fake([SyncKiotProductsBySku::class]);
        Http::fake(function (Request $request) {
            $this->sent[] = ['url' => $request->url(), 'body' => json_decode($request->body(), true) ?? []];
            if (str_ends_with($request->url(), '/payments')) {
                return Http::response(['success' => true, 'data' => ['kiot_order_code' => 'DH000055', 'cash_flow_code' => 'PT1', 'amount_paid_total' => 1]], 201);
            }

            return Http::response(['success' => true, 'data' => ['kiot_order_id' => 55, 'kiot_order_code' => 'DH000055']], 201);
        });
        foreach ([
            'payment_bank_name' => 'BIDV', 'payment_bank_account' => '8602206668', 'payment_bank_holder' => 'LAPTOP PLUS',
            'payment_deposit_required' => '1', 'payment_deposit_type' => 'percent', 'payment_deposit_value' => '10',
            'shipping_free_threshold' => '500000',
        ] as $key => $value) {
            Setting::set($key, $value);
        }
        Setting::clearCache();
        $this->laptops = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true, 'show_on_pc_website' => true]);
    }

    public function test_delivery_outside_the_exempt_provinces_must_pay_the_deposit_first(): void
    {
        $laptop = $this->product(10_000_000);
        $quote = $this->quote($laptop, '79')->assertJsonPath('required_deposit', 1_000_000);
        $methods = collect($quote->json('payment_methods'))->keyBy('code');
        $this->assertFalse($methods['cod']['available']);
        $this->assertSame('Đơn giao đến tỉnh/thành này cần đặt cọc trước.', $methods['cod']['disabled_reason']);
        $this->assertTrue($methods['sepay']['deposit_required']);
        $this->assertSame(1_000_000, $methods['sepay']['deposit_amount']);

        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'cod'))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'sepay', 'deposit') + ['quote_id' => $quote->json('quote_id')])
            ->assertCreated()
            ->assertJsonPath('integration_status', 'waiting_payment')
            ->assertJsonPath('order.payment_stage', 'awaiting_deposit')
            ->assertJsonPath('order.payment_stage_label', 'Chờ cọc')
            ->assertJsonPath('payment.amount', 1_000_000)
            ->assertJsonPath('payment.purpose', 'deposit');

        $order = Order::latest('id')->first();
        $this->assertSame(['pending', 'unpaid', 'waiting_payment'], [$order->order_status, $order->payment_status, $order->kiot_sync_status]);
        $this->assertTrue($order->requires_prepayment);
        $this->assertSame(0, IntegrationOutboxEvent::count());
        $this->assertSame([], $this->sent);
    }

    public function test_hanoi_and_store_pickup_need_no_deposit_but_may_choose_one(): void
    {
        Setting::set('shipping_pickup_enabled', '1');
        Setting::clearCache();
        $laptop = $this->product(10_000_000);

        $hanoi = $this->quote($laptop, '01')->assertJsonPath('required_deposit', null);
        $methods = collect($hanoi->json('payment_methods'))->keyBy('code');
        $this->assertTrue($methods['cod']['available']);
        $this->assertFalse($methods['sepay']['deposit_required']);
        $this->assertSame(1_000_000, $methods['sepay']['deposit_amount']);
        $this->quote($laptop, '', 'pickup')->assertJsonPath('required_deposit', null)->assertJsonPath('can_place_order', true);

        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '01', 'cod'))->assertCreated();
        $this->assertSame('synced', Order::latest('id')->first()->kiot_sync_status);

        // A Hà Nội customer may still choose to pay a deposit: it waits for the money too.
        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '01', 'sepay', 'deposit'))->assertCreated()
            ->assertJsonPath('order.payment_stage', 'awaiting_deposit');
    }

    public function test_threshold_and_exempt_category(): void
    {
        Setting::set('payment_deposit_min_total', '2000000');
        Setting::clearCache();
        $cheap = $this->product(1_000_000);
        $this->quote($cheap, '79')->assertJsonPath('required_deposit', null);
        $this->postJson('/api/v1/orders', $this->orderPayload($cheap, '79', 'cod'))->assertCreated();

        $this->laptops->update(['deposit_rule' => ['type' => 'none']]);
        $laptop = $this->product(10_000_000);
        $this->quote($laptop, '79')->assertJsonPath('required_deposit', null);
        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'cod'))->assertCreated();

        // The exempt province list is edited in Cài đặt.
        Setting::set('payment_deposit_exempt_provinces', ['79']);
        $this->laptops->update(['deposit_rule' => null]);
        Setting::clearCache();
        $this->quote($laptop, '79')->assertJsonPath('required_deposit', null);
        $this->quote($laptop, '01')->assertJsonPath('required_deposit', 1_000_000);
    }

    public function test_partial_then_full_deposit_sends_the_order_and_one_receipt_to_kiot(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $order = $this->depositOrder();
        $token = $order->token;
        $order = $order->model;

        $this->webhook($order, 400_000)->assertOk();
        $order->refresh();
        $this->assertSame(['unpaid', 'waiting_payment', 400_000], [$order->payment_status, $order->kiot_sync_status, (int) $order->amount_paid]);
        $this->assertSame('awaiting_deposit', $order->paymentStage());
        $this->withHeader('X-Order-Access-Token', $token)->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('payment.amount', 600_000)->assertJsonPath('payment.purpose', 'deposit');
        $this->assertSame([], $this->sent);

        $this->webhook($order, 600_000)->assertOk()->assertJsonPath('payment_status', 'deposited');
        $order->refresh();
        $this->assertSame(['deposited', 'confirmed', 'synced'], [$order->payment_status, $order->order_status, $order->kiot_sync_status]);
        $this->assertNull($order->paymentStage());
        $this->assertSame(1_000_000, (int) $order->kiot_paid_synced_amount);

        $create = collect($this->sent)->first(fn ($request) => str_ends_with($request['url'], '/orders'));
        $this->assertStringContainsString('Đã chuyển khoản: 1.000.000đ, còn lại thu khi giao: 9.000.000đ', $create['body']['note']);
        $receipts = collect($this->sent)->filter(fn ($request) => str_ends_with($request['url'], '/payments'))->values();
        $this->assertCount(1, $receipts);
        $this->assertSame(1_000_000, $receipts[0]['body']['amount']);
        $this->assertSame(1, IntegrationOutboxEvent::where('event_type', 'order.payment')->count());
    }

    public function test_deposit_receipt_is_sent_even_with_the_auto_receipt_switch_off(): void
    {
        $order = $this->depositOrder()->model;

        $this->webhook($order, 1_000_000)->assertOk();

        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertSame([1_000_000], collect($this->sent)->filter(fn ($request) => str_ends_with($request['url'], '/payments'))->pluck('body.amount')->values()->all());
    }

    public function test_full_transfer_waits_then_reaches_kiot_paid(): void
    {
        $laptop = $this->product(10_000_000);
        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'sepay', 'full'))->assertCreated()
            ->assertJsonPath('order.payment_stage', 'awaiting_payment')
            ->assertJsonPath('order.payment_stage_label', 'Chờ thanh toán')
            ->assertJsonPath('payment.amount', 10_000_000);
        $order = Order::latest('id')->first();

        $this->webhook($order, 10_000_000)->assertOk()->assertJsonPath('payment_status', 'paid');

        $this->assertSame(['paid', 'synced'], [$order->fresh()->payment_status, $order->fresh()->kiot_sync_status]);
        $this->assertSame([10_000_000], collect($this->sent)->filter(fn ($request) => str_ends_with($request['url'], '/payments'))->pluck('body.amount')->values()->all());
    }

    public function test_switch_off_keeps_todays_behaviour(): void
    {
        Setting::set('payment_deposit_required', '0');
        Setting::set('payment_deposit_enabled', '1');
        Setting::clearCache();
        $laptop = $this->product(10_000_000);

        $this->quote($laptop, '79')->assertJsonPath('required_deposit', null);
        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'cod'))->assertCreated();
        $this->postJson('/api/v1/orders', $this->orderPayload($laptop, '79', 'sepay', 'deposit'))->assertCreated()
            ->assertJsonPath('order.payment_stage', null);

        $this->assertSame(['synced', 'synced'], Order::orderBy('id')->pluck('kiot_sync_status')->all());
        $this->assertFalse(Order::latest('id')->first()->requires_prepayment);
        $this->assertSame(0, IntegrationOutboxEvent::where('event_type', 'order.payment')->count());
    }

    public function test_waiting_orders_are_never_cancelled_and_staff_can_send_them(): void
    {
        $order = $this->depositOrder()->model;

        $this->travel(30)->days();
        $this->artisan('kiot:resend-unsynced-orders')->assertSuccessful();
        $this->artisan('kiot:retry-outbox')->assertSuccessful();
        $order->refresh();
        $this->assertSame(['pending', 'waiting_payment'], [$order->order_status, $order->kiot_sync_status]);
        $this->assertSame([], $this->sent);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/orders?status=awaiting_prepayment')->assertOk()
            ->assertInertia(fn ($page) => $page->where('orders.data.0.id', $order->id)->where('orders.data.0.payment_stage_label', 'Chờ cọc'));
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertRedirect();
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);

        // The deposit arriving afterwards still becomes one KIOT receipt.
        $this->webhook($order, 1_000_000)->assertOk()->assertJsonPath('payment_status', 'deposited');
        $this->assertSame([1_000_000], collect($this->sent)->filter(fn ($request) => str_ends_with($request['url'], '/payments'))->pluck('body.amount')->values()->all());
    }

    /** @return object{model: Order, token: string} */
    private function depositOrder(): object
    {
        $payload = $this->orderPayload($this->product(10_000_000), '79', 'sepay', 'deposit');
        $this->postJson('/api/v1/orders', $payload)->assertCreated();

        return (object) ['model' => Order::latest('id')->first(), 'token' => $payload['order_access_token']];
    }

    private function webhook(Order $order, int $amount)
    {
        return $this->withHeader('Authorization', 'Apikey webhook-secret')->postJson('/api/v1/sepay/callback', [
            'id' => ++$this->transactionId, 'gateway' => 'BIDV', 'transactionDate' => '2026-11-18 10:00:00',
            'content' => "{$order->order_number} coc", 'transferType' => 'in', 'transferAmount' => $amount,
            'referenceCode' => 'FT'.$this->transactionId,
        ]);
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
            'payment_method' => 'sepay',
        ], fn ($value) => $value !== null))->assertOk();
    }

    private function orderPayload(Product $product, string $province, string $method, ?string $option = null): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Lê Lợi', 'shipping_province_code' => $province,
            'shipping_ward_code' => ['01' => '09877', '79' => '27316'][$province],
            'payment_method' => $method, 'payment_option' => $option,
        ];
    }

    private function product(int $price): Product
    {
        return Product::create([
            'name' => 'Laptop '.Str::random(6), 'slug' => 'laptop-'.Str::lower(Str::random(10)), 'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'category_id' => $this->laptops->id, 'price' => $price, 'stock_quantity' => 20, 'is_active' => true,
            'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }
}
