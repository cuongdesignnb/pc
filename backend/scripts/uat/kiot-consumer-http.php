<?php

declare(strict_types=1);

use App\Models\IntegrationOutboxEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\SepayPaymentEvent;
use App\Services\Integrations\Kiot\KiotClient;
use App\Services\Integrations\Kiot\KiotOrderCancellationService;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
if (! app()->environment('testing') || ! str_contains(strtolower($database), 'test')) {
    throw new RuntimeException('Consumer HTTP UAT may only run against an explicitly named testing database.');
}

$baseUrl = rtrim((string) (getenv('PC_UAT_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
if (! in_array($host, ['127.0.0.1', 'localhost', 'pc_consumer_uat_app'], true)) {
    throw new RuntimeException('Consumer HTTP UAT only permits an explicitly local test server.');
}

/**
 * @param  array<string, mixed>|null  $payload
 * @param  list<string>  $headers
 * @return array{status: int, duration_ms: int, body: mixed}
 */
function request(string $baseUrl, string $method, string $path, ?array $payload = null, array $headers = []): array
{
    $handle = curl_init($baseUrl.$path);
    $allHeaders = array_merge(['Accept: application/json'], $headers);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 70,
    ];
    if ($payload !== null) {
        $allHeaders[] = 'Content-Type: application/json';
        $options[CURLOPT_HTTPHEADER] = $allHeaders;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($handle, $options);
    $rawBody = curl_exec($handle);
    if (! is_string($rawBody)) {
        throw new RuntimeException('HTTP request failed: '.curl_error($handle));
    }
    $result = [
        'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
        'duration_ms' => (int) round((float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000),
        'body' => json_decode($rawBody, true),
    ];
    curl_close($handle);

    return $result;
}

/** @param list<int> $expected */
function assertStatus(array $response, array $expected, string $label): void
{
    if (! in_array($response['status'], $expected, true)) {
        throw new RuntimeException("{$label} returned HTTP {$response['status']}: ".json_encode($response['body'], JSON_UNESCAPED_UNICODE));
    }
}

/**
 * @param  list<array<string, mixed>>  $payloads
 * @return list<array{status: int, duration_ms: int, body: mixed}>
 */
function concurrentCheckouts(string $baseUrl, array $payloads): array
{
    $multi = curl_multi_init();
    $handles = [];
    foreach ($payloads as $index => $payload) {
        $handle = curl_init($baseUrl.'/api/v1/orders');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Cart-Session: pc-consumer-concurrency-{$index}",
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 70,
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $responses = [];
    foreach ($handles as $handle) {
        $rawBody = curl_multi_getcontent($handle);
        $responses[] = [
            'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'duration_ms' => (int) round((float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000),
            'body' => json_decode((string) $rawBody, true),
        ];
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);

    return $responses;
}

/** @param array<string, mixed> $result */
function emitResult(array $result): void
{
    $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    $evidenceFile = trim((string) getenv('PC_CONSUMER_EVIDENCE_FILE'));
    if ($evidenceFile !== '') {
        $directory = dirname($evidenceFile);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create evidence directory: {$directory}");
        }
        file_put_contents($evidenceFile, $encoded);
    }
    fwrite(STDOUT, $encoded);
}

/** @return array<string, mixed> */
function checkoutPayload(int $productId, int $quantity, string $paymentMethod = 'cod'): array
{
    return [
        'checkout_idempotency_key' => (string) Str::uuid(),
        'order_access_token' => (string) Str::uuid(),
        'customer_name' => 'PC Consumer UAT',
        'customer_email' => 'pc-consumer-uat@example.test',
        'customer_phone' => '0900000000',
        'shipping_address' => 'UAT address',
        'shipping_city' => 'UAT City',
        'shipping_district' => 'UAT District',
        'shipping_ward' => 'UAT Ward',
        'payment_method' => $paymentMethod,
        'items' => [['product_id' => $productId, 'quantity' => $quantity]],
    ];
}

$mode = (string) ($argv[1] ?? 'UAT-PC-NORMAL-001');
if ($mode === 'duplicate-kiot') {
    $orderId = (int) ($argv[2] ?? 29);
    $event = IntegrationOutboxEvent::query()
        ->where('aggregate_id', $orderId)
        ->where('event_type', 'order.create')
        ->firstOrFail();
    $response = app(KiotClient::class)->createOrder($event->raw_body, $event->idempotency_key);
    if (! $response->successful() || ! $response->duplicate()) {
        throw new RuntimeException('KIOT did not return duplicate success for the frozen create event.');
    }
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'order_id' => $orderId,
        'http_status' => $response->status,
        'duplicate' => true,
        'event_id_stable' => is_string($event->event_id),
        'idempotency_key_stable' => is_string($event->idempotency_key),
        'payload_hash_matches' => hash('sha256', $event->raw_body) === $event->payload_hash,
    ]);
    exit(0);
}

if ($mode === 'outbox-create') {
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    $payload = checkoutPayload((int) $product->id, 1, 'cod');
    $result = app(KiotOrderService::class)->create($payload, null);
    if ($result['duplicate'] || ! is_int($result['outbox_id'])) {
        throw new RuntimeException('Unable to prepare a unique pending outbox event.');
    }
    $event = IntegrationOutboxEvent::findOrFail($result['outbox_id']);
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'order_id' => $result['order']->id,
        'outbox_id' => $event->id,
        'outbox_status' => $event->status,
        'attempt_count' => $event->attempt_count,
        'website_stock_before_workers' => (int) $product->stock_quantity,
    ]);
    exit(0);
}

