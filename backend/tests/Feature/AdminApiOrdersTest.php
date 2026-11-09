<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminApiOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'orders.view', 'guard_name' => 'web']));
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'order_number' => 'LP'.fake()->unique()->numerify('######'),
            'subtotal' => 9_000_000, 'discount' => 200_000, 'shipping_fee' => 0, 'total' => 8_800_000,
            'payment_status' => 'unpaid', 'payment_method' => 'cod', 'order_status' => 'pending',
            'shipping_name' => 'Nguyễn Văn A', 'shipping_phone' => '0912345678', 'customer_email' => 'a@example.com',
            'shipping_address' => '12 Láng Hạ', 'shipping_city' => 'Hà Nội',
        ]);
        $order->items()->create([
            'product_name' => 'Dell Latitude 7420', 'sku' => 'D7420', 'quantity' => 1, 'price' => 9_000_000, 'total' => 9_000_000,
        ]);

        return $order;
    }

    public function test_orders_token_lists_and_filters_orders_read_only(): void
    {
        $old = $this->order(['order_status' => 'delivered']);
        $old->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();
        $new = $this->order();
        $token = app(AdminApiTokens::class)->issue($this->admin, 'n8n', 'orders')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin-api/orders?status=pending&created_since='.urlencode(now()->subDay()->toIso8601String()))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.customer_name', 'Nguyễn Văn A')
            ->assertJsonPath('data.0.customer_phone', '0912345678')
            ->assertJsonPath('data.0.items.0.name', 'Dell Latitude 7420')
            ->assertJsonPath('data.0.total', 8_800_000)
            ->assertJsonPath('data.0.status_label', 'Chờ xử lý')
            ->assertJsonPath('data.0.payment_status', 'unpaid')
            ->assertJsonPath('data.0.shipping_address', '12 Láng Hạ, Hà Nội')
            ->assertJsonMissingPath('data.0.order_access_token_hash');

        $this->withToken($token)->getJson("/api/v1/admin-api/orders?after_id={$old->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $new->id);
        $this->withToken($token)->getJson("/api/v1/admin-api/orders/{$old->id}")
            ->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->withToken($token)->getJson('/api/v1/admin-api/orders?status=dang-cho')->assertUnprocessable();

        // Orders tokens cannot touch content, and there is no write route for orders.
        $this->withToken($token)->getJson('/api/v1/admin-api/products')->assertForbidden();
        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$new->id}", ['order_status' => 'cancelled'])->assertStatus(405);
        $this->assertSame('pending', $new->fresh()->order_status);
    }

    public function test_content_tokens_and_anonymous_callers_cannot_read_orders(): void
    {
        $order = $this->order();
        $content = app(AdminApiTokens::class)->issue($this->admin, 'AI', 'content')->plainTextToken;

        $this->withToken($content)->getJson('/api/v1/admin-api/orders')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/admin-api/orders')->assertUnauthorized();
        // The storefront order page needs the buyer's access token.
        $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    }

    public function test_only_staff_who_may_view_orders_can_issue_or_use_order_tokens(): void
    {
        $writer = User::factory()->create(['role' => 'staff']);
        $writer->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));

        $this->actingAs($writer)->post('/admin/api-tokens', ['name' => 'n8n', 'type' => 'orders'])
            ->assertSessionHasErrors('type');
        $this->assertSame(0, $writer->tokens()->count());

        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $this->actingAs($this->admin)->post('/admin/api-tokens', ['name' => 'n8n', 'type' => 'orders'])
            ->assertSessionHas('api_token');

        $token = app(AdminApiTokens::class)->issue($this->admin, 'n8n-2', 'orders')->plainTextToken;
        $this->admin->revokePermissionTo('orders.view');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin-api/orders')->assertForbidden();
    }
}
