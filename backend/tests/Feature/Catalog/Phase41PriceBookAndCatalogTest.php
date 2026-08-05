<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogPriceBook;
use App\Models\CatalogProductPrice;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\Pricing\CatalogChannelPriceResolver;
use App\Services\Catalog\Pricing\CatalogChannelPriceSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase41PriceBookAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_price_source_and_explicit_fallback_are_resolved_without_fake_values(): void
    {
        $book = CatalogPriceBook::create([
            'provider' => 'kiot', 'remote_price_book_id' => 101, 'name' => 'Partner', 'is_active' => true,
            'currency' => 'VND', 'checksum' => str_repeat('a', 64),
        ]);
        $product = $this->product();
        CatalogProductPrice::create([
            'product_id' => $product->id, 'price_book_id' => $book->id, 'price_source' => 'price_book',
            'price' => 9900000, 'currency' => 'VND', 'checksum' => str_repeat('b', 64), 'synced_at' => now(),
        ]);
        app(CatalogChannelPriceSettingsService::class)->update('meta_catalog', 'price_book:'.$book->id, 'none');

        $resolved = app(CatalogChannelPriceResolver::class)->resolve($product->fresh(), 'meta_catalog');
        $this->assertSame(9900000, $resolved['value']);
        $this->assertFalse($resolved['fallback_used']);

        app(CatalogChannelPriceSettingsService::class)->update('meta_catalog', 'price_book:'.$book->id, 'retail_price');
        $missing = app(CatalogChannelPriceResolver::class)->resolve($this->product(), 'meta_catalog');
        $this->assertSame(15000000, $missing['value']);
        $this->assertSame('PRICE_FALLBACK_USED', $missing['issue']);
    }

    public function test_projection_exposes_retail_all_book_and_channel_prices(): void
    {
        $book = CatalogPriceBook::create([
            'provider' => 'kiot', 'remote_price_book_id' => 102, 'name' => 'Retailer', 'is_active' => true,
            'currency' => 'VND', 'checksum' => str_repeat('c', 64),
        ]);
        $product = $this->product();
        CatalogProductPrice::create([
            'product_id' => $product->id, 'price_book_id' => $book->id, 'price_source' => 'price_book',
            'price' => 12000000, 'currency' => 'VND', 'checksum' => str_repeat('d', 64), 'synced_at' => now(),
        ]);
        $projection = app(CatalogProductProjectionService::class)->project($product->fresh());

        $this->assertSame(15000000, $projection->price);
        $this->assertSame(12000000, $projection->priceBooks[$book->id]);
        $this->assertArrayHasKey('google_sheets', $projection->selectedChannelPrices);
    }

    private function product(): Product
    {
        $category = Category::create([
            'provider' => 'kiot', 'remote_category_id' => random_int(1, 100000), 'name' => 'Laptop', 'slug' => 'laptop-'.uniqid(),
            'is_active' => true, 'show_on_pc_website' => true, 'provider_sync_status' => 'active',
        ]);

        return Product::create([
            'category_id' => $category->id, 'provider' => 'kiot', 'remote_product_id' => random_int(1, 100000),
            'name' => 'Laptop', 'slug' => 'laptop-'.uniqid(), 'sku' => 'SKU-'.uniqid(), 'price' => 15000000,
            'stock_quantity' => 1, 'inventory_source' => 'kiot', 'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available', 'kiot_sellable' => true, 'kiot_available_quantity' => 1,
            'kiot_retail_price' => 15000000, 'kiot_selected_price' => 14000000, 'is_active' => true, 'show_on_pc_website' => true,
        ]);
    }
}
