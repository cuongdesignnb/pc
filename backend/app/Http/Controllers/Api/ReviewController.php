<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Submit a review for a product.
     * Guests can submit with name/email, authenticated users submit with account identity.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $user = $request->user('sanctum');

        $rules = [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ];

        if ($user) {
            $rules['guest_name'] = ['nullable', 'string', 'max:120'];
            $rules['guest_email'] = ['nullable', 'email', 'max:255'];
        } else {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
        }

        $validated = $request->validate($rules);

        $duplicateQuery = Review::where('product_id', $product->id);

        if ($user) {
            $duplicateQuery->where('user_id', $user->id);
        } else {
            $duplicateQuery
                ->whereNull('user_id')
                ->where('guest_email', $validated['guest_email']);
        }

        if ($duplicateQuery->exists()) {
            return response()->json([
                'message' => 'Bạn đã gửi đánh giá cho sản phẩm này rồi.',
            ], 422);
        }

        Review::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'guest_name' => $user ? null : $validated['guest_name'],
            'guest_email' => $user ? null : $validated['guest_email'],
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'Cảm ơn bạn đã gửi đánh giá. Đánh giá sẽ hiển thị sau khi được duyệt.',
        ], 201);
    }
}
