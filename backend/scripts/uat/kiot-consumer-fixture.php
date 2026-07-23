<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
if (! app()->environment('testing') || ! str_contains(strtolower($database), 'test')) {
    throw new RuntimeException('Consumer fixtures may only modify an explicitly named testing database.');
}

$category = Category::firstOrCreate(
    ['slug' => 'uat-kiot-products'],
    ['name' => 'UAT KIOT Products', 'description' => 'Consumer verification only', 'is_active' => true],
);
$brand = Brand::firstOrCreate(
    ['slug' => 'uat-kiot-brand'],
    ['name' => 'UAT KIOT Brand', 'is_active' => true],
);

$skus = [
    'UAT-PC-NORMAL-001',
    'UAT-PC-SERIAL-001',
    'UAT-PC-LOW-001',
    'UAT-PC-INACTIVE-001',
    'UAT-PC-NOTSELL-001',
    'UAT-PC-Case-Abc',
    'UAT-PC-DELETED-001',
    'UAT-PC-SERVICE-001',
    'UAT-PC-REMOTE-MISSING-001',
];
if (filter_var(getenv('PC_UAT_INCLUDE_ZERO') ?: false, FILTER_VALIDATE_BOOL)) {
    $skus[] = 'UAT-PC-ZERO-001';
}

$products = collect($skus)->values()->map(function (string $sku, int $index) use ($category, $brand): Product {
    $slug = 'uat-kiot-'.strtolower(str_replace(['/', ' '], '-', $sku));
    $product = Product::updateOrCreate(['sku' => $sku], [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => "Website marketing {$sku}",
        'slug' => $slug,
        'short_description' => "Short content {$sku}",
        'description' => "SEO marketing content preserved for {$sku}",
        'price' => 111000 + $index,
        'sale_price' => 700000 - $index,
        'cost_price' => 123456 + $index,
        'stock_quantity' => 3,
        'is_active' => true,
        'is_featured' => $index % 2 === 0,
        'weight' => 321 + $index,
        'warranty_months' => 6,
        'specifications_text' => "Website specification {$sku}",
        'meta_title' => "Website SEO {$sku}",
        'meta_description' => "Website meta description {$sku}",
        'inventory_source' => 'local',
        'kiot_sync_status' => 'unmapped',
        'kiot_sellable' => false,
        'kiot_sync_error_code' => null,
        'kiot_sync_error_message' => null,
    ]);
    $product->images()->updateOrCreate(
        ['is_primary' => true],
        ['url' => "https://example.test/images/{$slug}.jpg", 'alt_text' => "Website image {$sku}", 'sort_order' => 0],
    );

    return $product->fresh('images');
});

$snapshot = $products->map(fn (Product $product) => [
    'id' => $product->id,
    'sku' => $product->sku,
    'inventory_source' => $product->inventory_source,
    'price' => (int) $product->price,
    'sale_price' => (int) $product->sale_price,
    'cost_price' => (int) $product->cost_price,
    'stock_quantity' => (int) $product->stock_quantity,
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
])->all();

$encoded = json_encode([
    'environment' => app()->environment(),
    'connection' => $connection,
    'database' => $database,
    'case_variant_rows_supported' => false,
    'case_variant_note' => 'The products SKU unique collation is case-insensitive; wrong-case matching is verified through provider requests and consumer tests.',
    'product_count' => count($snapshot),
    'products' => $snapshot,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
$evidenceFile = trim((string) getenv('PC_CONSUMER_EVIDENCE_FILE'));
if ($evidenceFile !== '') {
    $directory = dirname($evidenceFile);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create evidence directory: {$directory}");
    }
    file_put_contents($evidenceFile, $encoded);
}
fwrite(STDOUT, $encoded);
