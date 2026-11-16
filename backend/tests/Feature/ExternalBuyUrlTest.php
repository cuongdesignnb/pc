<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalBuyUrlTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien', 'is_active' => true, 'show_on_pc_website' => true]);

        return Product::create($attributes + [
            'name' => 'Chuột Logitech G102', 'slug' => 'chuot-logitech-g102', 'sku' => 'G102',
            'category_id' => $category->id, 'price' => 390_000, 'stock_quantity' => 5,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    public function test_product_with_an_external_link_is_shown_but_not_sold_on_site(): void
    {
        $product = $this->product();
        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.external_buy_url', null)
            ->assertJsonPath('product.inventory.purchasable', true);

        $product->update(['external_buy_url' => 'https://shopee.vn/g102?ref=lp', 'external_buy_label' => 'Mua trên Shopee']);

        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.external_buy_url', 'https://shopee.vn/g102?ref=lp')
            ->assertJsonPath('product.external_buy_label', 'Mua trên Shopee')
            ->assertJsonPath('product.inventory.purchasable', false)
            ->assertJsonPath('product.availability_label', 'Mua tại trang đối tác');

        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_admin_form_and_admin_api_set_the_external_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product();
        $token = app(AdminApiTokens::class)->issue($admin, 'n8n', 'content')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['external_buy_url' => 'http://shopee.vn/x'])
            ->assertUnprocessable()->assertJsonValidationErrors('external_buy_url');
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", [
            'external_buy_url' => 'https://tiki.vn/g102', 'external_buy_label' => 'Mua trên Tiki',
        ])->assertOk()
            ->assertJsonPath('data.external_buy_url', 'https://tiki.vn/g102')
            ->assertJsonPath('data.external_buy_label', 'Mua trên Tiki');

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['external_buy_url' => null])->assertOk();
        $this->assertNull($product->fresh()->external_buy_url);
        $this->assertTrue($product->fresh()->is_purchasable);
    }
}
