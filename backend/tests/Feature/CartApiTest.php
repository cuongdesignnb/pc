<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_returns_selected_summary_real_relations_and_support_configuration(): void
    {
        $category = Category::create([
            'name' => 'Linh kiện',
            'slug' => 'linh-kien-'.Str::lower(Str::random(6)),
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
        $product = $this->product($category, ['price' => 1000, 'sale_price' => 900, 'stock_quantity' => 5]);
        $second = $this->product($category, ['price' => 2000, 'stock_quantity' => 5]);
        $accessory = $this->product($category, ['price' => 300, 'stock_quantity' => 5]);
        ProductRelation::create([
            'product_id' => $product->id,
            'related_product_id' => $accessory->id,
            'relation_type' => 'accessory',
            'sort_order' => 0,
        ]);
        Setting::create([
            'key' => 'shipping_free_threshold', 'value' => '5000', 'group' => 'shipping',
            'type' => 'number', 'label' => 'Miễn phí ship từ', 'is_public' => true,
        ]);
        Setting::create([
            'key' => 'shipping_default_fee', 'value' => '30', 'group' => 'shipping',
            'type' => 'number', 'label' => 'Phí ship', 'is_public' => true,
        ]);
        Setting::create([
            'key' => 'contact_hotline', 'value' => '1900 2064', 'group' => 'contact',
            'type' => 'text', 'label' => 'Hotline', 'is_public' => true,
        ]);

        $session = 'cart-api-test';
        $this->withHeader('X-Cart-Session', $session)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id, 'quantity' => 2,
        ])->assertOk();
        $this->withHeader('X-Cart-Session', $session)->postJson('/api/v1/cart/items', [
            'product_id' => $second->id, 'quantity' => 1,
        ])->assertOk();

        $response = $this->withHeader('X-Cart-Session', $session)->getJson('/api/v1/cart')->assertOk();
        $response
            ->assertJsonPath('summary.line_count', 2)
            ->assertJsonPath('summary.item_count', 3)
            ->assertJsonPath('summary.selected_item_count', 3)
            ->assertJsonPath('summary.quantity', 3)
            ->assertJsonPath('summary.original_subtotal', 4000)
            ->assertJsonPath('summary.subtotal', 3800)
            ->assertJsonPath('summary.payable_before_shipping', 3800)
            ->assertJsonPath('summary.product_discount', 200)
            ->assertJsonPath('summary.shipping_fee', 30)
            ->assertJsonPath('summary.total', 3830)
            ->assertJsonPath('accessories.0.id', $accessory->id)
            ->assertJsonPath('support.hotline', '1900 2064')
            ->assertJsonPath('items.0.selected', true)
            ->assertJsonPath('items.0.unit_price', 900)
            ->assertJsonPath('items.0.pricing.line_original', 2000)
            ->assertJsonPath('items.0.pricing.line_total', 1800)
            ->assertJsonPath('items.0.inventory.max_quantity', 5);

        $cart = Cart::where('session_id', $session)->firstOrFail();
        $secondItem = $cart->items()->where('product_id', $second->id)->firstOrFail();
        $this->withHeader('X-Cart-Session', $session)
            ->patchJson("/api/v1/cart/items/{$secondItem->id}/selection", ['selected' => false])
            ->assertOk()
            ->assertJsonPath('summary.line_count', 2)
            ->assertJsonPath('summary.selected_line_count', 1)
            ->assertJsonPath('summary.item_count', 3)
            ->assertJsonPath('summary.selected_item_count', 2)
            ->assertJsonPath('summary.quantity', 2)
            ->assertJsonPath('summary.subtotal', 1800);

        $this->withHeader('X-Cart-Session', $session)
            ->deleteJson('/api/v1/cart/items', ['item_ids' => [$secondItem->id]])
            ->assertOk()
            ->assertJsonPath('cart.item_count', 1)
            ->assertJsonPath('items.0.product_id', $product->id);

        $this->withHeader('X-Cart-Session', 'other-session')
            ->deleteJson('/api/v1/cart/items', ['item_ids' => [$cart->items()->firstOrFail()->id]])
            ->assertNotFound();
    }

    public function test_cart_item_mutations_are_scoped_to_the_current_session_and_add_checks_cumulative_stock(): void
    {
        $product = $this->product(null, ['stock_quantity' => 2]);
        $this->withHeader('X-Cart-Session', 'owner-session')->postJson('/api/v1/cart/items', [
            'product_id' => $product->id, 'quantity' => 2,
        ])->assertOk();

        $this->withHeader('X-Cart-Session', 'owner-session')->postJson('/api/v1/cart/items', [
            'product_id' => $product->id, 'quantity' => 1,
        ])->assertUnprocessable();

        $item = Cart::where('session_id', 'owner-session')->firstOrFail()->items()->firstOrFail();
        $this->withHeader('X-Cart-Session', 'other-session')
            ->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 1])
            ->assertNotFound();
    }

    private function product(?Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category?->id,
            'name' => 'Cart product '.Str::random(8),
            'slug' => 'cart-product-'.Str::lower(Str::random(12)),
            'sku' => 'CART-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
