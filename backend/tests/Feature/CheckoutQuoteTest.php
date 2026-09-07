<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_api_and_quote_use_selected_cart_items(): void
    {
        $product = $this->product(['price' => 2000, 'sale_price' => 1500]);
        $unselected = $this->product(['price' => 900]);
        $cart = Cart::create(['session_id' => 'checkout-quote-session']);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 1500,
            'is_selected' => true,
        ]);
        $cart->items()->create([
            'product_id' => $unselected->id,
            'quantity' => 1,
            'price' => 900,
            'is_selected' => false,
        ]);

        $this->getJson('/api/v1/locations/provinces')
            ->assertOk()
            ->assertJsonFragment(['code' => '79', 'fullname' => 'Thành phố Hồ Chí Minh']);
        $this->getJson('/api/v1/locations/provinces/79/wards')
            ->assertOk()
            ->assertJsonFragment(['code' => '27316', 'fullname' => 'Phường An Đông']);

        $response = $this->withHeader('X-Cart-Session', 'checkout-quote-session')
            ->postJson('/api/v1/checkout/quote', [
                'checkout_mode' => 'cart',
                'shipping_province_code' => '79',
                'shipping_ward_code' => '27316',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])
            ->assertOk()
            ->assertJsonPath('checkout_mode', 'cart')
            ->assertJsonPath('summary.line_count', 1)
            ->assertJsonPath('summary.total_quantity', 2)
            ->assertJsonPath('summary.subtotal', 3000)
            ->assertJsonPath('summary.shipping_fee', 30000)
            ->assertJsonPath('summary.total', 33000)
            ->assertJsonPath('can_place_order', true)
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.unit_price', 1500);

        $this->assertNotNull($response->json('quote_id'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_resolves_location_names_from_codes_and_rejects_mismatched_ward(): void
    {
        $product = $this->product();
        $payload = $this->orderPayload($product->id, [
            'shipping_city' => 'Tên tỉnh giả từ client',
            'shipping_ward' => 'Tên xã giả từ client',
            'shipping_province_code' => '79',
            'shipping_ward_code' => '27316',
        ]);

        $this->withHeader('X-Cart-Session', 'checkout-order-session')
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.shipping_city', 'Thành phố Hồ Chí Minh')
            ->assertJsonPath('order.shipping_ward', 'Phường An Đông');

        $invalid = $this->orderPayload($product->id, [
            'shipping_province_code' => '79',
            'shipping_ward_code' => '30985',
        ]);
        $this->withHeader('X-Cart-Session', 'checkout-invalid-location-session')
            ->postJson('/api/v1/orders', $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_ward_code');
    }

    /** @return array<string, mixed> */
    private function orderPayload(int $productId, array $overrides = []): array
    {
        return array_merge([
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'Hà Nội',
            'shipping_ward' => 'Phường Cửa Nam',
            'payment_method' => 'cod',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ], $overrides);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Checkout product '.Str::random(8),
            'slug' => 'checkout-product-'.Str::lower(Str::random(12)),
            'sku' => 'CHECKOUT-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
