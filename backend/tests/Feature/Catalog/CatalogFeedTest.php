<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\CatalogChannelException;
use App\Models\CatalogChannelConnection;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedBuilder;
use App\Services\Catalog\GoogleMerchant\GoogleMerchantFeedValidator;
use App\Services\Catalog\Meta\MetaCatalogCsvRenderer;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use App\Services\Catalog\Meta\MetaCatalogFeedValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('f', 32)),
            'catalog.storefront_url' => 'https://laptopplus.test',
            'catalog.feed_disk' => 'local',
            'catalog.feed_directory' => 'catalog-feed-tests',
        ]);
        Storage::fake('local');
    }

    public function test_google_xml_and_meta_csv_include_only_eligible_products_and_preserve_vietnamese(): void
    {
        $category = $this->category();
        $valid = $this->product($category, 1, ['name' => 'Laptop Đồ họa, cao cấp']);
        $valid->images()->create(['url' => 'https://cdn.laptopplus.test/valid.jpg', 'is_primary' => true]);
        $repairing = $this->product($category, 2, [
            'sku' => 'REPAIR-2', 'slug' => 'repair-2', 'kiot_is_under_repair' => true,
            'kiot_availability_status' => 'repairing',
        ]);
        $repairing->images()->create(['url' => 'https://cdn.laptopplus.test/repair.jpg', 'is_primary' => true]);
        $zero = $this->product($category, 3, ['sku' => 'ZERO-3', 'slug' => 'zero-3', 'price' => 0]);
        $zero->images()->create(['url' => 'https://cdn.laptopplus.test/zero.jpg', 'is_primary' => true]);
        $this->product($category, 4, ['sku' => 'NOIMAGE-4', 'slug' => 'no-image-4']);
        $hidden = $this->product($category, 5, ['sku' => 'HIDDEN-5', 'slug' => 'hidden-5', 'show_on_pc_website' => false]);
        $hidden->images()->create(['url' => 'https://cdn.laptopplus.test/hidden.jpg', 'is_primary' => true]);

        $google = app(GoogleMerchantFeedBuilder::class)->build();
        $meta = app(MetaCatalogFeedBuilder::class)->build();

        $this->assertSame(2, $google['VALID_PRODUCTS']);
        $this->assertSame(3, $google['INVALID_PRODUCTS']);
        $this->assertSame(2, $meta['VALID_PRODUCTS']);

        $xml = Storage::disk('local')->get('catalog-feed-tests/google-products.xml');
        $this->assertStringContainsString('Laptop Đồ họa, cao cấp', $xml);
        $this->assertStringContainsString('<g:id>kiot:1</g:id>', $xml);
        $this->assertStringContainsString('<g:id>kiot:2</g:id>', $xml);
        $this->assertStringContainsString('<g:availability>out_of_stock</g:availability>', $xml);
        $this->assertStringNotContainsString('kiot:3', $xml);
        $this->assertStringNotContainsString('kiot:4', $xml);
        $this->assertStringNotContainsString('kiot:5', $xml);

        $csv = Storage::disk('local')->get('catalog-feed-tests/meta-products.csv');
        $this->assertStringContainsString('Laptop Đồ họa, cao cấp', $csv);
        $this->assertStringContainsString('UNDER_REPAIR', $csv);
        $this->assertSame(MetaCatalogCsvRenderer::HEADERS, str_getcsv(strtok($csv, "\n"), ',', '"', ''));
    }

    public function test_feed_validators_reject_duplicate_ids(): void
    {
        $xmlPath = Storage::disk('local')->path('duplicate.xml');
        Storage::disk('local')->put('duplicate.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0"><channel>
<item><g:id>x</g:id><g:title>A</g:title><g:link>https://example.com/a</g:link><g:image_link>https://example.com/a.jpg</g:image_link><g:price>1 VND</g:price></item>
<item><g:id>x</g:id><g:title>B</g:title><g:link>https://example.com/b</g:link><g:image_link>https://example.com/b.jpg</g:image_link><g:price>1 VND</g:price></item>
</channel></rss>
XML);
        try {
            app(GoogleMerchantFeedValidator::class)->validate($xmlPath);
            $this->fail('Duplicate XML IDs must fail validation.');
        } catch (CatalogChannelException $exception) {
            $this->assertSame('FEED_INVALID_XML', $exception->errorCode);
        }

        $csvPath = Storage::disk('local')->path('duplicate.csv');
        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, MetaCatalogCsvRenderer::HEADERS, ',', '"', '');
        $row = ['x', 'A', 'A', 'in stock', 'new', '1 VND', 'https://example.com/a', 'https://example.com/a.jpg', '', '', 1, '', '', 'active', 'KIOT', ''];
        fputcsv($handle, $row, ',', '"', '');
        fputcsv($handle, $row, ',', '"', '');
        fclose($handle);
        $this->expectException(CatalogChannelException::class);
        app(MetaCatalogFeedValidator::class)->validate($csvPath);
    }

    public function test_feed_requires_rotatable_token_and_supports_http_cache_headers(): void
    {
        $category = $this->category();
        $product = $this->product($category, 10);
        $product->images()->create(['url' => 'https://cdn.laptopplus.test/image.jpg', 'is_primary' => true]);
        app(GoogleMerchantFeedBuilder::class)->build();

        $connection = CatalogChannelConnection::where('channel', CatalogChannelConnection::GOOGLE_MERCHANT)->firstOrFail();
        $connection->update([
            'status' => 'configured',
            'is_enabled' => true,
            'configuration_encrypted' => [],
        ]);
        $token = app(CatalogChannelManager::class)->rotateFeedToken($connection);

        $this->get('/feeds/google/products.xml')->assertNotFound();
        $this->get('/feeds/google/products.xml?token=wrong')->assertNotFound();
        $response = $this->get('/feeds/google/products.xml?token='.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $etag = $response->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)
            ->get('/feeds/google/products.xml?token='.$token)
            ->assertStatus(304);
    }

    private function category(): Category
    {
        return Category::create([
            'provider' => 'kiot', 'remote_category_id' => 1, 'name' => 'Laptop', 'slug' => 'laptop',
            'is_active' => true, 'show_on_pc_website' => true, 'provider_sync_status' => 'active',
        ]);
    }

    private function product(Category $category, int $remoteId, array $overrides = []): Product
    {
        return Product::create($overrides + [
            'category_id' => $category->id,
            'provider' => 'kiot',
            'remote_product_id' => $remoteId,
            'name' => 'Laptop '.$remoteId,
            'slug' => 'laptop-'.$remoteId,
            'sku' => 'SKU-'.$remoteId,
            'price' => 10000000,
            'stock_quantity' => 2,
            'inventory_source' => 'kiot',
            'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available',
            'kiot_sellable' => true,
            'kiot_available_quantity' => 2,
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }
}
