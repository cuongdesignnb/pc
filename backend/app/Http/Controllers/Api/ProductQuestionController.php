<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductQuestionController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
        $product = Product::where('slug', $slug)->visibleOnStorefront()->firstOrFail();
        $questions = ProductQuestion::with([
            'user:id,name',
            'answers' => fn ($query) => $query->where('is_approved', true)->with('user:id,name')->oldest(),
        ])->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest()
            ->paginate($validated['per_page'] ?? 10);

        return response()->json([
            'questions' => $questions->getCollection()->map(fn (ProductQuestion $question) => $this->present($question))->values(),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->visibleOnStorefront()->firstOrFail();
        $user = $request->user('sanctum');
        $rules = ['body' => ['required', 'string', 'min:5', 'max:2000']];
        if ($user) {
            $rules['guest_name'] = ['nullable', 'string', 'max:120'];
            $rules['guest_email'] = ['nullable', 'email', 'max:255'];
        } else {
            $rules['guest_name'] = ['required', 'string', 'max:120'];
            $rules['guest_email'] = ['required', 'email', 'max:255'];
        }
        $validated = $request->validate($rules);
        ProductQuestion::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'guest_name' => $user ? null : $validated['guest_name'],
            'guest_email' => $user ? null : $validated['guest_email'],
            'body' => $validated['body'],
            'is_approved' => false,
        ]);

        return response()->json(['message' => 'Câu hỏi đã được gửi và đang chờ duyệt.'], 201);
    }

    private function present(ProductQuestion $question): array
    {
        return [
            'id' => $question->id,
            'asker_name' => $question->user?->name ?? $question->guest_name ?? 'Khách hàng',
            'body' => $question->body,
            'created_at' => $question->created_at?->toISOString(),
            'answers' => $question->answers->map(fn ($answer) => [
                'id' => $answer->id,
                'author_name' => $answer->user?->name ?? 'PC Shop',
                'body' => $answer->body,
                'is_official' => $answer->is_official,
                'created_at' => $answer->created_at?->toISOString(),
            ])->values(),
        ];
    }
}
