<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_returns_active_variants_without_related_products(): void
    {
        $category = Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop-'.Str::lower(Str::random(6)),
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
        $product = $this->product(['category_id' => $category->id]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'RAM 16GB',
            'sku' => 'VAR-'.Str::upper(Str::random(6)),
            'price' => 1250,
            'sale_price' => 1200,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'RAM 8GB',
            'sku' => 'VAR-'.Str::upper(Str::random(6)),
            'price' => 1100,
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.variants.0.name', 'RAM 16GB')
            ->assertJsonPath('product.variants.0.display_price', 1200)
            ->assertJsonMissingPath('product.variants.1')
            ->assertJsonMissingPath('related');
    }

    public function test_variant_can_be_added_to_cart_at_variant_price(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'SSD 1TB',
            'sku' => 'VAR-'.Str::upper(Str::random(6)),
            'price' => 1500,
            'sale_price' => 1400,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->withHeader('X-Cart-Session', 'variant-test-session')
            ->postJson('/api/v1/cart/items', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('items.0.variant_id', $variant->id)
            ->assertJsonPath('items.0.price', '1400');
    }

    public function test_storefront_detail_settings_are_public_and_keep_newlines(): void
    {
        Setting::updateOrCreate(
            ['key' => 'storefront_warehouse_addresses'],
            [
                'value' => "Kho 1\n34 Hồ Tùng Mậu",
                'group' => 'contact',
                'type' => 'textarea',
                'label' => 'Kho hàng',
                'is_public' => true,
            ],
        );
        Setting::updateOrCreate(
            ['key' => 'storefront_warranty_information'],
            [
                'value' => "Bảo hành 12 tháng\nĐổi trả trong 7 ngày",
                'group' => 'contact',
                'type' => 'textarea',
                'label' => 'Bảo hành',
                'is_public' => true,
            ],
        );

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('storefront_warehouse_addresses', "Kho 1\n34 Hồ Tùng Mậu")
            ->assertJsonPath('storefront_warranty_information', "Bảo hành 12 tháng\nĐổi trả trong 7 ngày");
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Sản phẩm biến thể',
            'slug' => 'variant-'.Str::lower(Str::random(8)),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
