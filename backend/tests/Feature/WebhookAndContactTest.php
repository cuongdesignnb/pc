<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Integrations\Kiot\KiotFulfillmentService;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebhookAndContactTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    /** @var list<Request> */
    private array $requests = [];

    private int $responseStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $this->requests[] = $request;

            return Http::response(['ok' => true], $this->responseStatus);
        });
    }

    private function enable(array $events = ['order.created', 'order.status_changed', 'order.payment_changed', 'contact.created']): void
    {
        WebhookEndpoint::create(['enabled' => true, 'url' => 'https://hook.laptopplus.vn/in', 'secret' => self::SECRET, 'events' => $events]);
    }

    private function order(): Order
    {
        $order = Order::create([
            'order_number' => 'LP'.fake()->unique()->numerify('######'),
            'subtotal' => 5_000_000, 'discount' => 0, 'shipping_fee' => 0, 'total' => 5_000_000,
            'payment_status' => 'unpaid', 'payment_method' => 'cod', 'order_status' => 'pending',
            'shipping_name' => 'Nguyễn Văn A', 'shipping_phone' => '0912345678', 'shipping_address' => '1 Phố Huế',
        ]);
        $order->items()->create(['product_name' => 'Chuột', 'sku' => 'M1', 'quantity' => 1, 'price' => 5_000_000, 'total' => 5_000_000]);

        return $order;
    }

    public function test_nothing_is_sent_while_webhooks_are_off(): void
    {
        $this->order()->update(['order_status' => 'confirmed']);
        $this->postJson('/api/v1/contact-messages', ['name' => 'A', 'phone' => '0912345678', 'message' => 'Hỏi giá'])->assertCreated();

        $this->assertSame([], $this->requests);
        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_status_and_payment_changes_are_signed_and_sent(): void
    {
        $this->enable();
        $order = $this->order();

        $order->update(['order_status' => 'confirmed']);
        $order->update(['payment_status' => 'paid', 'amount_paid' => 5_000_000]);

        $this->assertCount(2, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('https://hook.laptopplus.vn/in', $request->url());
        $this->assertSame('order.status_changed', $request->header('X-Laptopplus-Event')[0]);
        $timestamp = $request->header('X-Laptopplus-Timestamp')[0];
        $this->assertSame('sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), self::SECRET), $request->header('X-Laptopplus-Signature')[0]);
        $body = json_decode($request->body(), true);
        $this->assertSame($request->header('X-Laptopplus-Delivery')[0], $body['id']);
        $this->assertSame(['order.status_changed', 'confirmed', 'pending'], [$body['event'], $body['data']['status'], $body['data']['previous']['status']]);
        $this->assertArrayHasKey('kiot_sync_status', $body['data']);
        $this->assertArrayHasKey('tracking_code', $body['data']);

        $payment = json_decode($this->requests[1]->body(), true);
        $this->assertSame(['order.payment_changed', 'paid', 5_000_000, 'unpaid', 0], [
            $payment['event'], $payment['data']['payment_status'], $payment['data']['amount_paid'],
            $payment['data']['previous']['payment_status'], $payment['data']['previous']['amount_paid'],
        ]);
        $this->assertSame(['sent', 'sent'], WebhookDelivery::orderBy('id')->pluck('status')->all());
    }

    public function test_failures_back_off_then_stop_and_never_break_the_order(): void
    {
        $this->enable(['order.status_changed']);
        $this->responseStatus = 500;
        $order = $this->order();

        $order->update(['order_status' => 'confirmed']);
        $this->assertSame('confirmed', $order->fresh()->order_status);

        $delivery = WebhookDelivery::firstOrFail();
        $this->assertSame(['failed', 1, 500], [$delivery->status, $delivery->attempt_count, $delivery->response_status]);
        $this->assertEqualsWithDelta(now()->addMinute()->timestamp, $delivery->next_attempt_at->timestamp, 2);

        $this->artisan('webhooks:retry')->assertSuccessful();
        $this->assertCount(1, $this->requests, 'Not due yet.');

        foreach (WebhookSender::BACKOFF_SECONDS as $seconds) {
            $this->travel($seconds + 1)->seconds();
            $this->artisan('webhooks:retry')->assertSuccessful();
        }
        $delivery->refresh();
        $this->assertSame(['dead', 6], [$delivery->status, $delivery->attempt_count]);
        $this->assertCount(6, $this->requests);

        // "Gửi lại" from the admin log.
        $this->responseStatus = 200;
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $this->actingAs($admin)->post("/admin/webhooks/deliveries/{$delivery->id}/retry")->assertSessionHas('success');
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_checkout_sends_order_created_with_items(): void
    {
        $this->enable(['order.created']);
        $product = Product::create([
            'name' => 'Chuột Logitech', 'slug' => 'chuot-'.Str::lower(Str::random(6)), 'sku' => 'WEB-NEW-M1',
            'price' => 500_000, 'stock_quantity' => 3, 'is_active' => true, 'inventory_source' => 'local', 'weight' => 200,
        ]);

        $this->postJson('/api/v1/orders', [
            'checkout_idempotency_key' => (string) Str::uuid(), 'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A', 'customer_email' => 'a@example.com', 'customer_phone' => '0987654321',
            'shipping_address' => '1 Phố Huế', 'shipping_city' => 'Hà Nội', 'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $created = collect($this->requests)->map(fn (Request $r) => json_decode($r->body(), true))->firstWhere('event', 'order.created');
        $this->assertNotNull($created);
        $this->assertSame('WEB-NEW-M1', $created['data']['items'][0]['sku']);
    }

    public function test_contact_form_saves_validates_and_sends_webhook(): void
    {
        $this->enable(['contact.created']);

        $this->postJson('/api/v1/contact-messages', ['name' => '', 'phone' => 'abc', 'message' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone', 'message']);
        $this->postJson('/api/v1/contact-messages', ['name' => 'Bot', 'phone' => '0912345678', 'message' => 'spam', 'website' => 'http://spam'])
            ->assertCreated();
        $this->assertSame(0, ContactMessage::count());

        $this->postJson('/api/v1/contact-messages', [
            'name' => 'Trần B', 'phone' => '0912 345 678', 'email' => 'b@example.com', 'subject' => 'Báo giá',
            'message' => 'Cần 5 máy Dell', 'page_url' => 'https://laptopplus.vn/lien-he',
        ])->assertCreated()->assertJsonPath('message', 'Cảm ơn anh/chị, Laptop Plus sẽ liên hệ lại sớm.');

        $message = ContactMessage::firstOrFail();
        $this->assertSame(['Trần B', 'new'], [$message->name, $message->status]);
        $body = json_decode($this->requests[0]->body(), true);
        $this->assertSame(['contact.created', 'Cần 5 máy Dell', '0912 345 678'], [$body['event'], $body['data']['message'], $body['data']['phone']]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/contact-messages', ['name' => 'C', 'phone' => '0912345678', 'message' => 'x']);
        }
        $this->postJson('/api/v1/contact-messages', ['name' => 'C', 'phone' => '0912345678', 'message' => 'x'])->assertStatus(429);
    }

    public function test_admin_configures_rotates_secret_and_pings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));

        $this->actingAs($admin)->put('/admin/webhooks', ['enabled' => true, 'url' => 'https://hook.laptopplus.vn/in', 'events' => ['order.created']])
            ->assertSessionHasErrors('enabled');
        $this->actingAs($admin)->post('/admin/webhooks/secret')->assertSessionHas('webhook_secret');
        $secret = session('webhook_secret');
        $this->assertStringStartsWith('whsec_', $secret);
        $this->assertNotSame($secret, WebhookEndpoint::query()->toBase()->value('secret'), 'Stored encrypted.');

        $this->actingAs($admin)->put('/admin/webhooks', ['enabled' => true, 'url' => 'http://hook.laptopplus.vn/in', 'events' => []])
            ->assertSessionHasErrors('url');
        $this->actingAs($admin)->put('/admin/webhooks', ['enabled' => true, 'url' => 'https://hook.laptopplus.vn/in', 'events' => ['order.created', 'contact.created']])
            ->assertSessionHas('success');
        $this->assertTrue(WebhookEndpoint::current()->subscribes('contact.created'));

        $this->actingAs($admin)->post('/admin/webhooks/test')->assertSessionHas('success');
        $ping = $this->requests[0];
        $this->assertSame('ping', $ping->header('X-Laptopplus-Event')[0]);
        $this->assertSame('sha256='.hash_hmac('sha256', $ping->header('X-Laptopplus-Timestamp')[0].'.'.$ping->body(), $secret), $ping->header('X-Laptopplus-Signature')[0]);

        $this->actingAs($admin)->get('/admin/webhooks')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations/Webhook')
            ->where('endpoint.has_secret', true)
            ->missing('endpoint.secret')
            ->where('deliveries.0.event', 'ping'));
    }

    public function test_kiot_fulfillment_switch_has_its_own_toggle_and_stays_off_the_settings_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['settings.edit', 'settings.view'] as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $this->assertFalse(KiotFulfillmentService::enabled());

        $this->actingAs($admin)->patch('/admin/integrations/kiot/fulfillment', ['enabled' => true])->assertSessionHas('success');
        $this->assertTrue(KiotFulfillmentService::enabled());
        $this->actingAs($admin)->get('/admin/integrations/kiot')->assertOk()
            ->assertInertia(fn ($page) => $page->where('configuration.auto_fulfillment_enabled', true));
        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertInertia(fn ($page) => $page->missing('settings.kiot_integration'));
    }
}
