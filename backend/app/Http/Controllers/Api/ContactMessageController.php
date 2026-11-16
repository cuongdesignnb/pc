<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The storefront contact form (/lien-he). Bots filling the hidden "website" field get a 201 and nothing is saved. */
class ContactMessageController extends Controller
{
    public function store(Request $request, WebhookDispatcher $webhooks): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Đã nhận liên hệ.'], 201);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]{8,30}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ.',
            'email.email' => 'Email không hợp lệ.',
            'message.required' => 'Vui lòng nhập nội dung.',
        ]);

        $message = ContactMessage::create(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $data) + [
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'status' => 'new',
        ]);
        $webhooks->contactCreated($message);

        return response()->json(['message' => 'Cảm ơn anh/chị, Laptop Plus sẽ liên hệ lại sớm.', 'id' => $message->id], 201);
    }
}
