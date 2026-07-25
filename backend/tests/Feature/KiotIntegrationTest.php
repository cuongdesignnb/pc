<?php

namespace Tests\Feature;

use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Jobs\Integrations\Kiot\SyncKiotProducts;
use App\Jobs\Integrations\Kiot\SyncKiotProductsBySku;
use App\Models\Cart;
use App\Models\Category;
use App\Models\ComponentType;
use App\Models\IntegrationConnection;
use App\Models\IntegrationOutboxEvent;
use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\Product;
use App\Models\SepayPaymentEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotClient;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use App\Services\Integrations\Kiot\KiotOutboxService;
use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
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
        IntegrationConnection::create([
            'provider' => 'kiot',
            'configuration_source' => 'manual',
            'base_url' => 'https://kiot.test',
            'client_id' => 'pc-website',
            'secret_encrypted' => 'test-secret',
            'secret_fingerprint' => substr(hash('sha256', 'test-secret'), 0, 16),
            'api_version' => 'v1',
            'connection_status' => 'connected',
            'is_enabled' => false,
            'product_sync_enabled' => false,
            'order_sync_enabled' => false,
            'capabilities' => ['products' => true, 'orders' => true],
        ]);
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

    public function test_provider_available_quantity_is_preserved_when_product_is_not_sellable(): void
    {
        $product = $this->product(['sku' => 'CPU-1']);
        Http::fake(['https://kiot.test/*' => Http::response($this->productList([
            $this->remote(['sell_directly' => false, 'available_quantity' => 4]),
        ]), 200)]);

        app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $product->refresh();
        $this->assertFalse($product->kiot_sellable);
        $this->assertSame(0, $product->stock_quantity);
        $this->assertSame(4, $product->kiot_available_quantity);
    }

    public function test_incremental_sync_sends_an_rfc3339_watermark_with_overlap(): void
    {
        IntegrationSyncState::create([
            'integration' => 'kiot',
            'resource' => 'products',
            'status' => 'completed',
            'last_successful_watermark' => '2026-07-20 10:00:00',
        ]);
        Http::fake(function (Request $request) {
            $this->assertSame('2026-07-20T09:58:00+00:00', $request->data()['updated_since']);
            $this->assertSame(1, $request->data()['include_inactive']);

            return Http::response($this->productList([]), 200);
        });

        app(KiotProductSyncService::class)->sync(dryRun: false);
    }

    public function test_failed_later_cursor_page_does_not_advance_the_product_watermark(): void
    {
        $product = $this->product(['sku' => 'CPU-1']);
        IntegrationSyncState::create([
            'integration' => 'kiot',
            'resource' => 'products',
            'status' => 'completed',
            'last_successful_watermark' => '2026-07-20 10:00:00',
        ]);
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            return $calls === 1
                ? Http::response($this->productList([$this->remote(['updated_at' => '2026-07-20T11:00:00Z'])], 'page-2'), 200)
                : Http::response(['success' => false, 'error' => ['code' => 'INTERNAL_INTEGRATION_ERROR', 'message' => 'temporary']], 503);
        });

        try {
            app(KiotProductSyncService::class)->sync(dryRun: false);
            $this->fail('Expected the second cursor page to fail.');
        } catch (\App\Exceptions\KiotIntegrationException $exception) {
            $this->assertSame('INTERNAL_INTEGRATION_ERROR', $exception->errorCode);
        }

        $this->assertSame('2026-07-20 10:00:00', IntegrationSyncState::first()->last_successful_watermark->format('Y-m-d H:i:s'));
        $this->assertSame('failed', IntegrationSyncState::first()->status);
        $this->assertSame('kiot', $product->fresh()->inventory_source);
    }

    public function test_targeted_product_request_uses_a_url_encoded_exact_case_sku(): void
    {
        $product = $this->product(['sku' => 'SKU A/B']);
        Http::fake(function (Request $request) {
            $this->assertStringContainsString('/products/SKU%20A%2FB', $request->url());

            return Http::response(['success' => true, 'data' => $this->remote(['sku' => 'SKU A/B'])], 200);
        });

        app(KiotProductSyncService::class)->sync(dryRun: false, sku: 'SKU A/B');

        $this->assertSame('kiot', $product->fresh()->inventory_source);
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
        $this->assertDatabaseHas('orders', [
            'id' => $first['id'],
            'order_access_token_hash' => Order::hashAccessToken($payload['order_access_token']),
        ]);
        $this->assertStringNotContainsString($payload['order_access_token'], IntegrationOutboxEvent::first()->raw_body);
    }

    public function test_checkout_idempotency_key_cannot_be_reused_with_another_access_token(): void
    {
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10'],
        ], 201)]);
        $payload = $this->checkoutPayload($product->id);
        $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $payload['order_access_token'] = (string) Str::uuid();

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(409)
            ->assertJson(['error_code' => 'IDEMPOTENCY_KEY_CONFLICT']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('integration_outbox_events', 1);
    }

    public function test_checkout_rolls_back_order_items_and_outbox_when_payload_serialization_fails(): void
    {
        $product = $this->product(['sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true]);
        $payload = $this->checkoutPayload($product->id);
        $client = \Mockery::mock(KiotClient::class);
        $client->shouldReceive('assertConfigured')->once();
        $client->shouldReceive('encode')->once()->andThrow(new \App\Exceptions\KiotIntegrationException(
            'INVALID_PAYLOAD',
            'Unable to serialize payload.',
            'fatal_conflict',
        ));
        $this->app->instance(KiotClient::class, $client);

        try {
            app(KiotOrderService::class)->create($payload, null);
            $this->fail('Expected invalid UTF-8 payload serialization to fail.');
        } catch (\App\Exceptions\KiotIntegrationException $exception) {
            $this->assertSame('INVALID_PAYLOAD', $exception->errorCode);
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('integration_outbox_events', 0);
    }

    public function test_guest_order_status_requires_the_matching_access_token_and_excludes_internal_data(): void
    {
        $product = $this->product([
            'sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true,
            'price' => 1000, 'cost_price' => 700,
        ]);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10'],
        ], 201)]);
        $payload = $this->checkoutPayload($product->id);
        $order = $this->postJson('/api/v1/orders', $payload)->assertCreated()->json('order');

        $this->getJson("/api/v1/orders/{$order['id']}")->assertNotFound();
        $this->withHeader('X-Order-Access-Token', (string) Str::uuid())
            ->getJson("/api/v1/orders/{$order['id']}")->assertNotFound();
        $response = $this->withHeader('X-Order-Access-Token', $payload['order_access_token'])
            ->getJson("/api/v1/orders/{$order['id']}")->assertOk();

        $response->assertJsonMissingPath('order_access_token_hash')
            ->assertJsonMissingPath('checkout_idempotency_key')
            ->assertJsonMissingPath('kiot_response')
            ->assertJsonMissingPath('items.0.product');
        $this->withHeader('X-Order-Access-Token', $payload['order_access_token'])
            ->getJson("/api/v1/orders/{$order['id']}/check-payment")->assertOk();
    }

    public function test_authenticated_customer_cannot_read_another_customers_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), $owner->id);

        Sanctum::actingAs($intruder);
        $this->getJson("/api/v1/orders/{$created['order']->id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/orders/{$created['order']->id}")->assertOk();
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
        $this->assertCount(1, $captured);
        $event->update(['next_attempt_at' => now()]);
        $service->process($event->id);

        $this->assertSame($captured[0]['body'], $captured[1]['body']);
        $this->assertSame($captured[0]['key'], $captured[1]['key']);
        $this->assertNotSame($captured[0]['nonce'], $captured[1]['nonce']);
        $this->assertSame(hash('sha256', $captured[0]['body']), $event->fresh()->payload_hash);
        $this->assertSame('synced', Order::first()->kiot_sync_status);
    }

    public function test_rate_limit_and_provider_server_errors_are_retryable(): void
    {
        $responseStatus = 429;
        Http::fake(function () use (&$responseStatus) {
            return Http::response([
                'success' => false,
                'error' => ['code' => 'UPSTREAM_TEMPORARY_FAILURE', 'message' => 'temporary'],
            ], $responseStatus);
        });

        foreach ([429, 500, 502, 503, 504] as $status) {
            $responseStatus = $status;
            $product = $this->product([
                'sku' => "RETRY-{$status}",
                'inventory_source' => 'kiot',
                'kiot_sellable' => true,
            ]);
            $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);

            app(KiotOutboxService::class)->process($created['outbox_id']);

            $this->assertDatabaseHas('integration_outbox_events', [
                'id' => $created['outbox_id'],
                'status' => 'retrying',
                'response_status' => $status,
            ]);
            $this->assertDatabaseHas('orders', [
                'id' => $created['order']->id,
                'kiot_sync_status' => 'retrying',
            ]);
        }
    }

    public function test_connection_failure_is_retryable(): void
    {
        $product = $this->product([
            'sku' => 'RETRY-CONNECTION',
            'inventory_source' => 'kiot',
            'kiot_sellable' => true,
        ]);
        Http::fake(fn () => throw new ConnectionException('connection timed out'));
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);

        app(KiotOutboxService::class)->process($created['outbox_id']);

        $this->assertDatabaseHas('integration_outbox_events', [
            'id' => $created['outbox_id'],
            'status' => 'retrying',
            'last_error_code' => 'CONNECTION_ERROR',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $created['order']->id,
            'kiot_sync_status' => 'retrying',
        ]);
    }

    public function test_retryable_event_moves_to_dead_letter_at_the_configured_limit(): void
    {
        config()->set('integrations.kiot.outbox_max_attempts', 2);
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => false, 'error' => ['code' => 'INTERNAL_INTEGRATION_ERROR', 'message' => 'temporary'],
        ], 503)]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        $service = app(KiotOutboxService::class);

        $service->process($created['outbox_id']);
        IntegrationOutboxEvent::findOrFail($created['outbox_id'])->update(['next_attempt_at' => now()]);
        $service->process($created['outbox_id']);

        $this->assertDatabaseHas('integration_outbox_events', [
            'id' => $created['outbox_id'], 'status' => 'dead_letter', 'attempt_count' => 2,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $created['order']->id, 'kiot_sync_status' => 'failed']);
    }

    public function test_stale_outbox_lock_is_recovered_and_sent(): void
    {
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        IntegrationOutboxEvent::findOrFail($created['outbox_id'])->update([
            'status' => 'processing', 'attempt_count' => 1, 'locked_at' => now()->subMinutes(6),
        ]);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10'],
        ], 201)]);

        app(KiotOutboxService::class)->process($created['outbox_id']);

        $this->assertDatabaseHas('integration_outbox_events', [
            'id' => $created['outbox_id'], 'status' => 'sent', 'attempt_count' => 2,
        ]);
    }

    public function test_sepay_callback_before_kiot_accept_is_reconciled_after_order_sync(): void
    {
        config()->set('services.sepay.webhook_key', 'webhook-secret');
        $product = $this->product([
            'sku' => 'CPU-1', 'inventory_source' => 'kiot', 'kiot_sellable' => true,
            'price' => 1000,
        ]);
        $payload = $this->checkoutPayload($product->id);
        $payload['payment_method'] = 'sepay';
        $created = app(KiotOrderService::class)->create($payload, null);
        $order = $created['order']->fresh();

        $this->withHeader('Authorization', 'Apikey webhook-secret')->postJson('/api/v1/sepay/callback', [
            'id' => 987654,
            'transferAmount' => $order->total,
            'content' => "Thanh toan {$order->order_number}",
            'gateway' => 'VCB',
        ])->assertOk()->assertJson(['payment_status' => 'pending_reconciliation']);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('sepay_payment_events', ['external_transaction_id' => 987654, 'status' => 'pending']);
        Http::fake(['https://kiot.test/*' => Http::response([
            'success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10'],
        ], 201)]);

        app(KiotOutboxService::class)->process($created['outbox_id']);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('processed', SepayPaymentEvent::first()->status);
        $this->assertSame(1, Transaction::count());
    }

    public function test_sepay_webhook_fails_closed_when_authentication_is_missing(): void
    {
        config()->set('services.sepay.webhook_key', null);

        $this->postJson('/api/v1/sepay/callback', [
            'id' => 123,
            'transferAmount' => 1000,
            'content' => 'DH202607200001',
        ])->assertStatus(503);
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

    public function test_guest_cancel_requires_the_matching_order_access_token(): void
    {
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        Http::fake([
            'https://kiot.test/api/integrations/v1/pc/orders' => Http::response(['success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10']], 201),
            'https://kiot.test/api/integrations/v1/pc/orders/*/cancel' => Http::response(['success' => true, 'data' => ['status' => 'cancelled']], 200),
        ]);
        $payload = $this->checkoutPayload($product->id);
        $order = $this->postJson('/api/v1/orders', $payload)->assertCreated()->json('order');

        $this->postJson("/api/v1/orders/{$order['id']}/cancel", [])->assertNotFound();
        $this->withHeader('X-Order-Access-Token', $payload['order_access_token'])
            ->postJson("/api/v1/orders/{$order['id']}/cancel", [])
            ->assertOk()
            ->assertJsonPath('order.order_status', 'cancelled');
    }

    public function test_kiot_order_cannot_be_cancelled_locally_while_integration_is_disabled(): void
    {
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true]);
        $created = app(KiotOrderService::class)->create($this->checkoutPayload($product->id), null);
        $created['order']->update(['kiot_sync_status' => 'synced']);
        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);

        try {
            app(KiotOrderCancellationService::class)->cancel($created['order']->fresh(), 'Test disabled');
            $this->fail('Expected fail-closed cancellation.');
        } catch (\App\Exceptions\KiotIntegrationException $exception) {
            $this->assertSame('INTEGRATION_DISABLED', $exception->errorCode);
        }

        $this->assertSame('pending', $created['order']->fresh()->order_status);
    }

    public function test_pcbuilder_product_cart_and_checkout_share_the_sellability_gate(): void
    {
        $category = Category::create(['name' => 'Components', 'slug' => 'components', 'is_active' => true]);
        $componentType = ComponentType::create(['name' => 'CPU', 'slug' => 'cpu', 'display_order' => 1]);
        $local = $this->product(['sku' => 'LOCAL-OK', 'category_id' => $category->id, 'component_type_id' => $componentType->id]);
        $kiot = $this->product([
            'sku' => 'KIOT-OK', 'category_id' => $category->id, 'component_type_id' => $componentType->id,
            'inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 2,
        ]);
        $blocked = $this->product([
            'sku' => 'KIOT-BLOCKED', 'category_id' => $category->id, 'component_type_id' => $componentType->id,
            'inventory_source' => 'kiot', 'kiot_sellable' => false, 'stock_quantity' => 0,
        ]);
        $deleted = $this->product([
            'sku' => 'KIOT-DELETED', 'category_id' => $category->id, 'component_type_id' => $componentType->id,
            'inventory_source' => 'kiot', 'kiot_sellable' => false, 'stock_quantity' => 0, 'kiot_sync_status' => 'deleted',
        ]);

        $listingSkus = collect($this->getJson('/api/v1/products?per_page=100')->assertOk()->json('data'))->pluck('sku');
        $this->assertEqualsCanonicalizing([$local->sku, $kiot->sku], $listingSkus->all());
        $this->getJson("/api/v1/products/{$deleted->slug}")->assertNotFound();

        $builderSkus = collect($this->postJson('/api/v1/builder/compatible/cpu', ['build' => []])
            ->assertOk()->json('products'))->pluck('product.sku');
        $this->assertEqualsCanonicalizing([$local->sku, $kiot->sku], $builderSkus->all());

        $this->withHeader('X-Cart-Session', 'sellability-test')->postJson('/api/v1/cart/items', [
            'product_id' => $blocked->id,
            'quantity' => 1,
        ])->assertUnprocessable();
        $this->postJson('/api/v1/orders', $this->checkoutPayload($blocked->id))->assertUnprocessable();
    }

    public function test_cart_is_cleared_once_on_accept_preserved_on_rejection_and_cleared_on_transient_retry(): void
    {
        $product = $this->product(['inventory_source' => 'kiot', 'kiot_sellable' => true, 'stock_quantity' => 5]);
        $cart = Cart::create(['session_id' => 'accepted-cart']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);
        Http::fake(['https://kiot.test/*' => Http::sequence()
            ->push(['success' => true, 'data' => ['kiot_order_id' => 10, 'kiot_order_code' => 'K10']], 201)
            ->push(['success' => false, 'error' => ['code' => 'PRODUCT_NOT_SELLABLE', 'message' => 'blocked']], 422)
            ->push(['success' => false, 'error' => ['code' => 'INTERNAL_INTEGRATION_ERROR', 'message' => 'temporary']], 503),
        ]);
        $payload = $this->checkoutPayload($product->id);

        $this->withHeader('X-Cart-Session', 'accepted-cart')->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $replacementCart = Cart::create(['session_id' => 'accepted-cart']);
        $replacementCart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);
        $this->withHeader('X-Cart-Session', 'accepted-cart')->postJson('/api/v1/orders', $payload)->assertOk();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $replacementCart->id, 'product_id' => $product->id]);

        $rejectedCart = Cart::create(['session_id' => 'rejected-cart']);
        $rejectedCart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);
        $this->withHeader('X-Cart-Session', 'rejected-cart')
            ->postJson('/api/v1/orders', $this->checkoutPayload($product->id))->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $rejectedCart->id, 'product_id' => $product->id]);

        $retryCart = Cart::create(['session_id' => 'retry-cart']);
        $retryCart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);
        $this->withHeader('X-Cart-Session', 'retry-cart')
            ->postJson('/api/v1/orders', $this->checkoutPayload($product->id))->assertAccepted();
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $retryCart->id]);
    }

    public function test_admin_product_update_and_csv_import_protect_kiot_authoritative_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Admin products', 'slug' => 'admin-products', 'is_active' => true]);
        $kiot = $this->product([
            'name' => 'KIOT original', 'slug' => 'kiot-original', 'sku' => 'KIOT-ORIGINAL',
            'category_id' => $category->id, 'inventory_source' => 'kiot', 'kiot_product_id' => 99,
            'price' => 2000, 'stock_quantity' => 5, 'barcode' => 'KIOT-BARCODE',
        ]);
        $local = $this->product([
            'name' => 'Local original', 'slug' => 'local-original', 'sku' => 'LOCAL-ORIGINAL',
            'category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 3, 'barcode' => 'LOCAL-BARCODE',
        ]);

        $this->actingAs($admin)->put("/admin/products/{$kiot->id}", [
            'name' => 'KIOT marketing edit', 'slug' => 'kiot-marketing-edit', 'sku' => 'KIOT-CRAFTED',
            'category_id' => $category->id, 'price' => 9999, 'sale_price' => 1800,
            'stock_quantity' => 99, 'barcode' => 'CRAFTED-BARCODE', 'is_active' => true,
            'is_featured' => true, 'warranty_months' => 24,
        ])->assertRedirect(route('admin.products.index'));
        $kiot->refresh();
        $this->assertSame('KIOT-ORIGINAL', $kiot->sku);
        $this->assertSame('2000', $kiot->price);
        $this->assertSame(5, $kiot->stock_quantity);
        $this->assertSame('KIOT-BARCODE', $kiot->barcode);
        $this->assertSame('KIOT marketing edit', $kiot->name);
        $this->assertSame('1800', $kiot->sale_price);

        $header = 'ID,Tên sản phẩm,SKU,Slug,Danh mục,Thương hiệu,Giá gốc,Giá sale,Tồn kho,Trạng thái,Nổi bật,Mô tả ngắn,Thông số KT,Bảo hành (tháng),Ảnh chính,Meta Title,Meta Description,Barcode';
        $csv = implode("\n", [
            $header,
            "{$kiot->id},KIOT CSV edit,KIOT-CSV-CRAFTED,kiot-csv-edit,{$category->name},,7777,1700,77,active,1,Short,Specs,12,,Meta,Description,CSV-KIOT-BARCODE",
            "{$local->id},Local CSV edit,LOCAL-CSV-EDIT,local-csv-edit,{$category->name},,4444,4000,44,active,0,Short,Specs,6,,Meta,Description,CSV-LOCAL-BARCODE",
        ]);
        $response = $this->actingAs($admin)->post('/admin/products-import', [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);
        $response->assertRedirect()->assertSessionHas('success', fn (string $message) => str_contains($message, 'SKU, giá cơ sở, tồn kho và barcode được bỏ qua'));

        $kiot->refresh();
        $this->assertSame('KIOT-ORIGINAL', $kiot->sku);
        $this->assertSame('2000', $kiot->price);
        $this->assertSame(5, $kiot->stock_quantity);
        $this->assertSame('KIOT-BARCODE', $kiot->barcode);
        $this->assertSame('KIOT CSV edit', $kiot->name);
        $local->refresh();
        $this->assertSame('LOCAL-CSV-EDIT', $local->sku);
        $this->assertSame('4444', $local->price);
        $this->assertSame(44, $local->stock_quantity);
        $this->assertSame('CSV-LOCAL-BARCODE', $local->barcode);
    }

    public function test_admin_kiot_page_requires_permission_masks_secret_dispatches_jobs_and_never_retries_rejected_events(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/integrations/kiot')->assertForbidden();
        $admin->givePermissionTo(Permission::create(['name' => 'settings.view', 'guard_name' => 'web']));
        $admin->givePermissionTo(Permission::create(['name' => 'settings.edit', 'guard_name' => 'web']));

        $this->actingAs($admin)->get('/admin/integrations/kiot')
            ->assertOk()
            ->assertSee('kiot.test')
            ->assertDontSee('test-secret');

        Bus::fake([SyncKiotProducts::class, ProcessKiotOutboxEvent::class]);
        $this->actingAs($admin)->post('/admin/integrations/kiot/dry-run')->assertRedirect();
        Bus::assertDispatched(SyncKiotProducts::class, fn (SyncKiotProducts $job) => $job->full && $job->dryRun);

        $event = IntegrationOutboxEvent::create([
            'integration' => 'kiot', 'event_type' => 'order.create', 'aggregate_type' => Order::class,
            'aggregate_id' => 999, 'event_id' => (string) Str::uuid(), 'idempotency_key' => (string) Str::uuid(),
            'payload' => [], 'raw_body' => '{}', 'payload_hash' => hash('sha256', '{}'),
            'status' => 'rejected', 'attempt_count' => 1, 'last_error_code' => 'PRODUCT_NOT_SELLABLE',
        ]);
        $this->actingAs($admin)->post("/admin/integrations/kiot/events/{$event->id}/retry")->assertRedirect();
        $this->assertSame('rejected', $event->fresh()->status);
        Bus::assertNotDispatched(ProcessKiotOutboxEvent::class);
    }

    public function test_payment_sepay_gateway_ipn_fails_closed_without_the_x_secret_key(): void
    {
        config()->set('services.sepay.secret_key', null);
        $this->postJson('/payment/ipn', ['notification_type' => 'ORDER_PAID'])->assertStatus(503);

        config()->set('services.sepay.secret_key', 'gateway-secret');
        $this->withHeader('X-Secret-Key', 'wrong-secret')
            ->postJson('/payment/ipn', ['notification_type' => 'ORDER_PAID'])
            ->assertUnauthorized();
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
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(), 'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC', 'shipping_city' => 'TP. Hồ Chí Minh',
            'shipping_district' => 'Quận 1', 'shipping_ward' => 'Phường 1', 'payment_method' => 'cod',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];
    }
}