if ($mode === 'outbox-verify') {
    $orderId = (int) ($argv[2] ?? 0);
    $order = Order::findOrFail($orderId);
    $event = IntegrationOutboxEvent::query()
        ->where('aggregate_id', $orderId)
        ->where('event_type', 'order.create')
        ->firstOrFail();
    if ($event->status !== 'sent' || (int) $event->attempt_count !== 1 || $order->kiot_sync_status !== 'synced') {
        throw new RuntimeException('Concurrent workers did not produce exactly one successful outbox attempt.');
    }
    $order = app(KiotOrderCancellationService::class)->cancel($order, 'PC consumer outbox concurrency cleanup');
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'order_id' => $orderId,
        'create_outbox_id' => $event->id,
        'create_outbox_status' => $event->status,
        'create_attempt_count' => (int) $event->attempt_count,
        'provider_order_id_present' => $order->kiot_order_id !== null,
        'cleanup_status' => $order->kiot_sync_status,
        'website_stock_after_cleanup' => (int) $product->fresh()->stock_quantity,
    ]);
    exit(0);
}

if ($mode === 'invoice-create') {
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    $payload = checkoutPayload((int) $product->id, 2, 'cod');
    $payload['checkout_idempotency_key'] = '33333333-3333-4333-8333-333333333333';
    $payload['order_access_token'] = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    $checkout = request($baseUrl, 'POST', '/api/v1/orders', $payload, ['X-Cart-Session: pc-consumer-invoice']);
    assertStatus($checkout, [200, 201], 'invoice stock-parity checkout');
    if (($checkout['body']['integration_status'] ?? null) !== 'synced') {
        throw new RuntimeException('Invoice stock-parity checkout was not accepted by KIOT.');
    }
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'checkout' => $checkout,
        'website_stock_after_reservation' => (int) $product->fresh()->stock_quantity,
    ]);
    exit(0);
}

if ($mode === 'invoice-verify') {
    $order = Order::where('checkout_idempotency_key', '33333333-3333-4333-8333-333333333333')->firstOrFail();
    $cancel = request(
        $baseUrl,
        'POST',
        "/api/v1/orders/{$order->id}/cancel",
        ['reason' => 'PC consumer verifies invoiced cancellation guard'],
        ['X-Order-Access-Token: cccccccc-cccc-4ccc-8ccc-cccccccccccc'],
    );
    assertStatus($cancel, [422], 'invoiced order cancellation');
    if (($cancel['body']['error_code'] ?? null) !== 'ORDER_ALREADY_INVOICED') {
        throw new RuntimeException('Invoiced order did not return ORDER_ALREADY_INVOICED.');
    }
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    if ((int) $product->kiot_physical_quantity !== 8
        || (int) $product->kiot_reserved_quantity !== 0
        || (int) $product->kiot_available_quantity !== 8
        || (int) $product->stock_quantity !== 8) {
        throw new RuntimeException('Website stock cache does not match the post-invoice provider state.');
    }
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'order_id' => $order->id,
        'cancel_rejection' => $cancel,
        'local_order_status' => $order->fresh()->order_status,
        'local_kiot_sync_status' => $order->fresh()->kiot_sync_status,
        'stock' => [
            'physical' => (int) $product->kiot_physical_quantity,
            'reserved' => (int) $product->kiot_reserved_quantity,
            'available' => (int) $product->kiot_available_quantity,
            'website_cache' => (int) $product->stock_quantity,
            'drift' => (int) $product->stock_quantity - (int) $product->kiot_available_quantity,
        ],
    ]);
    exit(0);
}

