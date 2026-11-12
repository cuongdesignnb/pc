<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A web order KIOT refuses (e.g. UNKNOWN_SKU for a WEB-NEW product) is never
 * cancelled by the website: it stays pending, says "chưa vào KIOT", can be
 * resent by staff or automatically (limited), and every change is on the
 * order's history.
 */
class KiotRejectedOrderTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN_SKU = ['success' => false, 'error' => [
        'code' => 'UNKNOWN_SKU', 'message' => 'Một hoặc nhiều SKU không tồn tại trong KIOT.',
        'details' => [['sku' => 'WEB-NEW-DC15250-I5-16T', 'reason' => 'not_found']],
    ]];

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

    private function webProduct(): Product
    {
        return Product::create([
            'name' => 'Dell 15 DC15250 i5 16GB', 'slug' => 'dell-'.Str::lower(Str::random(6)), 'sku' => 'WEB-NEW-DC15250-I5-16T',
            'price' => 20_990_000, 'stock_quantity' => 3, 'is_active' => true, 'inventory_source' => 'local', 'weight' => 2000,
        ]);
    }

    private function placeOrder(Product $product): Order
    {
        $this->postJson('/api/v1/orders', [
            'checkout_idempotency_key' => (string) Str::uuid(), 'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        return Order::latest('id')->firstOrFail();
    }

    public function test_unknown_sku_keeps_the_order_pending_with_a_readable_reason_and_history(): void
    {
        Http::fake(['https://kiot.test/*' => Http::response(self::UNKNOWN_SKU, 422)]);

        $order = $this->placeOrder($this->webProduct());

        $this->assertSame('pending', $order->order_status);
        $this->assertSame('rejected', $order->kiot_sync_status);
        $this->assertSame('Chưa vào KIOT: Một hoặc nhiều SKU không tồn tại trong KIOT. (SKU: WEB-NEW-DC15250-I5-16T)', $order->kiot_sync_error_message);
        $this->assertTrue($order->canResendToKiot());
        $this->assertTrue($order->canCancel());

        $history = OrderHistory::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(['status', 'kiot'], $history->pluck('type')->all());
        $this->assertSame(['customer', null, 'pending'], [$history[0]->actor_type, $history[0]->from_status, $history[0]->to_status]);
        $this->assertStringStartsWith('Chưa vào KIOT (UNKNOWN_SKU)', $history[1]->message);
        // No status change other than the order being placed.
        $this->assertSame(1, OrderHistory::where('order_id', $order->id)->where('type', 'status')->count());
    }

    public function test_staff_link_a_kiot_code_and_resend_the_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->webProduct();
        $bodies = [];
        Http::fake(function (Request $request) use (&$bodies) {
            $bodies[] = json_decode($request->body(), true);

            return count($bodies) === 1
                ? Http::response(self::UNKNOWN_SKU, 422)
                : Http::response(['success' => true, 'data' => ['kiot_order_id' => 55, 'kiot_order_code' => 'DH000055']], 201);
        });
        $order = $this->placeOrder($product);

        $this->actingAs($admin)->get("/admin/orders/{$order->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('kiot.can_resend', true)
                ->where('kiot.unlinked_products.0.sku', 'WEB-NEW-DC15250-I5-16T'));

        $product->update(['kiot_code' => 'DC15250']);
        $this->actingAs($admin)->post("/admin/orders/{$order->id}/resend-kiot")->assertRedirect();

        $order->refresh();
        $this->assertSame('synced', $order->kiot_sync_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame('DC15250', $bodies[1]['items'][0]['sku']);
        $this->assertSame('Dell 15 DC15250 i5 16GB', $bodies[1]['items'][0]['catalog_name']);
        $this->assertSame(20_990_000, $bodies[1]['items'][0]['catalog_price']);
        $this->assertSame(['rejected', 'sent'], IntegrationOutboxEvent::where('aggregate_id', $order->id)->orderBy('id')->pluck('status')->all());
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'type' => 'kiot', 'message' => 'Đã vào KIOT với mã DH000055.']);
    }

    public function test_refused_orders_are_resent_automatically_three_times_at_most(): void
    {
        Http::fake(['https://kiot.test/*' => Http::response(self::UNKNOWN_SKU, 422)]);
        $order = $this->placeOrder($this->webProduct());

        $this->artisan('kiot:resend-unsynced-orders')->assertSuccessful();
        $this->assertSame(0, $order->fresh()->kiot_auto_resend_count, 'waits 15 minutes first');

        foreach ([16, 61, 241] as $minutes) {
            $this->travel($minutes)->minutes();
            $this->artisan('kiot:resend-unsynced-orders')->assertSuccessful();
        }
        $this->travel(1)->days();
        $this->artisan('kiot:resend-unsynced-orders')->assertSuccessful();

        $order->refresh();
        $this->assertSame(3, $order->kiot_auto_resend_count);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame(4, IntegrationOutboxEvent::where('aggregate_id', $order->id)->count());
        $this->assertSame(3, OrderHistory::where('order_id', $order->id)->where('message', 'like', 'Tự gửi lại sang KIOT lần%')->count());
    }

    public function test_every_status_change_is_recorded_with_who_and_why(): void
    {
        config()->set('integrations.kiot.order_sync_enabled', false);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Chị Lan']);
        $order = $this->placeOrder($this->webProduct());

        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/status", ['order_status' => 'confirmed', 'reason' => 'Đã gọi khách'])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/orders/{$order->id}/status", ['order_status' => 'cancelled'])->assertRedirect();

        $rows = OrderHistory::where('order_id', $order->id)->where('type', 'status')->orderBy('id')->get();
        $this->assertSame([[null, 'pending'], ['pending', 'confirmed'], ['confirmed', 'cancelled']], $rows->map(fn ($row) => [$row->from_status, $row->to_status])->all());
        $this->assertSame(['customer', 'admin', 'admin'], $rows->pluck('actor_type')->all());
        $this->assertSame([null, $admin->id, $admin->id], $rows->pluck('actor_id')->all());
        $this->assertSame(['Khách đặt hàng trên website', 'Đã gọi khách', 'Nhân viên huỷ đơn'], $rows->pluck('message')->all());
        $this->assertSame('Nhân viên: Chị Lan', $rows[1]->actor_label);
    }

    public function test_deploy_restores_orders_the_website_cancelled_when_kiot_refused_them(): void
    {
        config()->set('integrations.kiot.order_sync_enabled', false);
        $product = $this->webProduct();
        $autoCancelled = $this->placeOrder($product);
        $autoCancelled->forceFill(['order_status' => 'cancelled', 'kiot_sync_status' => 'rejected'])->saveQuietly();
        $staffCancelled = $this->placeOrder($product);
        $staffCancelled->forceFill(['order_status' => 'cancelled', 'kiot_sync_status' => 'cancelled'])->saveQuietly();

        (require database_path('migrations/2026_11_12_000002_create_order_histories_and_kiot_resend_columns.php'))->up();

        $this->assertSame('pending', $autoCancelled->fresh()->order_status);
        $this->assertSame('cancelled', $staffCancelled->fresh()->order_status);
        $this->assertDatabaseHas('order_histories', ['order_id' => $autoCancelled->id, 'from_status' => 'cancelled', 'to_status' => 'pending', 'actor_type' => 'system']);
    }
}
