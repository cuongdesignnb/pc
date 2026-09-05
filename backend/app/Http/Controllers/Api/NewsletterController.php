<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));
        NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'is_active' => true,
                'subscribed_at' => now(),
            ],
        );

        return response()->json(['message' => 'Đăng ký nhận tin thành công.']);
    }
}
