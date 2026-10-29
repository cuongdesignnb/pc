<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateCustomer;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\Kiot\KiotOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AffiliateProgramTest extends TestCase
{
    use RefreshDatabase;

    private const USED_LAPTOP_TIERS = ['type' => 'tiers', 'tiers' => [
        ['min_price' => 0, 'amount' => 200000],
        ['min_price' => 10_000_000, 'amount' => 500000],
    ]];

    public function test_customer_signs_up_and_admin_approves_with_a_code(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/affiliate/register', [
            'phone' => '+84 912 000 111', 'bank_name' => 'VCB', 'bank_account_number' => '0011', 'bank_account_name' => 'NGUYEN VAN A',
        ])->assertCreated()->assertJsonPath('affiliate.status', 'pending')->assertJsonPath('affiliate.phone', '0912000111');
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.affiliate.status', 'pending');

        $affiliate = $user->affiliate()->first();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch("/admin/affiliates/{$affiliate->id}/status", ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CTV001', $affiliate->fresh()->code);

        Sanctum::actingAs($user->fresh());
        $this->putJson('/api/v1/affiliate/me/payout-schedule', ['payout_days' => [25, 10]])
            ->assertOk()->assertJsonPath('affiliate.payout_days', [10, 25]);
        $this->putJson('/api/v1/affiliate/me/payout-schedule', ['payout_days' => [1, 2, 3, 4]])->assertUnprocessable();
        $this->getJson('/api/v1/affiliate/me')
            ->assertOk()
            ->assertJsonPath('affiliate.code', 'CTV001')
            ->assertJsonPath('wallet.available', 0);
    }

    public function test_link_click_is_logged_for_active_codes_only(): void
    {
        $affiliate = $this->affiliate();

        $this->postJson('/api/v1/affiliate/clicks', ['code' => strtolower($affiliate->code), 'url' => '/laptop-cu'])
            ->assertOk()->assertJsonPath('valid', true);
        $this->postJson('/api/v1/affiliate/clicks', ['code' => 'CTV999'])->assertOk()->assertJsonPath('valid', false);

        $this->assertSame(1, AffiliateClick::count());
    }

    public function test_first_laptop_order_gets_the_discount_and_the_first_ctv_keeps_the_customer(): void
    {
        $first = $this->affiliate();
        $second = $this->affiliate();
        $laptop = $this->product('Laptop cũ', 8_000_000, self::USED_LAPTOP_TIERS);

        $this->postJson('/api/v1/checkout/quote', $this->location() + [
            'checkout_mode' => 'buy_now', 'items' => [['product_id' => $laptop->id, 'quantity' => 1]],
            'referral_code' => $first->code,
        ])->assertOk()
            ->assertJsonPath('summary.referral_discount', 200000)
            ->assertJsonPath('referral.code', $first->code);

        $this->placeOrder($laptop, ['referral_code' => $first->code])->assertCreated()
            ->assertJsonPath('order.referral_discount', 200000);
        $order = Order::latest('id')->first();
        $this->assertSame($first->id, $order->affiliate_id);
        $this->assertSame(200000, (int) $order->discount);
        $this->assertSame(8_000_000 - 200000 + (int) $order->shipping_fee, (int) $order->total);
        $this->assertSame(200000, (int) $order->affiliateCommission->amount);
        $this->assertSame($first->id, AffiliateCustomer::where('phone', '0987654321')->value('affiliate_id'));

        $payload = (fn () => $this->createPayload($order->fresh()->load('items'), 0))->call(app(KiotOrderService::class));
        $this->assertStringContainsString("Khách của CTV: {$first->user->name} – {$first->code}", $payload['note']);
        $this->assertSame(200000, $payload['totals']['discount']);

        // Another CTV's code later: the customer stays with the first CTV, and
        // the first-order discount is not given again.
        $this->placeOrder($laptop, ['referral_code' => $second->code, 'customer_phone' => '+84987654321'])->assertCreated();
        $again = Order::latest('id')->first();
        $this->assertSame($first->id, $again->affiliate_id);
        $this->assertSame(0, (int) $again->discount);

        // No code at all: still the first CTV's customer.
        $this->placeOrder($laptop, [])->assertCreated();
        $this->assertSame($first->id, Order::latest('id')->first()->affiliate_id);
    }

    public function test_accessories_need_two_million_for_the_discount(): void
    {
        $affiliate = $this->affiliate();
        $mouse = $this->product('Phụ kiện', 500000, ['type' => 'percent', 'value' => 5]);

        $this->placeOrder($mouse, ['referral_code' => $affiliate->code])->assertCreated();
        $small = Order::latest('id')->first();
        $this->assertSame(0, (int) $small->discount);
        $this->assertSame(25000, (int) $small->affiliateCommission->amount);

        $this->placeOrder($mouse, ['referral_code' => $affiliate->code, 'customer_phone' => '0900000002', 'quantity' => 4])->assertCreated();
        $this->assertSame(200000, (int) Order::latest('id')->first()->discount);
    }

    public function test_ctv_buying_for_themself_gets_nothing(): void
    {
        $affiliate = $this->affiliate();
        $laptop = $this->product('Laptop mới', 20_000_000, ['type' => 'percent', 'value' => 1]);

        $this->placeOrder($laptop, ['referral_code' => $affiliate->code, 'customer_phone' => $affiliate->phone])->assertCreated();

        $order = Order::latest('id')->first();
        $this->assertNull($order->affiliate_id);
        $this->assertSame(0, (int) $order->discount);
        $this->assertSame(0, AffiliateCommission::count());
    }

    public function test_product_rule_overrides_category_and_repairs_pay_once_per_order(): void
    {
        $affiliate = $this->affiliate();
        $laptop = $this->product('Laptop mới', 20_000_000, ['type' => 'percent', 'value' => 1]);
        $laptop->update(['affiliate_commission' => ['type' => 'percent', 'value' => 2]]);
        $repair = $this->product('Sửa chữa', 300000, ['type' => 'fixed', 'value' => 50000, 'per' => 'order']);

        $this->placeOrder($laptop, ['referral_code' => $affiliate->code])->assertCreated();
        $this->assertSame(400000, (int) Order::latest('id')->first()->affiliateCommission->amount);

        $this->placeOrder($repair, ['referral_code' => $affiliate->code, 'customer_phone' => '0900000003', 'quantity' => 3])->assertCreated();
        $this->assertSame(50000, (int) Order::latest('id')->first()->affiliateCommission->amount);
    }

    public function test_commission_is_approved_seven_days_after_delivery_and_cancelled_with_the_order(): void
    {
        $affiliate = $this->affiliate();
        $laptop = $this->product('Laptop cũ', 8_000_000, self::USED_LAPTOP_TIERS);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->placeOrder($laptop, ['referral_code' => $affiliate->code])->assertCreated();
        $delivered = Order::latest('id')->first();
        $this->placeOrder($laptop, ['referral_code' => $affiliate->code, 'customer_phone' => '0900000004'])->assertCreated();
        $cancelled = Order::latest('id')->first();

        $this->actingAs($admin)->patch("/admin/orders/{$delivered->id}/status", ['order_status' => 'delivered']);
        $this->actingAs($admin)->patch("/admin/orders/{$cancelled->id}/status", ['order_status' => 'cancelled']);
        $this->assertNotNull($delivered->fresh()->delivered_at);
        $this->assertSame('cancelled', $cancelled->affiliateCommission()->first()->status);

        $this->travel(6)->days();
        $this->artisan('affiliate:approve-commissions')->assertSuccessful();
        $this->assertSame('pending', $delivered->affiliateCommission()->first()->status);

        $this->travel(2)->days();
        $this->artisan('affiliate:approve-commissions')->assertSuccessful();
        $this->assertSame('approved', $delivered->affiliateCommission()->first()->status);

        Sanctum::actingAs($affiliate->user);
        $this->getJson('/api/v1/affiliate/me')
            ->assertJsonPath('wallet.available', 200000)
            ->assertJsonPath('wallet.pending', 0)
            ->assertJsonPath('stats.orders', 1);
    }

    public function test_staff_can_confirm_or_cancel_a_commission(): void
    {
        $affiliate = $this->affiliate();
        $laptop = $this->product('Laptop cũ', 8_000_000, self::USED_LAPTOP_TIERS);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->placeOrder($laptop, ['referral_code' => $affiliate->code])->assertCreated();
        $commission = AffiliateCommission::latest('id')->first();

        $this->actingAs($admin)->post("/admin/affiliates/commissions/{$commission->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('approved', $commission->fresh()->status);
        $this->assertSame($admin->id, $commission->fresh()->approved_by);

        $this->actingAs($admin)->post("/admin/affiliates/commissions/{$commission->id}/cancel", ['reason' => 'Khách trả hàng'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $commission->fresh()->status);
        $this->assertSame('Khách trả hàng', $commission->fresh()->cancelled_reason);
    }

    public function test_shop_sale_is_recorded_for_the_ctv_and_ties_the_phone(): void
    {
        $affiliate = $this->affiliate();
        $other = $this->affiliate();
        $category = Category::create(['name' => 'Linh kiện laptop', 'slug' => 'linh-kien-'.Str::random(4), 'is_active' => true,
            'affiliate_commission' => ['type' => 'percent', 'value' => 5]]);
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = ['customer_phone' => '0911 222 333', 'category_id' => $category->id, 'amount' => 1_000_000, 'sold_at' => now()->toDateString()];

        $this->actingAs($admin)->post('/admin/affiliates/external-orders', $sale)->assertSessionHasErrors('affiliate_code');

        $this->actingAs($admin)->post('/admin/affiliates/external-orders', $sale + ['affiliate_code' => $affiliate->code])
            ->assertSessionHasNoErrors();
        $commission = AffiliateCommission::latest('id')->first();
        $this->assertSame($affiliate->id, $commission->affiliate_id);
        $this->assertSame(50000, (int) $commission->amount);

        // The phone now belongs to the first CTV, whichever code staff type.
        $this->actingAs($admin)->post('/admin/affiliates/external-orders', $sale + ['affiliate_code' => $other->code])
            ->assertSessionHasNoErrors();
        $this->assertSame($affiliate->id, AffiliateCommission::latest('id')->first()->affiliate_id);

        $customer = AffiliateCustomer::where('phone', '0911222333')->first();
        $this->actingAs($admin)->patch("/admin/affiliates/customers/{$customer->id}", ['affiliate_id' => $other->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($other->id, $customer->fresh()->affiliate_id);
    }

    public function test_share_box_shows_the_ctv_commission_for_a_product(): void
    {
        $affiliate = $this->affiliate();
        $laptop = $this->product('Laptop cũ', 12_000_000, self::USED_LAPTOP_TIERS);

        Sanctum::actingAs($affiliate->user);
        $this->getJson("/api/v1/affiliate/products/{$laptop->id}/commission")
            ->assertOk()
            ->assertJsonPath('code', $affiliate->code)
            ->assertJsonPath('estimated_amount', 500000);

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/v1/affiliate/products/{$laptop->id}/commission")->assertForbidden();
    }

    private function affiliate(): Affiliate
    {
        $user = User::factory()->create(['role' => 'customer']);

        return Affiliate::create([
            'user_id' => $user->id, 'code' => Affiliate::nextCode(), 'phone' => '09'.random_int(10000000, 99999999),
            'status' => 'active', 'approved_at' => now(),
        ])->load('user');
    }

    private function product(string $categoryName, int $price, array $commission): Product
    {
        $category = Category::where('name', $categoryName)->first()
            ?? Category::create(['name' => $categoryName, 'slug' => Str::slug($categoryName).'-'.Str::random(4), 'is_active' => true, 'affiliate_commission' => $commission]);

        return Product::create([
            'name' => "{$categoryName} mẫu", 'slug' => 'sp-'.Str::lower(Str::random(8)), 'sku' => 'SKU-'.Str::random(6),
            'category_id' => $category->id, 'price' => $price, 'stock_quantity' => 50,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    private function location(): array
    {
        return ['shipping_province_code' => '79', 'shipping_ward_code' => '27316', 'shipping_method' => 'standard', 'payment_method' => 'cod'];
    }

    private function placeOrder(Product $product, array $overrides)
    {
        $quantity = $overrides['quantity'] ?? 1;
        unset($overrides['quantity']);

        return $this->withHeader('X-Cart-Session', 'affiliate-'.Str::random(6))->postJson('/api/v1/orders', array_merge([
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'checkout_mode' => 'buy_now',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'customer_name' => 'Nguyễn Văn B', 'customer_email' => 'b@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
        ], $overrides));
    }
}
