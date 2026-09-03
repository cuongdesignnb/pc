<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductRelation;
use App\Models\ProductVariant;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductDetailApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);
    }

    public function test_detail_endpoint_returns_safe_pdp_data_without_operational_metadata(): void
    {
        $product = $this->product([
            'cost_price' => 250,
            'kiot_sync_error_message' => 'private sync detail',
            'description' => '<script>alert(1)</script><p>Nội dung an toàn</p>',
        ]);
        $product->images()->create(['url' => '/storage/media/card.jpg', 'is_primary' => true, 'sort_order' => 0]);
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => '16 GB',
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'attributes' => ['RAM' => '16 GB'],
            'price' => 1200,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.name', $product->name)
            ->assertJsonPath('product.variants.0.attributes.RAM', '16 GB')
            ->assertJsonPath('product.description', 'alert(1)Nội dung an toàn')
            ->assertJsonMissingPath('product.cost_price')
            ->assertJsonMissingPath('product.provider')
            ->assertJsonMissingPath('product.kiot_sync_error_message')
            ->assertJsonMissingPath('product.reviews');
    }

    public function test_review_feed_hides_guest_email_and_marks_only_real_orders_as_verified(): void
    {
        $product = $this->product();
        $order = Order::create([
            'order_number' => 'DH'.now()->format('YmdHis'),
            'subtotal' => 1000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 1000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'order_status' => 'delivered',
            'shipping_name' => 'Khách đã mua',
            'shipping_phone' => '0900000000',
            'shipping_address' => 'Hà Nội',
            'shipping_city' => 'Hà Nội',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
        ]);
        Review::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'guest_name' => 'Khách đã mua',
            'guest_email' => 'private@example.test',
            'rating' => 5,
            'body' => 'Sản phẩm rất tốt và giao nhanh.',
            'is_approved' => true,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')
            ->assertOk()
            ->assertJsonPath('reviews.0.reviewer_name', 'Khách đã mua')
            ->assertJsonPath('reviews.0.verified_purchase', true)
            ->assertJsonMissingPath('reviews.0.guest_email');
    }

    public function test_question_feed_hides_guest_email_and_only_returns_approved_questions(): void
    {
        $product = $this->product();
        ProductQuestion::create([
            'product_id' => $product->id,
            'guest_name' => 'Khách hỏi',
            'guest_email' => 'hidden@example.test',
            'body' => 'Sản phẩm này có sẵn không?',
            'is_approved' => true,
        ]);
        ProductQuestion::create([
            'product_id' => $product->id,
            'guest_name' => 'Chờ duyệt',
            'guest_email' => 'pending@example.test',
            'body' => 'Câu hỏi chờ duyệt',
            'is_approved' => false,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug.'/questions')
            ->assertOk()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.asker_name', 'Khách hỏi')
            ->assertJsonMissingPath('questions.0.guest_email');
    }

    public function test_manual_relations_only_return_storefront_visible_products(): void
    {
        $product = $this->product();
        $visible = $this->product();
        $hidden = $this->product(['show_on_pc_website' => false]);
        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $hidden->id, 'relation_type' => 'related', 'sort_order' => 0]);
        ProductRelation::create(['product_id' => $product->id, 'related_product_id' => $visible->id, 'relation_type' => 'related', 'sort_order' => 1]);

        $this->getJson('/api/v1/products/'.$product->slug.'/relations?type=related')
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $visible->id);
    }

    public function test_buy_now_checkout_preserves_the_existing_cart(): void
    {
        $product = $this->product();
        $cart = Cart::create(['session_id' => 'buy-now-session']);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]);

        $payload = [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.test',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->withHeader('X-Cart-Session', 'buy-now-session')
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('order.checkout_mode', 'buy_now');

        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'PDP Test '.Str::random(8),
            'slug' => 'pdp-'.Str::lower(Str::random(12)),
            'sku' => 'PDP-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
