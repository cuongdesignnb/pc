<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotFulfillmentService;
use App\Services\Orders\OrderHistoryRecorder;
use App\Services\Orders\OrderWriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Tự tạo hoá đơn KIOT khi đơn web Đã giao và phiếu thu khi Đã thanh toán":
 * website payments become KIOT receipts (only the new amount), delivery becomes
 * a KIOT invoice (once), and KIOT refusing either never changes the order's
 * own KIOT sync status.
 */
class KiotFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{url: string, body: array<string, mixed>, key: ?string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('integrations.kiot', [
            'enabled' => true, 'product_sync_enabled' => false, 'order_sync_enabled' => true,
            'base_url' => 'https://kiot.test', 'client_id' => 'pc-website', 'secret' => 'test-secret',
            'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 2,
            'product_sync_limit' => 100, 'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15, 'outbox_max_attempts' => 3,
            'outbox_retry_base_seconds' => 1,
        ]);
        Bus::fake([SyncKiotProductsBySku::class]);
    }

    /** @var array<string, array{0: int, 1: array<string, mixed>}> */
    private array $responses = [];

    private bool $faked = false;

    /** @param  array<string, array{0: int, 1: array<string, mixed>}>  $responses  keyed by path suffix */
    private function fakeKiot(array $responses = []): void
    {
        $this->responses = $responses + [
            '/orders' => [201, ['success' => true, 'data' => ['kiot_order_id' => 55, 'kiot_order_code' => 'DH000055']]],
            '/deliver' => [201, ['success' => true, 'data' => ['kiot_order_code' => 'DH000055', 'status' => 'completed', 'kiot_invoice_code' => 'HD000123']]],
            '/payments' => [201, ['success' => true, 'data' => ['kiot_order_code' => 'DH000055', 'kiot_invoice_code' => null, 'cash_flow_code' => 'PT000777', 'amount_paid_total' => 3_000_000]]],
        ];
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        Http::fake(function (Request $request) {
            $this->sent[] = ['url' => $request->url(), 'body' => json_decode($request->body(), true) ?? [], 'key' => $request->header('Idempotency-Key')[0] ?? null];
            foreach (['/deliver', '/payments', '/orders'] as $suffix) {
                if (str_ends_with($request->url(), $suffix)) {
                    return Http::response($this->responses[$suffix][1], $this->responses[$suffix][0]);
                }
            }

            return Http::response(['success' => false], 404);
        });
    }

    private function syncedOrder(array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'order_number' => 'LP'.fake()->unique()->numerify('######'),
            'subtotal' => 10_000_000, 'discount' => 0, 'shipping_fee' => 0, 'total' => 10_000_000,
            'payment_status' => 'unpaid', 'payment_method' => 'cod', 'order_status' => 'shipping',
            'shipping_name' => 'Nguyễn Văn A', 'shipping_phone' => '0912345678', 'shipping_address' => '1 Phố Huế',
        ]);
        $order->forceFill(['kiot_sync_status' => 'synced', 'kiot_order_id' => 55, 'kiot_order_code' => 'DH000055'])->saveQuietly();

        return $order->fresh();
    }

    /** @return list<string> */
    private function paths(): array
    {
        return array_map(fn ($sent) => (string) parse_url($sent['url'], PHP_URL_PATH), $this->sent);
    }

    public function test_switch_off_sends_nothing(): void
    {
        $this->fakeKiot();
        $order = $this->syncedOrder();

        $order->update(['order_status' => 'delivered', 'amount_paid' => 10_000_000, 'payment_status' => 'paid']);

        $this->assertSame([], $this->sent);
        $this->assertSame(0, IntegrationOutboxEvent::count());
        $this->assertFalse(KiotFulfillmentService::enabled());
    }

    public function test_turning_the_switch_on_never_resends_money_or_deliveries_from_before(): void
    {
        $this->fakeKiot();
        $paid = $this->syncedOrder();
        $paid->update(['order_status' => 'delivered', 'amount_paid' => 10_000_000, 'payment_status' => 'paid']);
        $deposit = $this->syncedOrder();
        $deposit->update(['amount_paid' => 2_000_000, 'payment_status' => 'deposited']);

        KiotFulfillmentService::setEnabled(true);
        $paid->refresh()->update(['notes' => 'sửa ghi chú']);
        $deposit->refresh()->update(['amount_paid' => 3_000_000]);

        // Only the 1.000.000đ paid after the switch went on reaches KIOT.
        $this->assertSame(["/api/integrations/v1/pc/orders/{$deposit->id}/payments"], $this->paths());
        $this->assertSame(1_000_000, $this->sent[0]['body']['amount']);
        $this->assertNotNull($paid->fresh()->kiot_delivery_synced_at);
        $this->assertSame(10_000_000, (int) $paid->fresh()->kiot_paid_synced_amount);
    }

    public function test_delivered_order_becomes_a_kiot_invoice_once(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $this->fakeKiot();
        $order = $this->syncedOrder(['tracking_code' => 'GHN1', 'shipping_carrier' => 'GHN']);

        OrderHistoryRecorder::actingAs('admin', null, fn () => $order->update(['order_status' => 'delivered']));
        $order->refresh();
        $order->update(['notes' => 'đã giao']);
        $order->update(['order_status' => 'delivered']);

        $this->assertSame(["/api/integrations/v1/pc/orders/{$order->id}/deliver"], $this->paths());
        $body = $this->sent[0]['body'];
        $this->assertTrue(Str::isUuid($body['event_id']));
        $this->assertSame(['GHN1', 'GHN'], [$body['tracking_code'], $body['carrier']]);
        $this->assertNotEmpty($body['delivered_at']);
        $this->assertNotEmpty($this->sent[0]['key']);
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertNotNull($order->fresh()->kiot_delivery_synced_at);
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'type' => 'kiot', 'actor_type' => 'kiot', 'message' => 'KIOT đã tạo hoá đơn HD000123.']);
        $this->assertSame(['sent'], IntegrationOutboxEvent::pluck('status')->all());
    }

    public function test_only_new_money_is_sent_as_receipts_and_before_the_invoice(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $this->fakeKiot();
        $order = $this->syncedOrder();
        $orders = app(OrderWriteService::class);

        OrderHistoryRecorder::actingAs('api', null, fn () => $orders->recordPayment($order, 3_000_000, '2026-11-16T09:00:00+07:00', 'bank_transfer', 'FT1'), null, 'Token: n8n');
        // Admin marks the rest paid and the order delivered in one go: receipt first, then the invoice.
        $order->refresh()->update(['payment_status' => 'paid', 'amount_paid' => 10_000_000, 'order_status' => 'delivered']);

        $this->assertSame([
            "/api/integrations/v1/pc/orders/{$order->id}/payments",
            "/api/integrations/v1/pc/orders/{$order->id}/payments",
            "/api/integrations/v1/pc/orders/{$order->id}/deliver",
        ], $this->paths());
        $this->assertSame([3_000_000, 'bank_transfer', 'FT1'], [$this->sent[0]['body']['amount'], $this->sent[0]['body']['method'], $this->sent[0]['body']['reference']]);
        $this->assertSame([7_000_000, 'cod'], [$this->sent[1]['body']['amount'], $this->sent[1]['body']['method']]);
        $this->assertSame(10_000_000, (int) $order->fresh()->kiot_paid_synced_amount);
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'type' => 'kiot', 'message' => 'KIOT đã ghi phiếu thu PT000777 3.000.000đ (tổng đã thu 3.000.000đ).']);
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
    }

    public function test_serial_not_allocated_is_written_to_history_without_retry_or_status_change(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $this->fakeKiot(['/deliver' => [422, ['success' => false, 'error' => ['code' => 'SERIAL_NOT_ALLOCATED', 'message' => 'Dòng 1 chưa chọn Serial/IMEI.']]]]);
        $order = $this->syncedOrder();

        $order->update(['order_status' => 'delivered']);

        $event = IntegrationOutboxEvent::firstOrFail();
        $this->assertSame(['order.deliver', 'rejected', 1], [$event->event_type, $event->status, $event->attempt_count]);
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertNull($order->fresh()->kiot_sync_error_code);
        $message = OrderHistory::where('order_id', $order->id)->where('type', 'kiot')->latest('id')->value('message');
        $this->assertSame('KIOT chưa tạo hoá đơn: cần chọn Serial/IMEI trong KIOT (SERIAL_NOT_ALLOCATED: Dòng 1 chưa chọn Serial/IMEI.)', $message);

        $this->artisan('kiot:retry-outbox')->assertSuccessful();
        $this->assertCount(1, $this->sent);

        // Fixed in KIOT: staff retry from Tích hợp KIOT, a fresh event is sent.
        $this->fakeKiot();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin)->post("/admin/integrations/kiot/events/{$event->id}/retry")->assertSessionHas('success');
        $this->assertSame(['cancelled', 'sent'], IntegrationOutboxEvent::orderBy('id')->pluck('status')->all());
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'message' => 'KIOT đã tạo hoá đơn HD000123.']);
    }

    public function test_connection_errors_retry_without_touching_the_order_sync_status(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $this->fakeKiot(['/payments' => [503, ['success' => false, 'error' => ['code' => 'TEMPORARILY_UNAVAILABLE', 'message' => 'KIOT bận']]]]);
        $order = $this->syncedOrder();

        $order->update(['amount_paid' => 2_000_000, 'payment_status' => 'deposited']);

        $event = IntegrationOutboxEvent::firstOrFail();
        $this->assertSame(['order.payment', 'retrying'], [$event->event_type, $event->status]);
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertNull($order->fresh()->kiot_sync_error_code);
    }

    public function test_orders_not_in_kiot_send_nothing_until_they_are_resent(): void
    {
        KiotFulfillmentService::setEnabled(true);
        $product = Product::create([
            'name' => 'Dell 15', 'slug' => 'dell-'.Str::lower(Str::random(6)), 'sku' => 'WEB-NEW-D15',
            'price' => 10_000_000, 'stock_quantity' => 3, 'is_active' => true, 'inventory_source' => 'local', 'weight' => 2000,
        ]);
        $this->fakeKiot(['/orders' => [422, ['success' => false, 'error' => ['code' => 'UNKNOWN_SKU', 'message' => 'SKU không tồn tại.']]]]);
        $this->postJson('/api/v1/orders', [
            'checkout_idempotency_key' => (string) Str::uuid(), 'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('rejected', $order->kiot_sync_status);

        // Delivered and paid by hand while KIOT does not have the order: nothing to send yet.
        $order->update(['order_status' => 'delivered', 'payment_status' => 'paid', 'amount_paid' => (int) $order->total]);
        $this->assertSame(['order.create'], IntegrationOutboxEvent::pluck('event_type')->all());

        $admin = User::factory()->create(['role' => 'admin']);
        $product->update(['kiot_code' => 'D15']);
        $this->sent = [];
        $this->fakeKiot();
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertRedirect();

        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertSame([
            '/api/integrations/v1/pc/orders',
            "/api/integrations/v1/pc/orders/{$order->id}/payments",
            "/api/integrations/v1/pc/orders/{$order->id}/deliver",
        ], $this->paths());
        $this->assertSame((int) $order->total, $this->sent[1]['body']['amount']);
    }
}
