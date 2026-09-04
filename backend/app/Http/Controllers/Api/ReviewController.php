<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sort' => ['nullable', 'in:newest,oldest,highest,lowest'],
        ]);
        $product = Product::where('slug', $slug)->visibleOnStorefront()->firstOrFail();
        $query = Review::with(['user:id,name', 'order.items:id,order_id,product_id', 'media:id,review_id,url,sort_order'])
            ->where('product_id', $product->id)
            ->where('is_approved', true);
        if (isset($validated['rating'])) {
            $query->where('rating', $validated['rating']);
        }
        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'highest' => $query->orderByDesc('rating')->latest(),
            'lowest' => $query->orderBy('rating')->latest(),
            default => $query->latest(),
        };
        $reviews = $query->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'reviews' => ReviewResource::collection($reviews->getCollection())->resolve(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Submit a review for a product.
     * Guests can submit with name/email, authenticated users submit with account identity.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->visibleOnStorefront()
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

        $verifiedOrderId = $user
            ? Order::where('user_id', $user->id)
                ->where('order_status', '!=', 'cancelled')
                ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
                ->latest()
                ->value('id')
            : null;

        Review::create([
            'product_id' => $product->id,
            'order_id' => $verifiedOrderId,
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