if ($mode === 'sepay-early') {
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    $payload = checkoutPayload((int) $product->id, 1, 'sepay');
    $payload['checkout_idempotency_key'] = '22222222-2222-4222-8222-222222222222';
    $payload['order_access_token'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    $checkout = request($baseUrl, 'POST', '/api/v1/orders', $payload, ['X-Cart-Session: pc-consumer-sepay-early']);
    assertStatus($checkout, [200, 202], 'early SePay checkout');
    $orderId = $checkout['body']['order']['id'] ?? null;
    $orderNumber = $checkout['body']['order']['order_number'] ?? null;
    $amount = (int) ($checkout['body']['order']['total'] ?? 0);
    if (! is_int($orderId) || ! is_string($orderNumber) || $amount <= 0 || ($checkout['body']['payment'] ?? null) !== null) {
        throw new RuntimeException('Early SePay checkout did not remain payment-gated while KIOT was unavailable.');
    }
    $callbackPayload = [
        'id' => 2026072001,
        'transferAmount' => $amount,
        'content' => "PC UAT {$orderNumber}",
        'gateway' => 'PC-UAT',
        'referenceNumber' => 'PC-UAT-EARLY-20260720',
        'transactionDate' => gmdate(DATE_ATOM),
    ];
    $callback = request(
        $baseUrl,
        'POST',
        '/api/v1/sepay/callback',
        $callbackPayload,
        ['Authorization: Apikey '.config('services.sepay.webhook_key')],
    );
    $duplicateCallback = request(
        $baseUrl,
        'POST',
        '/api/v1/sepay/callback',
        $callbackPayload,
        ['Authorization: Apikey '.config('services.sepay.webhook_key')],
    );
    assertStatus($callback, [200], 'early SePay callback');
    assertStatus($duplicateCallback, [200], 'duplicate early SePay callback');
    if (($callback['body']['payment_status'] ?? null) !== 'pending_reconciliation'
        || ($duplicateCallback['body']['duplicate'] ?? null) !== true) {
        throw new RuntimeException('Early SePay callback was not persisted idempotently for later reconciliation.');
    }
    $outbox = IntegrationOutboxEvent::where('aggregate_id', $orderId)->latest('id')->firstOrFail();
    $paymentEvent = SepayPaymentEvent::where('external_transaction_id', 2026072001)->firstOrFail();
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'checkout' => $checkout,
        'callback' => $callback,
        'duplicate_callback' => $duplicateCallback,
        'outbox' => [
            'id' => $outbox->id,
            'status' => $outbox->status,
            'attempt_count' => $outbox->attempt_count,
            'next_attempt_at' => $outbox->next_attempt_at,
        ],
        'payment_event' => [
            'id' => $paymentEvent->id,
            'status' => $paymentEvent->status,
            'order_id' => $paymentEvent->order_id,
        ],
    ]);
    exit(0);
}

if ($mode === 'sepay-verify') {
    $order = Order::where('checkout_idempotency_key', '22222222-2222-4222-8222-222222222222')->firstOrFail();
    $payment = request(
        $baseUrl,
        'GET',
        "/api/v1/orders/{$order->id}/check-payment",
        null,
        ['X-Order-Access-Token: bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],
    );
    assertStatus($payment, [200], 'reconciled SePay payment check');
    if (($payment['body']['paid'] ?? null) !== true
        || ($payment['body']['kiot_sync_status'] ?? null) !== 'synced'
        || ($payment['body']['can_pay'] ?? null) !== false) {
        throw new RuntimeException('Early SePay payment was not reconciled after KIOT accepted the order.');
    }
    $paymentEvent = SepayPaymentEvent::where('external_transaction_id', 2026072001)->firstOrFail();
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'order_id' => $order->id,
        'payment_check' => $payment,
        'payment_event_status' => $paymentEvent->status,
        'payment_event_processed_at' => $paymentEvent->processed_at,
    ]);
    exit(0);
}

