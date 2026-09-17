<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_visible_local_product_with_contact_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'PC đồng bộ',
            'slug' => 'pc-dong-bo',
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/products', $this->validPayload($category, [
            'price' => 0,
            'stock_quantity' => 0,
        ]));

        $response->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'SKU0001')->firstOrFail();

        $this->assertSame('0', $product->price);
        $this->assertSame('local', $product->inventory_source);
        $this->assertNull($product->provider);
        $this->assertTrue($product->is_active);
        $this->assertTrue($product->show_on_pc_website);
        $this->assertFalse($product->is_purchasable);
        $this->assertTrue(Product::visibleOnStorefront()->whereKey($product->id)->exists());
    }

    public function test_admin_product_creation_reports_a_missing_price_without_writing_a_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create([
            'name' => 'PC đồng bộ',
            'slug' => 'pc-dong-bo',
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
        $payload = $this->validPayload($category);
        unset($payload['price']);

        $this->actingAs($admin)
            ->from('/admin/products/create')
            ->post('/admin/products', $payload)
            ->assertRedirect('/admin/products/create')
            ->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('products', ['sku' => 'SKU0001']);
    }

    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Máy tính đồng bộ thương hiệu Việt Nam HPCOM HPC-V10 MQ12400',
            'slug' => 'may-tinh-dong-bo-thuong-hieu-viet-nam-hpcom-hpc-v10-mq12400',
            'sku' => 'SKU0001',
            'category_id' => $category->id,
            'brand_id' => null,
            'component_type_id' => null,
            'description' => null,
            'short_description' => 'CPU Core i3-14100',
            'price' => 0,
            'sale_price' => null,
            'stock_quantity' => 0,
            'is_active' => true,
            'is_featured' => false,
            'show_on_pc_website' => true,
            'warranty_months' => 12,
            'meta_title' => null,
            'meta_description' => null,
            'thumbnail' => null,
            'gallery' => [],
            'variants' => [],
            'highlights' => [],
            'detail_blocks' => [],
            'relations' => [],
            'specifications_text' => null,
            'compatibility_specs' => [],
        ], $overrides);
    }
}
