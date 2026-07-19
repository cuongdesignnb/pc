<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\IntegrationOutboxEvent;
use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\Product;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Integrations\Kiot\KiotOutboxService;
use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class KiotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('integrations.kiot', [
            'enabled' => true, 'product_sync_enabled' => true, 'order_sync_enabled' => true,
            'base_url' => 'https://kiot.test', 'client_id' => 'pc-website', 'secret' => 'test-secret',
            'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 2,
            'product_sync_limit' => 100, 'product_sync_overlap_seconds' => 120,
            'product_stale_after_minutes' => 15, 'outbox_max_attempts' => 3,
            'outbox_retry_base_seconds' => 1,
        ]);
        Bus::fake([SyncKiotProductsBySku::class]);
    }

    public function test_product_dry_run_is_exact_case_and_does_not_write(): void
    {
        $product = $this->product(['sku' => 'CPU-AbC', 'price' => 1000, 'sale_price' => 900, 'cost_price' => 700, 'stock_quantity' => 2]);
        Http::fake(['https://kiot.test/*' => Http::response($this->productList([$this->remote(['sku' => 'cpu-abc'])]), 200)]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: true, full: true);

        $this->assertSame(0, $report['matched']);
        $this->assertSame(['cpu-abc'], $report['remote_unmatched']);
        $this->assertSame('local', $product->fresh()->inventory_source);
        $this->assertSame('900', $product->fresh()->sale_price);
        $this->assertDatabaseCount('integration_sync_states', 0);
    }

    public function test_full_product_sync_follows_cursor_and_preserves_website_fields(): void
    {
        $first = $this->product(['sku' => 'CPU-1', 'price' => 1000, 'sale_price' => 900, 'cost_price' => 700, 'slug' => 'marketing-slug']);
        $second = $this->product(['sku' => 'CPU-2', 'price' => 1000]);
        $calls = 0;
        Http::fake(function (Request $request) use (&$calls) {
            $calls++;
            $this->assertSame('pc-website', $request->header('X-Integration-Key')[0]);
            $this->assertNotEmpty($request->header('X-Nonce')[0]);

            return $calls === 1
                ? Http::response($this->productList([$this->remote(['sku' => 'CPU-1'])], 'next-page'), 200)
                : Http::response($this->productList([$this->remote(['sku' => 'CPU-2', 'available_quantity' => 3])]), 200);
        });

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(2, $report['matched']);
        $this->assertSame(2, $calls);
        $first->refresh();
        $this->assertSame('kiot', $first->inventory_source);
        $this->assertSame(5, $first->stock_quantity);
        $this->assertSame('900', $first->sale_price);
        $this->assertSame('700', $first->cost_price);
        $this->assertSame('marketing-slug', $first->slug);
        $this->assertSame(500, $first->weight);
        $this->assertSame(36, $first->warranty_months);
        $this->assertNotNull(IntegrationSyncState::first()->last_successful_watermark);
        $this->assertSame(3, $second->fresh()->stock_quantity);
    }

    public function test_deleted_product_is_not_sellable_and_no_remote_product_is_created(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'stock_quantity' => 5]);
        Http::fake(['https://kiot.test/*' => Http::response($this->productList([
            $this->remote(['sku' => 'CPU-1', 'sync_status' => 'deleted']),
            $this->remote(['sku' => 'REMOTE-ONLY']),
        ]), 200)]);

        app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertFalse($product->fresh()->kiot_sellable);
        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('products', ['sku' => 'REMOTE-ONLY']);
    }

    public function test_targeted_refresh_marks_missing_kiot_sku_unmatched(): void
    {
        $product = $this->product(['sku' => 'MISSING-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 5]);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => false,
            'error' => ['code' => 'UNKNOWN_SKU', 'message' => 'SKU not found'],
        ], 404)]);

        app(KiotProductSyncService::class)->sync(dryRun: false, sku: 'MISSING-1');

        $product->refresh();
        $this->assertSame('unmatched', $product->kiot_sync_status);
        $this->assertSame('UNKNOWN_SKU', $product->kiot_sync_error_code);
        $this->assertFalse($product->kiot_sellable);
        $this->assertSame(0, $product->stock_quantity);
    }

    public function test_checkout_creates_snapshot_and_outbox_without_decrementing_stock_and_is_idempotent(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 5, 'price' => 1000]);
        Http::fake(['https://kiot.test/api/integrations/v1/pc/orders' => Http::response([
            'success' => true, 'duplicate' => false,
            'data' => ['kiot_order_id' => 989, 'kiot_order_code' => 'DH2607191430001234', 'external_order_id' => '1', 'status' => 'confirmed'],
        ], 201)]);
        $payload = $this->checkoutPayload($product->id);

        $first = $this->postJson('/api/v1/orders', $payload)->assertCreated()->json('order');
        $second = $this->postJson('/api/v1/orders', $payload)->assertOk()->json('order');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('synced', $first['kiot_sync_status']);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Test product', 'sku' => 'CPU-1', 'price' => 1000, 'total' => 1000]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('integration_outbox_events', 1);
        $this->assertSame('sent', IntegrationOutboxEvent::first()->status);
        $this->assertArrayNotHasKey('kiot_event_id', $first);
        $this->assertArrayNotHasKey('kiot_idempotency_key', $first);
        $this->assertArrayNotHasKey('kiot_response', $first);
    }

    public function test_transient_failure_retries_with_frozen_body_and_new_nonce(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 5]);
        $captured = [];
        Http::fake(function (Request $request) use (&$captured) {
            $captured[] = ['body' => $request->body(), 'nonce' => $request->header('X-Nonce')[0], 'key' => $request->header('Idempotency-Key')[0]];

            return count($captured) === 1
                ? Http::response(['success' => false, 'error' => ['code' => 'INTERNAL_INTEGRATION_ERROR', 'message' => 'temporary']], 503)
                : Http::response(['success' => true, 'duplicate' => true, 'data' => ['kiot_order_id' => 5, 'kiot_order_code' => 'K5']], 200);
        });
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        $service = app(KiotOutboxService::class);

        $service->process($created['outbox_id']);
        $event = IntegrationOutboxEvent::findOrFail($created['outbox_id']);
        $this->assertSame('retrying', $event->status);
        $service->process($event->id);

        $this->assertSame($captured[0]['body'], $captured[1]['body']);
        $this->assertSame($captured[0]['key'], $captured[1]['key']);
        $this->assertNotSame($captured[0]['nonce'], $captured[1]['nonce']);
        $this->assertSame(hash('sha256', $captured[0]['body']), $event->fresh()->payload_hash);
        $this->assertSame('synced', Order::first()->kiot_sync_status);
    }

    public function test_business_rejection_cancels_order_and_is_not_retried(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 5]);
        Http::fake(['https://kiot.test/*' => Http::response(['success' => false, 'error' => ['code' => 'INSUFFICIENT_AVAILABLE_STOCK', 'message' => 'No stock']], 422)]);

        $this->postJson('/api/v1/orders', $this->checkoutPayload($product->id))->assertStatus(422);

        $this->assertDatabaseHas('orders', ['kiot_sync_status' => 'rejected', 'order_status' => 'cancelled']);
        $this->assertDatabaseHas('integration_outbox_events', ['status' => 'rejected', 'attempt_count' => 1]);
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_cancel_calls_kiot_before_local_cancel_and_does_not_restore_stock(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 4]);
        Http::fake([
            'https://kiot.test/api/integrations/v1/pc/orders' => Http::response(['success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10']], 201),
            'https://kiot.test/api/integrations/v1/pc/orders/*/cancel' => Http::response(['success' => true, 'data' => ['status' => 'cancelled']], 200),
        ]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        app(KiotOutboxService::class)->process($created['outbox_id']);
        $order = Order::first();

        app(KiotOrderCancellationService::class)->cancel($order, 'Test cancel');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame('cancelled', $order->fresh()->kiot_sync_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('integration_outbox_events', ['event_type' => 'order.cancel', 'status' => 'sent']);
    }

    public function test_cancel_rejection_does_not_cancel_local_order(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 4]);
        Http::fake([
            'https://kiot.test/api/integrations/v1/pc/orders' => Http::response(['success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10']], 201),
            'https://kiot.test/api/integrations/v1/pc/orders/*/cancel' => Http::response(['success' => false, 'error' => ['code' => 'ORDER_ALREADY_INVOICED', 'message' => 'Already invoiced']], 409),
        ]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        app(KiotOutboxService::class)->process($created['outbox_id']);
        $order = Order::first();

        try {
            app(KiotOrderCancellationService::class)->cancel($order, 'Test reject');
            $this->fail('Expected cancellation rejection.');
        } catch (\App\Exceptions\KiotIntegrationException $exception) {
            $this->assertSame('ORDER_ALREADY_INVOICED', $exception->errorCode);
        }

        $this->assertSame('pending', $order->fresh()->order_status);
        $this->assertSame('synced', $order->fresh()->kiot_sync_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('integration_outbox_events', ['event_type' => 'order.cancel', 'status' => 'rejected']);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test product', 'slug' => 'test-'.Str::lower(Str::random(8)), 'sku' => 'SKU-'.Str::random(5),
            'price' => 1000, 'sale_price' => null, 'cost_price' => null, 'stock_quantity' => 5,
            'is_active' => true, 'is_featured' => false, 'inventory_source' => 'local',
            'kiot_sellable' => false, 'weight' => 500, 'warranty_months' => 12,
        ], $overrides));
    }

    private function remote(array $overrides = []): array
    {
        return array_merge([
            'id' => 1001, 'sku' => 'CPU-1', 'barcode' => '893000001001', 'name' => 'CPU',
            'retail_price' => 2000, 'stock_quantity' => 7, 'reserved_quantity' => 2,
            'available_quantity' => 5, 'has_serial' => true, 'is_active' => true,
            'sell_directly' => true, 'weight' => 500, 'warranty_months' => 36,
            'sync_status' => 'active', 'updated_at' => '2026-07-19T07:00:00Z',
        ], $overrides);
    }

    private function productList(array $products, ?string $cursor = null): array
    {
        return ['success' => true, 'data' => $products, 'meta' => ['next_cursor' => $cursor, 'has_more' => $cursor !== null]];
    }

    private function checkoutPayload(int $productId): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(), 'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC', 'shipping_city' => 'TP. Hồ Chí Minh',
            'shipping_district' => 'Quận 1', 'shipping_ward' => 'Phường 1', 'payment_method' => 'cod',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];
    }
}