if (in_array($mode, ['cleanup-low', 'cleanup-sepay'], true)) {
    $order = $mode === 'cleanup-sepay'
        ? Order::where('checkout_idempotency_key', '22222222-2222-4222-8222-222222222222')->first()
        : Order::query()
            ->where('kiot_sync_status', 'synced')
            ->whereHas('items', fn ($query) => $query->where('sku', 'UAT-PC-LOW-001'))
            ->latest('id')
            ->first();
    if ($order) {
        $order = app(KiotOrderCancellationService::class)->cancel($order, 'PC consumer interrupted UAT cleanup');
    }
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'cleaned_order_id' => $order?->id,
        'cleanup_status' => $order?->kiot_sync_status ?? 'not_needed',
    ]);
    exit(0);
}

if ($mode === 'concurrency') {
    $iterations = max(1, min(20, (int) ($argv[2] ?? 5)));
    $product = Product::where('sku', 'UAT-PC-LOW-001')->firstOrFail();
    $runs = [];
    for ($iteration = 1; $iteration <= $iterations; $iteration++) {
        $product->refresh();
        if ((int) $product->stock_quantity !== 1) {
            throw new RuntimeException("Low-stock fixture must have one available unit before iteration {$iteration}.");
        }
        $payloads = [checkoutPayload((int) $product->id, 1), checkoutPayload((int) $product->id, 1)];
        $responses = concurrentCheckouts($baseUrl, $payloads);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        if ($statuses !== [201, 422]) {
            throw new RuntimeException("Concurrency iteration {$iteration} returned unexpected statuses: ".json_encode($responses, JSON_UNESCAPED_UNICODE));
        }

        $acceptedIndex = $responses[0]['status'] === 201 ? 0 : 1;
        $rejectedIndex = 1 - $acceptedIndex;
        $acceptedOrderId = $responses[$acceptedIndex]['body']['order']['id'] ?? null;
        if (! is_int($acceptedOrderId)) {
            throw new RuntimeException("Concurrency iteration {$iteration} did not return an accepted order id.");
        }
        $rejectedCode = $responses[$rejectedIndex]['body']['order']['kiot_sync_error_code']
            ?? $responses[$rejectedIndex]['body']['error_code']
            ?? null;
        if ($rejectedCode !== 'INSUFFICIENT_AVAILABLE_STOCK') {
            throw new RuntimeException("Concurrency iteration {$iteration} returned unexpected rejection {$rejectedCode}.");
        }
        $cancel = request(
            $baseUrl,
            'POST',
            "/api/v1/orders/{$acceptedOrderId}/cancel",
            ['reason' => "PC consumer concurrency cleanup {$iteration}"],
            ['X-Order-Access-Token: '.$payloads[$acceptedIndex]['order_access_token']],
        );
        assertStatus($cancel, [200], "concurrency cleanup {$iteration}");
        $product->refresh();
        if ((int) $product->stock_quantity !== 1) {
            throw new RuntimeException("Low-stock fixture was not restored after iteration {$iteration}.");
        }
        $runs[] = [
            'iteration' => $iteration,
            'statuses' => $statuses,
            'accepted_order_id' => $acceptedOrderId,
            'rejected_error_code' => $rejectedCode,
            'request_duration_ms' => array_column($responses, 'duration_ms'),
            'post_cancel_available_quantity' => (int) $product->stock_quantity,
        ];
    }
    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'sku' => $product->sku,
        'iterations' => $iterations,
        'passed' => count($runs) === $iterations,
        'runs' => $runs,
    ]);
    exit(0);
}

