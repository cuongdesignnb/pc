<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\WarrantyPackages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarrantyPackageTest extends TestCase
{
    use RefreshDatabase;

    private const PACKAGES = [['months' => 1, 'price' => 0], ['months' => 3, 'price' => 300000], ['months' => 6, 'price' => 700000]];

    public function test_admin_saves_packages_on_a_category(): void
    {
        $category = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'sort_order' => 0,
            'warranty_packages' => [['months' => 6, 'price' => 700000], ['months' => 1, 'price' => null], ['months' => 3, 'price' => 300000]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(self::PACKAGES, WarrantyPackages::normalise($category->fresh()->warranty_packages));

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'sort_order' => 0,
            'warranty_packages' => [['months' => 3, 'price' => 1], ['months' => 3, 'price' => 2]],
        ])->assertSessionHasErrors('warranty_packages.1.months');

        $this->actingAs($admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'sort_order' => 0, 'warranty_packages' => [],
        ])->assertSessionHasNoErrors();
        $this->assertNull($category->fresh()->warranty_packages);
    }

    public function test_child_category_products_offer_the_packages(): void
    {
        $product = $this->product(child: true);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.warranty_packages', self::PACKAGES);
    }

    public function test_chosen_package_is_charged_through_cart_quote_and_order(): void
    {
        $product = $this->product();

        $this->withHeader('X-Cart-Session', 'warranty-cart')
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2, 'warranty_months' => 2])
            ->assertUnprocessable();
        $this->withHeader('X-Cart-Session', 'warranty-cart')
            ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2, 'warranty_months' => 3])
            ->assertOk()
            ->assertJsonPath('items.0.warranty.months', 3)
            ->assertJsonPath('items.0.unit_price', 5_300_000)
            ->assertJsonPath('summary.subtotal', 10_600_000);

        $this->withHeader('X-Cart-Session', 'warranty-cart')
            ->postJson('/api/v1/checkout/quote', $this->location() + ['checkout_mode' => 'cart'])
            ->assertOk()
            ->assertJsonPath('items.0.warranty', ['months' => 3, 'price' => 300000])
            ->assertJsonPath('summary.subtotal', 10_600_000);

        $this->withHeader('X-Cart-Session', 'warranty-cart')
            ->postJson('/api/v1/orders', $this->order(['checkout_mode' => 'cart', 'items' => [['product_id' => $product->id, 'quantity' => 2]]]))
            ->assertCreated()
            ->assertJsonPath('order.items.0.warranty_months', 3)
            ->assertJsonPath('order.items.0.warranty_price', 300000);

        $order = Order::with('items')->latest('id')->first();
        $this->assertSame(10_600_000, (int) $order->subtotal);
        $this->assertSame(5_300_000, (int) $order->items[0]->price);
    }

    public function test_buy_now_without_a_package_keeps_the_default_warranty(): void
    {
        $product = $this->product();

        $this->postJson('/api/v1/checkout/quote', $this->location() + [
            'checkout_mode' => 'buy_now', 'items' => [['product_id' => $product->id, 'quantity' => 1, 'warranty_months' => 12]],
        ])->assertOk()->assertJsonPath('issues.0.code', 'WARRANTY_PACKAGE_UNAVAILABLE');

        $this->withHeader('X-Cart-Session', 'warranty-buy-now')
            ->postJson('/api/v1/orders', $this->order(['checkout_mode' => 'buy_now', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]))
            ->assertCreated()
            ->assertJsonPath('order.items.0.warranty_months', 6)
            ->assertJsonPath('order.items.0.warranty_price', 0)
            ->assertJsonPath('order.subtotal', '5000000');
    }

    private function product(bool $child = false): Product
    {
        $category = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu-'.Str::random(4), 'is_active' => true, 'warranty_packages' => self::PACKAGES]);
        if ($child) {
            $category = Category::create(['name' => 'Dell cũ', 'slug' => 'dell-cu-'.Str::random(4), 'is_active' => true, 'parent_id' => $category->id]);
        }

        return Product::create([
            'name' => 'Dell Latitude cũ', 'slug' => 'dell-latitude-'.Str::lower(Str::random(6)), 'sku' => 'USED-'.Str::random(6),
            'category_id' => $category->id, 'price' => 5_000_000, 'stock_quantity' => 5, 'warranty_months' => 6,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    private function location(): array
    {
        return ['shipping_province_code' => '79', 'shipping_ward_code' => '27316', 'shipping_method' => 'standard', 'payment_method' => 'cod'];
    }

    private function order(array $overrides): array
    {
        return array_merge([
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
        ], $overrides);
    }
}
