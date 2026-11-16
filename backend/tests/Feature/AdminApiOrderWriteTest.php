<?php

namespace Tests\Feature;

use App\Models\AdminApiWriteLog;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminApiOrderWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        foreach (['orders.view', 'orders.edit', 'settings.edit'] as $permission) {
            $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'order_number' => 'LP'.fake()->unique()->numerify('######'),
            'subtotal' => 10_000_000, 'discount' => 0, 'shipping_fee' => 0, 'total' => 10_000_000,
            'payment_status' => 'unpaid', 'payment_method' => 'cod', 'order_status' => 'pending',
            'shipping_name' => 'Nguyễn Văn A', 'shipping_phone' => '0912345678', 'customer_email' => 'a@example.com',
            'shipping_address' => '12 Láng Hạ', 'shipping_city' => 'Hà Nội',
        ]);
        $order->items()->create(['product_name' => 'Dell 7420', 'sku' => 'D7420', 'quantity' => 1, 'price' => 10_000_000, 'total' => 10_000_000]);

        return $order;
    }

    private function token(string $type = 'orders_write'): string
    {
        return app(AdminApiTokens::class)->issue($this->admin, 'n8n', $type)->plainTextToken;
    }

    public function test_write_token_needs_order_edit_permission_and_read_tokens_cannot_write(): void
    {
        $viewer = User::factory()->create(['role' => 'admin']);
        $viewer->givePermissionTo(['orders.view', 'settings.edit']);
        $this->actingAs($viewer)->post('/admin/api-tokens', ['name' => 'n8n', 'type' => 'orders_write'])
            ->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->post('/admin/api-tokens', ['name' => 'n8n', 'type' => 'orders_write'])
            ->assertSessionHas('api_token');
        $this->assertSame(['orders:read', 'orders:write'], $this->admin->tokens()->firstOrFail()->abilities);

        $this->app['auth']->forgetGuards();
        $order = $this->order();
        $this->withToken($this->token('orders'))->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'confirmed'])
            ->assertForbidden();
        $this->assertSame('pending', $order->fresh()->order_status);
    }

    public function test_status_moves_forward_and_is_recorded_as_the_token(): void
    {
        $order = $this->order();
        $token = $this->token();

        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'shipping', 'reason' => 'Đã gửi GHN'])
            ->assertOk()->assertJsonPath('data.status', 'shipping')->assertJsonPath('data.tracking_code', null);
        $history = OrderHistory::where('order_id', $order->id)->where('type', 'status')->latest('id')->firstOrFail();
        $this->assertSame(['api', 'Token: n8n', 'Token: n8n', 'Đã gửi GHN', 'shipping'], [$history->actor_type, $history->actor_name, $history->actor_label, $history->message, $history->to_status]);

        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'confirmed'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'INVALID_TRANSITION');
        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'pending'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'VALIDATION_FAILED');
        $this->withToken($token)->patchJson('/api/v1/admin-api/orders/999999/status', ['order_status' => 'confirmed'])
            ->assertNotFound()->assertJsonPath('error_code', 'ORDER_NOT_FOUND');

        $this->assertSame(4, AdminApiWriteLog::count());
        $this->assertSame([200, 422, 422, 404], AdminApiWriteLog::orderBy('id')->pluck('status_code')->all());
        $this->assertSame('n8n', AdminApiWriteLog::first()->token_name);
    }

    public function test_cancel_goes_through_cancellation_and_cancelled_orders_are_locked(): void
    {
        $order = $this->order();
        $order->forceFill(['kiot_sync_status' => 'not_required'])->saveQuietly();
        $token = $this->token();

        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'cancelled', 'reason' => 'Khách huỷ'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/status", ['order_status' => 'delivered'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'ORDER_CANCELLED');
        $this->withToken($token)->postJson("/api/v1/admin-api/orders/{$order->id}/payments", ['amount' => 1000], ['Idempotency-Key' => 'k-cancel'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'ORDER_CANCELLED');
    }

    public function test_payments_need_a_key_add_up_and_replay_safely(): void
    {
        $order = $this->order(['deposit_amount' => 3_000_000]);
        $token = $this->token();
        $url = "/api/v1/admin-api/orders/{$order->id}/payments";

        $this->withToken($token)->postJson($url, ['amount' => 3_000_000])
            ->assertUnprocessable()->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REQUIRED');

        $body = ['amount' => 3_000_000, 'method' => 'bank_transfer', 'reference' => 'FT123', 'paid_at' => '2026-11-16T09:00:00+07:00'];
        $first = $this->withToken($token)->postJson($url, $body, ['Idempotency-Key' => 'pay-1'])
            ->assertOk()->assertJsonPath('data.payment_status', 'deposited')->assertJsonPath('data.amount_paid', 3_000_000);
        $this->withToken($token)->postJson($url, $body, ['Idempotency-Key' => 'pay-1'])
            ->assertOk()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.amount_paid', 3_000_000)
            ->assertJsonPath('data.updated_at', $first->json('data.updated_at'));
        $this->withToken($token)->postJson($url, ['amount' => 1_000_000] + $body, ['Idempotency-Key' => 'pay-1'])
            ->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertSame(3_000_000, (int) $order->fresh()->amount_paid);
        $this->assertNotNull($order->fresh()->deposit_paid_at);

        $this->withToken($token)->postJson($url, ['amount' => 8_000_000], ['Idempotency-Key' => 'pay-2'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'OVERPAYMENT');
        $this->withToken($token)->postJson($url, ['amount' => 7_000_000, 'method' => 'cash'], ['Idempotency-Key' => 'pay-3'])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.amount_paid', 10_000_000);
        $this->assertNotNull($order->fresh()->paid_at);

        $payments = OrderHistory::where('order_id', $order->id)->where('type', 'payment')->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame('Ghi nhận thanh toán 3.000.000đ (bank_transfer, ref FT123). Đã thu 3.000.000đ/10.000.000đ.', $payments[0]->message);
        $this->assertSame('Token: n8n', $payments[0]->actor_label);
        $this->assertDatabaseHas('admin_api_write_logs', ['idempotency_key' => 'pay-1', 'status_code' => 200, 'action' => 'order.payment']);
    }

    public function test_sepay_orders_take_payments_only_once_kiot_accepted_them(): void
    {
        $order = $this->order(['payment_method' => 'sepay']);
        $order->forceFill(['kiot_sync_status' => 'pending'])->saveQuietly();

        $this->withToken($this->token())->postJson("/api/v1/admin-api/orders/{$order->id}/payments", ['amount' => 1_000_000], ['Idempotency-Key' => 'x'])
            ->assertUnprocessable()->assertJsonPath('error_code', 'PAYMENT_NOT_ACCEPTED');
    }

    public function test_shipment_saves_tracking_and_can_mark_delivered(): void
    {
        $order = $this->order(['order_status' => 'shipping']);
        $token = $this->token();

        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/shipment", [])
            ->assertUnprocessable();
        $this->withToken($token)->patchJson("/api/v1/admin-api/orders/{$order->id}/shipment", ['shipping_carrier' => 'GHN', 'tracking_code' => 'GHN123', 'delivered' => true])
            ->assertOk()
            ->assertJsonPath('data.tracking_code', 'GHN123')
            ->assertJsonPath('data.shipping_carrier', 'GHN')
            ->assertJsonPath('data.status', 'delivered');
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertDatabaseHas('order_histories', ['order_id' => $order->id, 'type' => 'shipment', 'message' => 'Cập nhật vận đơn: đơn vị GHN, mã vận đơn GHN123.', 'actor_name' => 'Token: n8n']);

        $this->withToken($this->token('orders'))->getJson("/api/v1/admin-api/orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.tracking_code', 'GHN123')->assertJsonPath('data.shipping_carrier', 'GHN');

        // Staff edit the same fields on the admin order page.
        $this->actingAs($this->admin)->patch("/admin/orders/{$order->id}/shipment", ['shipping_carrier' => 'Viettel Post', 'tracking_code' => 'VT9'])
            ->assertSessionHas('success');
        $this->assertSame('VT9', $order->fresh()->tracking_code);
        $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.tracking_code', 'VT9')->where('order.shipping_carrier', 'Viettel Post'));
    }
}
