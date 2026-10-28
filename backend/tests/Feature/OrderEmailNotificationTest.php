<?php

namespace Tests\Feature;

use App\Jobs\Orders\SendNewOrderNotification;
use App\Mail\NewOrderNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Mail\StorefrontSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);
    }

    public function test_smtp_password_is_encrypted_and_never_exposed_by_public_settings(): void
    {
        Setting::updateOrCreate(['key' => 'smtp_password'], [
            'value' => '',
            'group' => 'smtp',
            'type' => 'password',
            'label' => 'Mật khẩu SMTP',
            'is_public' => false,
        ]);

        Setting::set('smtp_password', 'app-password-secret');

        $stored = (string) Setting::where('key', 'smtp_password')->value('value');
        $this->assertNotSame('app-password-secret', $stored);
        $this->assertSame('app-password-secret', Setting::get('smtp_password'));
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonMissingPath('smtp_password');
    }

    public function test_configured_smtp_sends_new_order_email_to_store_recipient(): void
    {
        $this->smtpSettings();
        $order = $this->order();
        Mail::fake();

        app(StorefrontSmtpMailer::class)->sendOrderNotification($order);

        Mail::assertSent(NewOrderNotification::class, 1);
    }

    public function test_empty_sender_and_recipient_fall_back_to_smtp_login_and_contact_email(): void
    {
        $this->smtpSettings();
        Setting::set('smtp_from_address', '');
        Setting::set('smtp_order_recipient', '');
        Setting::updateOrCreate(['key' => 'contact_email'], ['value' => 'shop@example.test', 'group' => 'contact', 'type' => 'text', 'label' => 'Email', 'is_public' => true]);
        Setting::clearCache();
        Mail::fake();

        app(StorefrontSmtpMailer::class)->sendOrderNotification($this->order());

        Mail::assertSent(NewOrderNotification::class, fn ($mail) => $mail->hasTo('shop@example.test'));
        $this->assertSame('mailer@example.test', config('mail.from.address'));
    }

    public function test_without_any_recipient_or_contact_email_the_smtp_login_receives_orders(): void
    {
        $this->smtpSettings();
        Setting::set('smtp_from_address', '');
        Setting::set('smtp_order_recipient', '');
        Setting::clearCache();
        Mail::fake();

        app(StorefrontSmtpMailer::class)->sendOrderNotification($this->order());

        Mail::assertSent(NewOrderNotification::class, fn ($mail) => $mail->hasTo('mailer@example.test'));
    }

    public function test_order_email_is_not_sent_while_notifications_switch_is_off(): void
    {
        $this->smtpSettings();
        Setting::set('smtp_order_notifications_enabled', '0');
        Setting::clearCache();
        Mail::fake();

        app(StorefrontSmtpMailer::class)->sendOrderNotification($this->order());

        Mail::assertNothingSent();
    }

    /**
     * Sends through a real SMTP server when SMTP_E2E_HOST points at one
     * (e.g. a Mailpit container), proving the transport settings work.
     */
    public function test_order_email_is_delivered_through_a_real_smtp_server(): void
    {
        $host = (string) env('SMTP_E2E_HOST', '');
        if ($host === '') {
            $this->markTestSkipped('Set SMTP_E2E_HOST to a test SMTP server (e.g. Mailpit) to run.');
        }
        $this->smtpSettings();
        Setting::set('smtp_host', $host);
        Setting::set('smtp_port', (string) env('SMTP_E2E_PORT', 1025));
        Setting::set('smtp_encryption', 'none');
        Setting::clearCache();

        (new SendNewOrderNotification($this->order()->id))->handle(app(StorefrontSmtpMailer::class));

        $messages = json_decode((string) file_get_contents('http://'.$host.':'.env('SMTP_E2E_API_PORT', 8025).'/api/v1/messages'), true);
        $this->assertSame('Đơn hàng mới DH202609090001', $messages['messages'][0]['Subject'] ?? null);
        $this->assertSame('admin@example.test', $messages['messages'][0]['To'][0]['Address'] ?? null);
    }

    public function test_successful_checkout_queues_one_admin_order_notification(): void
    {
        Queue::fake();
        $product = Product::create([
            'name' => 'Sản phẩm có email',
            'slug' => 'san-pham-co-email',
            'sku' => 'SMTP-001',
            'price' => 250000,
            'stock_quantity' => 5,
            'inventory_source' => 'local',
            'is_active' => true,
        ]);
        $payload = [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'Hà Nội',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/orders', $payload)->assertCreated();

        Queue::assertPushed(SendNewOrderNotification::class, 1);
    }

    private function smtpSettings(): void
    {
        foreach ([
            ['smtp_enabled', '1', 'boolean'],
            ['smtp_order_notifications_enabled', '1', 'boolean'],
            ['smtp_host', 'smtp.example.test', 'text'],
            ['smtp_port', '587', 'number'],
            ['smtp_encryption', 'tls', 'select'],
            ['smtp_username', 'mailer@example.test', 'text'],
            ['smtp_password', 'app-password-secret', 'password'],
            ['smtp_from_address', 'orders@example.test', 'text'],
            ['smtp_from_name', 'PC Center', 'text'],
            ['smtp_order_recipient', 'admin@example.test', 'text'],
        ] as [$key, $value, $type]) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'group' => 'smtp',
                'type' => $type,
                'label' => $key,
                'is_public' => false,
            ]);
        }
        Setting::clearCache();
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'DH202609090001',
            'subtotal' => 250000,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => 250000,
            'payment_status' => 'unpaid',
            'payment_method' => 'cod',
            'checkout_mode' => 'cart',
            'order_status' => 'pending',
            'shipping_name' => 'Nguyễn Văn A',
            'shipping_phone' => '0987654321',
            'customer_email' => 'customer@example.com',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'Hà Nội',
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token_hash' => Order::hashAccessToken((string) Str::uuid()),
            'kiot_sync_status' => 'not_required',
        ]);
    }
}
