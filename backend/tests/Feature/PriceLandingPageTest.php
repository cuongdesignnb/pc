<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceLandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.vn');
        $laptops = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $dell = Category::create(['name' => 'Dell', 'slug' => 'dell', 'parent_id' => $laptops->id, 'is_active' => true]);
        $other = Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true]);

        $make = fn (string $sku, Category $category, int $price, int $stock) => Product::create([
            'name' => 'Máy '.$sku, 'slug' => strtolower($sku), 'sku' => $sku, 'category_id' => $category->id,
            'price' => $price, 'stock_quantity' => $stock, 'is_active' => true, 'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ]);
        $make('CHEAP', $dell, 4_500_000, 1);
        $make('CHEAP-SOLD', $dell, 4_000_000, 0);
        $make('MID', $laptops, 6_500_000, 2);
        $make('HIGH', $laptops, 12_000_000, 1);
        $make('CABLE', $other, 300_000, 5);
    }

    public function test_price_page_has_its_own_seo_and_lists_in_stock_products_in_range(): void
    {
        $response = $this->getJson('/api/v1/categories/laptop-duoi-5-trieu')->assertOk()
            ->assertJsonPath('category.name', 'Laptop cũ dưới 5 triệu')
            ->assertJsonPath('category.canonical_path', '/laptop-duoi-5-trieu')
            ->assertJsonPath('category.canonical_url', 'https://laptopplus.vn/laptop-duoi-5-trieu')
            ->assertJsonPath('category.meta_title', 'Laptop cũ dưới 5 triệu giá rẻ, còn hàng')
            ->assertJsonPath('category.ancestors.0.slug', 'laptop-cu')
            ->assertJsonPath('landing.max_price', 4999999)
            ->assertJsonPath('products.total', 1)
            ->assertJsonPath('products.data.0.sku', 'CHEAP');
        $this->assertCount(3, $response->json('filters.price_landings'));

        $this->getJson('/api/v1/categories/laptop-tu-5-den-8-trieu')->assertOk()
            ->assertJsonPath('products.total', 1)->assertJsonPath('products.data.0.sku', 'MID');
        $this->getJson('/api/v1/categories/laptop-tren-8-trieu')->assertOk()
            ->assertJsonPath('products.data.0.sku', 'HIGH');

        $this->getJson('/api/v1/categories/laptop-cu')->assertOk()
            ->assertJsonPath('landing', null)
            ->assertJsonPath('filters.price_landings.0.path', '/laptop-duoi-5-trieu');
    }

    public function test_price_pages_are_in_the_sitemap(): void
    {
        $xml = $this->get('/sitemaps/categories.xml')->assertOk()->getContent();
        foreach (['laptop-duoi-5-trieu', 'laptop-tu-5-den-8-trieu', 'laptop-tren-8-trieu'] as $slug) {
            $this->assertStringContainsString('<loc>https://laptopplus.vn/'.$slug.'</loc>', $xml);
        }
    }

    public function test_product_api_in_stock_filter_hides_sold_out_products(): void
    {
        $skus = collect($this->getJson('/api/v1/products?in_stock=1&per_page=50')->assertOk()->json('data'))->pluck('sku');
        $this->assertContains('CHEAP', $skus);
        $this->assertNotContains('CHEAP-SOLD', $skus);
        $this->assertContains('CHEAP-SOLD', collect($this->getJson('/api/v1/products?per_page=50')->json('data'))->pluck('sku'));
    }
}
