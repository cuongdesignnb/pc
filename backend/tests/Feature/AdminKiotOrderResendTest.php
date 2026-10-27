<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminKiotOrderResendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureKiot(orderSync: true);
        Bus::fake([SyncKiotProductsBySku::class]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_order_created_while_sync_was_off_can_be_resent_and_synced(): void
    {
        $order = $this->orderCreatedWithSyncOff();
        $this->assertSame('not_required', $order->kiot_sync_status);
        $captured = [];
        Http::fake(function (Request $request) use (&$captured) {
            $captured[] = json_decode($request->body(), true);

            return Http::response(['success' => true, 'data' => ['kiot_order_id' => 77, 'kiot_order_code' => 'DHK77']], 201);
        });

        $this->actingAs($this->admin)
            ->get("/admin/orders/{$order->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kiot.status', 'not_required')
                ->where('kiot.can_resend', true));

        $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}")
            ->post("/admin/orders/{$order->id}/resend-kiot")
            ->assertRedirect("/admin/orders/{$order->id}")
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('synced', $order->kiot_sync_status);
        $this->assertSame('DHK77', $order->kiot_order_code);
        $this->assertCount(1, $captured);
        $this->assertSame($order->order_number, $captured[0]['external_order_code']);
        $this->assertSame(500, $captured[0]['delivery']['weight']);
        $this->assertDatabaseHas('integration_outbox_events', [
            'aggregate_id' => $order->id, 'event_type' => 'order.create', 'status' => 'sent',
            'idempotency_key' => $order->kiot_idempotency_key,
        ]);

        // Once synced the button disappears and a second request is refused.
        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertSessionHas('error');
        $this->assertCount(1, $captured);
    }

    public function test_resend_is_hidden_and_refused_while_order_sync_is_off(): void
    {
        $order = $this->orderCreatedWithSyncOff();
        $this->configureKiot(orderSync: false);
        Http::fake();

        $this->actingAs($this->admin)
            ->get("/admin/orders/{$order->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('kiot.order_sync_enabled', false)
                ->where('kiot.can_resend', false));
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/resend-kiot")
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame('not_required', $order->fresh()->kiot_sync_status);
        $this->assertDatabaseCount('integration_outbox_events', 0);
    }

    public function test_dead_lettered_order_is_replayed_with_its_original_idempotency_key(): void
    {
        config()->set('integrations.kiot.outbox_max_attempts', 1);
        $keys = [];
        Http::fake(function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return count($keys) === 1
                ? Http::response(['success' => false, 'error' => ['code' => 'INTERNAL_INTEGRATION_ERROR', 'message' => 'down']], 503)
                : Http::response(['success' => true, 'duplicate' => true, 'data' => ['kiot_order_id' => 5, 'kiot_order_code' => 'K5']], 200);
        });
        $this->postJson('/api/v1/orders', $this->checkoutPayload($this->product()->id));
        $order = Order::firstOrFail();
        $this->assertSame('failed', $order->kiot_sync_status);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertSessionHas('success');

        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertDatabaseCount('integration_outbox_events', 1);
    }

    public function test_rejected_resend_does_not_cancel_an_order_staff_already_confirmed(): void
    {
        $order = $this->orderCreatedWithSyncOff();
        $order->update(['order_status' => 'confirmed']);
        Http::fake(['https://kiot.test/*' => Http::response(['success' => false, 'error' => ['code' => 'INSUFFICIENT_AVAILABLE_STOCK', 'message' => 'Hết hàng']], 422)]);

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertSessionHas('error');

        $order->refresh();
        $this->assertSame('rejected', $order->kiot_sync_status);
        $this->assertSame('confirmed', $order->order_status);
    }

    public function test_cancelled_order_cannot_be_resent(): void
    {
        $order = $this->orderCreatedWithSyncOff();
        $order->update(['order_status' => 'cancelled']);
        Http::fake();

        $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, IntegrationOutboxEvent::count());
    }

    private function orderCreatedWithSyncOff(): Order
    {
        $this->configureKiot(orderSync: false);
        $order = app(KiotOrderService::class)->create($this->checkoutPayload($this->product()->id), null)['order'];
        $this->configureKiot(orderSync: true);

        return $order->fresh();
    }

    private function configureKiot(bool $orderSync): void
    {
        config()->set('integrations.kiot', [
            'enabled' => true, 'product_sync_enabled' => true, 'order_sync_enabled' => $orderSync,
            'base_url' => 'https://kiot.test', 'client_id' => 'pc-website', 'secret' => 'test-secret',
            'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 2,
            'product_sync_limit' => 100, 'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15, 'outbox_max_attempts' => config('integrations.kiot.outbox_max_attempts', 3),
            'outbox_retry_base_seconds' => 1,
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Test product', 'slug' => 'test-'.Str::lower(Str::random(8)), 'sku' => 'CPU-1',
            'price' => 1000, 'stock_quantity' => 5, 'is_active' => true, 'inventory_source' => 'kiot',
            'kiot_sellable' => true, 'weight' => 500, 'warranty_months' => 12,
        ]);
    }

    private function checkoutPayload(int $productId): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(), 'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC', 'shipping_city' => 'TP. Hồ Chí Minh',
            'shipping_district' => 'Quận 1', 'shipping_ward' => 'Phường 1', 'payment_method' => 'cod',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];
    }
}
