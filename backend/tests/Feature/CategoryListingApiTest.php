<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryListingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_listing_returns_dynamic_cards_filters_banner_and_recommendations(): void
    {
        $category = $this->category('linh-kien', 'Linh kiện máy tính', [
            'description' => 'Danh mục linh kiện chính hãng.',
            'image' => '/storage/media/linh-kien.webp',
        ]);
        $vga = $this->category('vga', 'VGA - Card màn hình', ['parent_id' => $category->id]);
        $nvidia = Brand::create([
            'name' => 'NVIDIA',
            'slug' => 'nvidia',
            'logo' => '/storage/brands/nvidia.svg',
            'is_active' => true,
        ]);

        $featured = $this->product($category, [
            'brand_id' => $nvidia->id,
            'name' => 'Card màn hình NVIDIA RTX Test',
            'price' => 19000000,
            'sale_price' => 15990000,
            'sold_count' => 120,
            'specifications_text' => 'Bộ nhớ: 12GB\nSocket: AM5',
        ]);
        $featured->images()->create([
            'url' => '/storage/media/rtx-test.webp',
            'alt_text' => 'RTX test',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        ProductVariant::create([
            'product_id' => $featured->id,
            'name' => '12GB',
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'price' => 15990000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        $reviewer = \App\Models\User::factory()->create();
        Review::create([
            'user_id' => $reviewer->id,
            'product_id' => $featured->id,
            'rating' => 4,
            'body' => 'Đánh giá đã duyệt.',
            'is_approved' => true,
        ]);
        Review::create([
            'user_id' => $reviewer->id,
            'product_id' => $featured->id,
            'rating' => 5,
            'body' => 'Đánh giá thứ hai.',
            'is_approved' => true,
        ]);
        Review::create([
            'user_id' => $reviewer->id,
            'product_id' => $featured->id,
            'rating' => 1,
            'body' => 'Chưa được duyệt.',
            'is_approved' => false,
        ]);

        $recommendation = $this->product($vga, [
            'name' => 'Card màn hình đề xuất',
            'sold_count' => 80,
        ]);
        $this->product($vga, ['show_on_pc_website' => false]);

        $priceFilter = Filter::create([
            'name' => 'Khoảng giá',
            'slug' => 'khoang-gia',
            'type' => 'price_range',
            'match_field' => 'price',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        FilterValue::create([
            'filter_id' => $priceFilter->id,
            'label' => '10.000.000đ - 20.000.000đ',
            'slug' => '10-20',
            'price_min' => 10000000,
            'price_max' => 20000000,
            'is_active' => true,
        ]);
        $category->filters()->attach($priceFilter->id, ['sort_order' => 0]);

        $filter = Filter::create([
            'name' => 'Socket',
            'slug' => 'socket',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        FilterValue::create([
            'filter_id' => $filter->id,
            'label' => 'AM5',
            'slug' => 'am5',
            'match_value' => 'AM5',
            'is_active' => true,
        ]);
        $category->filters()->attach($filter->id, ['sort_order' => 1]);

        $brandFilter = Filter::create([
            'name' => 'Hãng sản xuất',
            'slug' => 'thuong-hieu',
            'type' => 'checkbox',
            'match_field' => 'brand',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        FilterValue::create([
            'filter_id' => $brandFilter->id,
            'label' => 'NVIDIA',
            'slug' => 'nvidia',
            'match_value' => 'nvidia',
            'is_active' => true,
        ]);
        $category->filters()->attach($brandFilter->id, ['sort_order' => 2]);

        Banner::create([
            'title' => 'Siêu sale linh kiện',
            'description' => 'Nâng cấp hiệu năng cho dàn máy.',
            'badge' => 'KHUYẾN MÃI',
            'image' => '/storage/banners/category.webp',
            'link' => '/vga',
            'position' => 'category',
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => [
                'category_slug' => ['linh-kien'],
                'cta_label' => 'Xem ngay',
            ],
        ]);

        $response = $this->getJson('/api/v1/categories/linh-kien?sort=rating&per_page=1')
            ->assertOk()
            ->assertJsonPath('category.slug', 'linh-kien')
            ->assertJsonPath('category.children.0.slug', 'vga')
            ->assertJsonPath('category.children.0.product_count', 1)
            ->assertJsonPath('promo_banner.title', 'Siêu sale linh kiện')
            ->assertJsonPath('promo_banner.metadata.cta_label', 'Xem ngay')
            ->assertJsonPath('filters.price_presets.0.label', '10.000.000đ - 20.000.000đ')
            ->assertJsonPath('filters.price_presets.0.min', 10000000)
            ->assertJsonPath('filters.price_presets.0.max', 20000000)
            ->assertJsonPath('products.current_page', 1)
            ->assertJsonPath('products.per_page', 1)
            ->assertJsonPath('products.total', 2)
            ->assertJsonPath('products.data.0.id', $featured->id)
            ->assertJsonPath('products.data.0.rating.average', 4.5)
            ->assertJsonPath('products.data.0.rating.count', 2)
            ->assertJsonPath('products.data.0.has_variants', true)
            ->assertJsonPath('recommendations.0.id', $recommendation->id)
            ->assertJsonStructure([
                'category' => ['id', 'name', 'slug', 'children'],
                'promo_banner' => ['id', 'title', 'image', 'metadata'],
                'products' => ['data', 'current_page', 'last_page', 'per_page', 'total'],
                'recommendations',
                'filters' => [
                    'brands',
                    'price_range' => ['min', 'max'],
                    'price_presets' => [['key', 'label', 'min', 'max']],
                    'groups' => [['id', 'name', 'slug', 'values']],
                    'specs',
                ],
            ]);

        $card = $response->json('products.data.0');
        $this->assertArrayNotHasKey('cost_price', $card);
        $this->assertArrayNotHasKey('provider', $card);
        $this->assertArrayNotHasKey('inventory_source', $card);
        $this->assertArrayNotHasKey('stock_quantity', $card);
        $this->assertSame('Socket', $response->json('filters.groups.0.name'));
        $this->assertCount(1, $response->json('filters.groups'));
        $this->assertSame(1, $response->json('filters.groups.0.values.0.count'));
    }

    public function test_category_listing_applies_brand_price_stock_and_dynamic_filters(): void
    {
        $category = $this->category('linh-kien', 'Linh kiện máy tính');
        $nvidia = Brand::create(['name' => 'NVIDIA', 'slug' => 'nvidia', 'is_active' => true]);
        $amd = Brand::create(['name' => 'AMD', 'slug' => 'amd', 'is_active' => true]);
        $filter = Filter::create([
            'name' => 'Dung lượng',
            'slug' => 'dung-luong',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'is_active' => true,
        ]);
        FilterValue::create([
            'filter_id' => $filter->id,
            'label' => '12GB',
            'slug' => '12gb',
            'match_value' => '12GB',
            'is_active' => true,
        ]);
        $category->filters()->attach($filter->id);

        $matching = $this->product($category, [
            'brand_id' => $nvidia->id,
            'price' => 18000000,
            'stock_quantity' => 4,
            'specifications_text' => "Bộ nhớ: 12GB\nGPU: NVIDIA",
        ]);
        $this->product($category, [
            'brand_id' => $nvidia->id,
            'price' => 18000000,
            'stock_quantity' => 0,
            'specifications_text' => 'Bộ nhớ: 12GB',
        ]);
        $this->product($category, [
            'brand_id' => $amd->id,
            'price' => 18000000,
            'stock_quantity' => 4,
            'specifications_text' => 'Bộ nhớ: 12GB',
        ]);

        $this->getJson('/api/v1/categories/linh-kien?brands=nvidia&f_dung-luong=12gb&in_stock=1&min_price=17000000&max_price=19000000')
            ->assertOk()
            ->assertJsonPath('products.total', 1)
            ->assertJsonPath('products.data.0.id', $matching->id);
    }

    public function test_category_listing_sorting_uses_sales_and_approved_rating_data(): void
    {
        $category = $this->category('vga', 'VGA');
        $popular = $this->product($category, ['sold_count' => 500]);
        $rated = $this->product($category, ['sold_count' => 1]);
        $reviewer = \App\Models\User::factory()->create();
        Review::create([
            'user_id' => $reviewer->id,
            'product_id' => $rated->id,
            'rating' => 5,
            'body' => 'Rất tốt.',
            'is_approved' => true,
        ]);

        $this->getJson('/api/v1/categories/vga?sort=popular&per_page=1')
            ->assertOk()
            ->assertJsonPath('products.data.0.id', $popular->id);

        $this->getJson('/api/v1/categories/vga?sort=rating&per_page=1')
            ->assertOk()
            ->assertJsonPath('products.data.0.id', $rated->id);

        $this->getJson('/api/v1/categories/vga?per_page=100')
            ->assertOk()
            ->assertJsonPath('products.per_page', 48);
    }

    /** @param array<string, mixed> $overrides */
    private function category(string $slug, string $name, array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'show_on_pc_website' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Category product '.Str::random(8),
            'slug' => 'category-product-'.Str::lower(Str::random(12)),
            'sku' => 'CATEGORY-'.Str::upper(Str::random(8)),
            'price' => 10000000,
            'stock_quantity' => 10,
            'is_active' => true,
            'is_featured' => false,
            'inventory_source' => 'local',
            'show_on_pc_website' => true,
        ], $overrides));
    }
}
