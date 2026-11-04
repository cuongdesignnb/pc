<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\Seo\ProductStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.vn');
        Carbon::setTestNow('2026-11-06 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_product_detail_returns_condition_seller_warranty_returns_shipping_and_reviews(): void
    {
        $used = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $dell = Category::create(['name' => 'Dell', 'slug' => 'dell', 'parent_id' => $used->id, 'is_active' => true]);
        $brand = Brand::create(['name' => 'Dell', 'slug' => 'dell', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Dell Latitude 7420', 'slug' => 'dell-latitude-7420', 'sku' => 'DELL-7420', 'brand_id' => $brand->id,
            'category_id' => $dell->id, 'price' => 9_000_000, 'stock_quantity' => 2, 'warranty_months' => 6,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
        Review::create(['product_id' => $product->id, 'guest_name' => 'Anh Minh', 'rating' => 5, 'body' => 'Máy đẹp', 'is_approved' => true]);
        Review::create(['product_id' => $product->id, 'guest_name' => 'Spam', 'rating' => 1, 'body' => 'x', 'is_approved' => false]);

        $data = $this->getJson('/api/v1/products/dell-latitude-7420')->assertOk()->json('product.structured_data');

        $this->assertSame(ProductStructuredData::USED, $data['item_condition']);
        $this->assertSame('2026-12-31', $data['price_valid_until']);
        $this->assertSame(['@type' => 'Organization', 'name' => 'Laptop Plus', 'url' => 'https://laptopplus.vn/'], $data['seller']);
        $this->assertSame(6, $data['warranty']['durationOfWarranty']['value']);
        $this->assertSame(7, $data['return_policy']['merchantReturnDays']);
        $this->assertSame('https://laptopplus.vn/chinh-sach-giao-hang-doi-tra-va-hoan-tien', $data['return_policy']['merchantReturnLink']);
        $this->assertSame(0, $data['shipping_details']['shippingRate']['value']);
        $this->assertSame('VN', $data['shipping_details']['shippingDestination']['addressCountry']);
        $this->assertCount(1, $data['reviews']);
        $this->assertSame('Anh Minh', $data['reviews'][0]['author']['name']);
        $this->assertSame(5, $data['reviews'][0]['reviewRating']['ratingValue']);

        // In December the date moves to the end of next year.
        Carbon::setTestNow('2026-12-15 10:00:00');
        $this->assertSame('2027-12-31', app(ProductStructuredData::class)->forProduct($product->fresh(), 9_000_000)['price_valid_until']);
    }

    public function test_new_products_and_cheap_items_get_new_condition_and_a_shipping_fee(): void
    {
        $accessories = Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true]);
        $cable = Product::create([
            'name' => 'Cáp Ugreen USB-C mới chính hãng', 'slug' => 'cap-ugreen', 'sku' => 'UG-1', 'category_id' => $accessories->id,
            'price' => 200_000, 'stock_quantity' => 3, 'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);

        $data = $this->getJson('/api/v1/products/cap-ugreen')->assertOk()->json('product.structured_data');
        $this->assertSame(ProductStructuredData::NEW, $data['item_condition']);
        $this->assertSame(12, $data['warranty']['durationOfWarranty']['value']); // the default warranty
        $this->assertSame(30000, $data['shipping_details']['shippingRate']['value']);
        $this->assertSame([], $data['reviews']);

        $cable->update(['name' => 'Bàn phím cũ Dell']);
        $this->assertSame(ProductStructuredData::USED, app(ProductStructuredData::class)->condition($cable->fresh()));
        $this->assertNull($data['brand']);
    }

    public function test_brand_falls_back_to_the_category_or_the_name(): void
    {
        Brand::create(['name' => 'Dell', 'slug' => 'dell', 'is_active' => true]);
        Brand::create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
        $used = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $dell = Category::create(['name' => 'Laptop cũ Dell', 'slug' => 'laptop-cu-dell', 'parent_id' => $used->id, 'is_active' => true]);
        $make = fn (string $name, Category $category) => Product::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'sku' => \Illuminate\Support\Str::random(8), 'category_id' => $category->id,
            'price' => 5_000_000, 'stock_quantity' => 1, 'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
        $structured = app(ProductStructuredData::class);

        $this->assertSame('Dell', $structured->brandName($make('Latitude 7420 i5', $dell)));
        $this->assertSame('Apple', $structured->brandName($make('Apple Macbook Pro 2020', $used)));
        $this->assertNull($structured->brandName($make('Máy lạ', $used)));
    }
}
