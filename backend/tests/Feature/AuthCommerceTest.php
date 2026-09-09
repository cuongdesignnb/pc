<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_merges_guest_cart_and_caps_quantity_at_live_stock(): void
    {
        $product = $this->product(['stock_quantity' => 3]);
        $user = User::factory()->create([
            'email' => 'customer@example.test',
            'password' => Hash::make('strong-password'),
        ]);
        $guestCart = Cart::create(['session_id' => 'auth-commerce-session']);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1000,
            'is_selected' => true,
        ]);
        $accountCart = Cart::create(['user_id' => $user->id]);
        $accountCart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 900,
            'is_selected' => false,
        ]);

        $response = $this->withHeader('X-Cart-Session', 'auth-commerce-session')
            ->postJson('/api/v1/auth/login', [
                'email' => 'CUSTOMER@example.test',
                'password' => 'strong-password',
                'remember' => true,
            ])
            ->assertOk()
            ->assertJsonPath('commerce.cart_merged', true)
            ->assertJsonPath('commerce.cart_warnings.0.code', 'quantity_capped')
            ->assertJsonPath('user.email', 'customer@example.test');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $accountCart->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'is_selected' => 1,
        ]);
        $this->assertDatabaseMissing('carts', ['session_id' => 'auth-commerce-session']);
    }

    public function test_register_requires_terms_and_returns_authenticated_commerce_payload(): void
    {
        $response = $this->withHeader('X-Cart-Session', 'new-customer-session')
            ->postJson('/api/v1/auth/register', [
                'name' => 'Nguyễn Văn A',
                'email' => 'new-customer@example.test',
                'password' => 'strong-password',
                'password_confirmation' => 'strong-password',
                'phone' => '0901234567',
                'terms_accepted' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('commerce.cart_merged', false)
            ->assertJsonPath('user.email', 'new-customer@example.test');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', ['email' => 'new-customer@example.test']);
    }

    public function test_authenticated_wishlist_is_private_and_merge_is_idempotent(): void
    {
        $first = $this->product();
        $second = $this->product();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wishlist/merge', ['product_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonCount(2, 'ids');
        $this->postJson('/api/v1/wishlist/merge', ['product_ids' => [$first->id]])
            ->assertOk()
            ->assertJsonCount(2, 'ids');
        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonCount(2, 'ids')
            ->assertJsonPath('products.0.id', $second->id);
    }

    public function test_authenticated_wishlist_can_add_and_remove_a_product(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('ids.0', $product->id);
        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->deleteJson('/api/v1/wishlist/items/'.$product->id)
            ->assertOk()
            ->assertJsonCount(0, 'ids');
        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::create([
            'name' => 'Auth category '.Str::random(6),
            'slug' => 'auth-category-'.Str::lower(Str::random(10)),
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Auth product '.Str::random(8),
            'slug' => 'auth-product-'.Str::lower(Str::random(12)),
            'sku' => 'AUTH-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
