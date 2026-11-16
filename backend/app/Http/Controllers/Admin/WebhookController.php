<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Webhooks\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Admin → Webhook: where order/contact events are sent (hook.laptopplus.vn), the secret and the delivery log. */
class WebhookController extends Controller
{
    public function index(): Response
    {
        $endpoint = WebhookEndpoint::current();

        return Inertia::render('Admin/Integrations/Webhook', [
            'endpoint' => [
                'enabled' => (bool) $endpoint->enabled,
                'url' => $endpoint->url,
                'events' => array_values((array) $endpoint->events),
                'has_secret' => filled($endpoint->secret),
                'secret_rotated_at' => $endpoint->secret_rotated_at?->toIso8601String(),
            ],
            'events' => collect(WebhookEndpoint::EVENTS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'deliveries' => WebhookDelivery::query()->latest('id')->limit(50)
                ->get(['id', 'uuid', 'event', 'status', 'attempt_count', 'response_status', 'last_error', 'next_attempt_at', 'sent_at', 'created_at', 'updated_at']),
            'backoff' => WebhookSender::BACKOFF_SECONDS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'url' => ['nullable', 'required_if:enabled,true', 'string', 'max:500', 'url:https'],
            'events' => ['present', 'array'],
            'events.*' => ['string', Rule::in(array_keys(WebhookEndpoint::EVENTS))],
        ], [
            'url.url' => 'URL webhook phải là địa chỉ https:// hợp lệ.',
            'url.required_if' => 'Nhập URL webhook trước khi bật.',
        ]);
        $endpoint = WebhookEndpoint::current();
        if ($data['enabled'] && blank($endpoint->secret)) {
            return back()->withErrors(['enabled' => 'Bấm "Tạo secret" trước khi bật webhook.']);
        }
        $endpoint->fill([
            'enabled' => (bool) $data['enabled'],
            'url' => filled($data['url'] ?? null) ? trim($data['url']) : null,
            'events' => array_values(array_unique($data['events'])),
        ])->save();

        return back()->with('success', 'Đã lưu cấu hình webhook.');
    }

    /** Generate or rotate the signing secret. Shown once, stored encrypted. */
    public function secret(): RedirectResponse
    {
        $secret = 'whsec_'.bin2hex(random_bytes(24));
        $endpoint = WebhookEndpoint::current();
        $rotated = filled($endpoint->secret);
        $endpoint->fill(['secret' => $secret, 'secret_rotated_at' => now()])->save();

        return back()->with('webhook_secret', $secret)->with('success', $rotated
            ? 'Đã đổi secret. Cập nhật secret mới bên nhận ngay, chữ ký cũ không còn hợp lệ.'
            : 'Đã tạo secret. Sao chép ngay, secret chỉ hiện một lần.');
    }

    public function test(WebhookDispatcher $dispatcher): RedirectResponse
    {
        $endpoint = WebhookEndpoint::current();
        if (blank($endpoint->url) || blank($endpoint->secret)) {
            return back()->with('error', 'Lưu URL và tạo secret trước khi gửi thử.');
        }
        $delivery = $dispatcher->ping();

        return $delivery->status === 'sent'
            ? back()->with('success', 'Gửi thử thành công (HTTP '.$delivery->response_status.').')
            : back()->with('error', 'Gửi thử lỗi: '.($delivery->last_error ?: 'không rõ lý do'));
    }

    public function retry(WebhookDelivery $delivery): RedirectResponse
    {
        if (! in_array($delivery->status, ['failed', 'dead'], true) || $delivery->event === 'ping') {
            return back()->with('error', 'Chỉ gửi lại được webhook lỗi.');
        }
        $delivery->update(['status' => 'pending', 'attempt_count' => 0, 'next_attempt_at' => now(), 'locked_until' => null]);
        DeliverWebhook::dispatch($delivery->id);

        return back()->with('success', 'Đã đưa webhook vào hàng đợi gửi lại.');
    }
}
