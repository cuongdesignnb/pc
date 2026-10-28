<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SepayDepositPaymentTest extends TestCase
{
    use RefreshDatabase;

    private int $transactionId = 5000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.sepay.webhook_key', 'webhook-secret');
        Setting::set('payment_bank_name', 'BIDV');
        Setting::set('payment_bank_account', '8602206668');
        Setting::set('payment_bank_holder', 'LAPTOP PLUS');
        Setting::set('payment_deposit_enabled', '1');
        Setting::set('payment_deposit_type', 'percent');
        Setting::set('payment_deposit_value', '10');
        Setting::clearCache();
    }

    public function test_deposit_then_remaining_transfer_is_recorded_on_the_order(): void
    {
        [$order, $token] = $this->order('deposit');
        $deposit = (int) ceil((int) $order->total * 0.1 / 1000) * 1000;

        $this->assertSame('deposit', $order->payment_option);
        $this->assertSame($deposit, (int) $order->deposit_amount);
        $this->withHeader('X-Order-Access-Token', $token)->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('payment.amount', $deposit)
            ->assertJsonPath('payment.purpose', 'deposit');

        $this->webhook($order, $deposit)->assertOk()->assertJsonPath('payment_status', 'deposited');
        $order->refresh();
        $this->assertSame('deposited', $order->payment_status);
        $this->assertSame('confirmed', $order->order_status);
        $this->assertSame($deposit, (int) $order->amount_paid);
        $this->assertNotNull($order->deposit_paid_at);
        $this->assertNull($order->paid_at);

        $remaining = (int) $order->total - $deposit;
        $this->withHeader('X-Order-Access-Token', $token)->getJson("/api/v1/orders/{$order->id}")
            ->assertJsonPath('can_pay', true)
            ->assertJsonPath('remaining_amount', $remaining)
            ->assertJsonPath('payment.amount', $remaining)
            ->assertJsonPath('payment.purpose', 'remaining');

        $this->webhook($order, $remaining)->assertOk()->assertJsonPath('payment_status', 'paid');
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame((int) $order->total, (int) $order->amount_paid);
        $this->assertSame(['DEPOSIT', 'REMAINDER_PAID'], Transaction::orderBy('id')->pluck('content')->all());
    }

    public function test_full_transfer_marks_the_order_paid(): void
    {
        [$order] = $this->order('full');

        $this->assertNull($order->deposit_amount);
        $this->webhook($order, (int) $order->total)->assertOk()->assertJsonPath('payment_status', 'paid');

        $order->refresh();
        $this->assertSame('full', $order->payment_option);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('ORDER_PAID', Transaction::first()->content);
    }

    public function test_short_transfer_is_kept_but_does_not_change_the_status(): void
    {
        [$order] = $this->order('full');

        $this->webhook($order, 50000)->assertOk();

        $order->refresh();
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(50000, (int) $order->amount_paid);
        $this->assertDatabaseHas('sepay_payment_events', ['order_id' => $order->id, 'failure_code' => 'BELOW_REQUIRED_AMOUNT']);
    }

    public function test_outgoing_and_repeated_transfers_are_not_counted_twice(): void
    {
        [$order] = $this->order('full');

        $this->webhook($order, (int) $order->total, ['transferType' => 'out'])->assertOk();
        $this->assertSame(0, (int) $order->fresh()->amount_paid);

        $this->webhook($order, (int) $order->total, ['id' => 777])->assertOk();
        $this->webhook($order, (int) $order->total, ['id' => 777])->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame((int) $order->total, (int) $order->fresh()->amount_paid);
        $this->assertSame(1, Transaction::count());
        $this->assertSame('FT123', Transaction::first()->reference_code);
    }

    public function test_deposit_is_offered_in_the_quote_only_when_enabled(): void
    {
        $this->assertNull($this->sepayMethod(null)['deposit_amount'] ?? null);
        $this->assertSame(1_000_000, $this->sepayMethod(10_000_000)['deposit_amount']);

        Setting::set('payment_deposit_type', 'fixed');
        Setting::set('payment_deposit_value', '500000');
        Setting::clearCache();
        $this->assertSame(500_000, $this->sepayMethod(10_000_000)['deposit_amount']);
        $this->assertNull($this->sepayMethod(400_000)['deposit_amount']);

        Setting::set('payment_deposit_enabled', '0');
        Setting::clearCache();
        $this->assertNull($this->sepayMethod(10_000_000)['deposit_amount']);
        $this->expectException(ValidationException::class);
        $this->order('deposit');
    }

    public function test_admin_sees_deposit_details_and_can_mark_deposited(): void
    {
        [$order] = $this->order('deposit');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")->assertOk();
        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/payment-status", ['payment_status' => 'deposited'])
            ->assertSessionHasNoErrors();
        $this->assertSame('deposited', $order->fresh()->payment_status);

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/payment-status", ['payment_status' => 'paid']);
        $this->assertSame((int) $order->total, (int) $order->fresh()->amount_paid);
    }

    /** @return array{0: Order, 1: string} */
    private function order(string $option): array
    {
        $product = Product::create([
            'name' => 'Laptop Dell', 'slug' => 'laptop-'.Str::lower(Str::random(6)), 'sku' => 'SKU-'.Str::random(5),
            'price' => 10_000_000, 'stock_quantity' => 5, 'is_active' => true, 'is_featured' => false,
            'inventory_source' => 'local', 'kiot_sellable' => false,
        ]);
        $token = (string) Str::uuid();
        $order = app(KiotOrderService::class)->create([
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => $token,
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội',
            'payment_method' => 'sepay', 'payment_option' => $option,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], null)['order']->fresh();

        // KIOT order sync is off, so the transfer is taken straight away.
        $this->assertSame('not_required', $order->kiot_sync_status);

        return [$order, $token];
    }

    private function webhook(Order $order, int $amount, array $overrides = [])
    {
        return $this->withHeader('Authorization', 'Apikey webhook-secret')->postJson('/api/v1/sepay/callback', array_merge([
            'id' => ++$this->transactionId,
            'gateway' => 'BIDV',
            'transactionDate' => '2026-10-30 10:00:00',
            'accountNumber' => '8602206668',
            'content' => "{$order->order_number} chuyen tien",
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'FT123',
        ], $overrides));
    }

    private function sepayMethod(?int $total): array
    {
        return collect(app(\App\Services\Checkout\PaymentMethodAvailability::class)->checkoutMethods($total))
            ->firstWhere('code', 'sepay');
    }
}
