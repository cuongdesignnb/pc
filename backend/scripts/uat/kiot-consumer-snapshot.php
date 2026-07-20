<?php

declare(strict_types=1);

use App\Models\IntegrationOutboxEvent;
use App\Models\IntegrationSyncState;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
if (! app()->environment('testing') || ! str_contains(strtolower($database), 'test')) {
    throw new RuntimeException('Consumer snapshots may only read an explicitly named testing database.');
}

$products = Product::query()
    ->where('sku', 'like', 'UAT-PC-%')
    ->orWhere('sku', 'like', 'uat-pc-%')
    ->with('images')
    ->orderBy('sku')
    ->get()
    ->map(fn (Product $product) => [
        'id' => $product->id,
        'sku' => $product->sku,
        'inventory_source' => $product->inventory_source,
        'price' => (int) $product->price,
        'sale_price' => (int) $product->sale_price,
        'cost_price' => (int) $product->cost_price,
        'stock_quantity' => (int) $product->stock_quantity,
        'kiot_sellable' => (bool) $product->kiot_sellable,
        'kiot_physical_quantity' => $product->kiot_physical_quantity,
        'kiot_reserved_quantity' => $product->kiot_reserved_quantity,
        'kiot_available_quantity' => $product->kiot_available_quantity,
        'kiot_sync_status' => $product->kiot_sync_status,
        'marketing_hash' => hash('sha256', json_encode([
            $product->slug,
            $product->category_id,
            $product->brand_id,
            $product->description,
            $product->short_description,
            $product->meta_title,
            $product->meta_description,
            $product->specifications_text,
            $product->is_featured,
            $product->images->pluck('url')->all(),
        ], JSON_THROW_ON_ERROR)),
    ])->values();

$snapshot = [
    'captured_at_utc' => gmdate(DATE_ATOM),
    'environment' => app()->environment(),
    'connection' => $connection,
    'database' => $database,
    'product_checksum' => hash('sha256', json_encode($products, JSON_THROW_ON_ERROR)),
    'products' => $products->all(),
    'sync_state' => IntegrationSyncState::where(['integration' => 'kiot', 'resource' => 'products'])->first()?->toArray(),
    'orders' => [
        'total' => Order::count(),
        'synced' => Order::where('kiot_sync_status', 'synced')->count(),
        'retrying' => Order::where('kiot_sync_status', 'retrying')->count(),
        'rejected' => Order::where('kiot_sync_status', 'rejected')->count(),
        'cancelled' => Order::where('kiot_sync_status', 'cancelled')->count(),
    ],
    'outbox' => IntegrationOutboxEvent::query()
        ->selectRaw('status, COUNT(*) as aggregate_count')
        ->groupBy('status')
        ->pluck('aggregate_count', 'status')
        ->all(),
];

$encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
$evidenceFile = trim((string) getenv('PC_CONSUMER_EVIDENCE_FILE'));
if ($evidenceFile !== '') {
    $directory = dirname($evidenceFile);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create evidence directory: {$directory}");
    }
    file_put_contents($evidenceFile, $encoded);
}
fwrite(STDOUT, $encoded);