if ($mode === 'duplicate-concurrency') {
    // Keep this isolated test run from colliding with external order IDs left by an
    // earlier reset of the disposable consumer database.
    DB::statement('ALTER TABLE orders AUTO_INCREMENT = 900000000');
    $product = Product::where('sku', 'UAT-PC-NORMAL-001')->firstOrFail();
    $payload = checkoutPayload((int) $product->id, 1);
    $responses = concurrentCheckouts($baseUrl, [$payload, $payload]);
    $statuses = array_column($responses, 'status');
    sort($statuses);
    if ($statuses !== [200, 201]) {
        throw new RuntimeException('Concurrent duplicate checkout returned unexpected statuses: '.json_encode($responses, JSON_UNESCAPED_UNICODE));
    }

    $orderIds = array_values(array_unique(array_map(
        static fn (array $response): int => (int) ($response['body']['order']['id'] ?? 0),
        $responses,
    )));
    if (count($orderIds) !== 1 || $orderIds[0] <= 0) {
        throw new RuntimeException('Concurrent duplicate checkout did not converge on one website order.');
    }

    $orderId = $orderIds[0];
    $orderCount = Order::where('checkout_idempotency_key', $payload['checkout_idempotency_key'])->count();
    $createEventCount = IntegrationOutboxEvent::where('aggregate_id', $orderId)
        ->where('event_type', 'order.create')
        ->count();
    if ($orderCount !== 1 || $createEventCount !== 1) {
        throw new RuntimeException('Concurrent duplicate checkout created duplicate local order or outbox state.');
    }

    $cancel = request(
        $baseUrl,
        'POST',
        "/api/v1/orders/{$orderId}/cancel",
        ['reason' => 'PC consumer concurrent duplicate cleanup'],
        ['X-Order-Access-Token: '.$payload['order_access_token']],
    );
    assertStatus($cancel, [200], 'concurrent duplicate cleanup');

    emitResult([
        'environment' => app()->environment(),
        'database' => $database,
        'base_url' => $baseUrl,
        'statuses' => $statuses,
        'order_ids' => $orderIds,
        'local_order_count' => $orderCount,
        'create_outbox_count' => $createEventCount,
        'request_duration_ms' => array_column($responses, 'duration_ms'),
        'cleanup_status' => $cancel['body']['order']['kiot_sync_status'] ?? null,
        'website_stock_after_cleanup' => (int) $product->fresh()->stock_quantity,
    ]);
    exit(0);
}

$sku = $mode;
$quantity = max(1, (int) ($argv[2] ?? 1));
$paymentMethod = (string) ($argv[3] ?? 'cod');
$product = Product::where('sku', $sku)->firstOrFail();
$payload = checkoutPayload((int) $product->id, $quantity, $paymentMethod);
$checkout = request($baseUrl, 'POST', '/api/v1/orders', $payload, ['X-Cart-Session: pc-consumer-uat']);
assertStatus($checkout, [201, 202, 422], 'checkout');

$orderId = $checkout['body']['order']['id'] ?? null;
$result = [
    'environment' => app()->environment(),
    'database' => $database,
    'base_url' => $baseUrl,
    'sku' => $sku,
    'product_id' => $product->id,
    'checkout' => $checkout,
];

if (is_int($orderId)) {
    $duplicate = request($baseUrl, 'POST', '/api/v1/orders', $payload, ['X-Cart-Session: pc-consumer-uat']);
    $missingToken = request($baseUrl, 'GET', "/api/v1/orders/{$orderId}");
    $wrongToken = request($baseUrl, 'GET', "/api/v1/orders/{$orderId}", null, ['X-Order-Access-Token: '.Str::uuid()]);
    $correctToken = request($baseUrl, 'GET', "/api/v1/orders/{$orderId}", null, ['X-Order-Access-Token: '.$payload['order_access_token']]);
    $payment = request($baseUrl, 'GET', "/api/v1/orders/{$orderId}/check-payment", null, ['X-Order-Access-Token: '.$payload['order_access_token']]);
    assertStatus($duplicate, [200, 422], 'duplicate checkout');
    assertStatus($missingToken, [404], 'missing guest token');
    assertStatus($wrongToken, [404], 'wrong guest token');
    assertStatus($correctToken, [200], 'correct guest token');
    assertStatus($payment, [200], 'check payment');
    $result['duplicate'] = $duplicate;
    $result['guest_access'] = [
        'missing_token_status' => $missingToken['status'],
        'wrong_token_status' => $wrongToken['status'],
        'correct_token' => $correctToken,
        'check_payment' => $payment,
    ];

    if (($checkout['body']['integration_status'] ?? null) === 'synced') {
        $cancel = request(
            $baseUrl,
            'POST',
            "/api/v1/orders/{$orderId}/cancel",
            ['reason' => 'PC consumer UAT cleanup'],
            ['X-Order-Access-Token: '.$payload['order_access_token']],
        );
        assertStatus($cancel, [200], 'cancel');
        $result['cancel'] = $cancel;
    }
}

emitResult($result);
