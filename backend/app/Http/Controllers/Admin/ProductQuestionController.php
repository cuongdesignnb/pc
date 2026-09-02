<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductQuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductQuestion::with(['product:id,name,slug', 'user:id,name', 'answers.user:id,name'])->latest();

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('body', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        return Inertia::render('Admin/ProductQuestions/Index', [
            'questions' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'is_approved']),
        ]);
    }

    public function approve(ProductQuestion $question)
    {
        $question->update(['is_approved' => true]);

        return back()->with('success', 'Đã duyệt câu hỏi');
    }

    public function reject(ProductQuestion $question)
    {
        $question->update(['is_approved' => false]);

        return back()->with('success', 'Đã ẩn câu hỏi');
    }

    public function answer(Request $request, ProductQuestion $question)
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $question->answers()->create([
            'user_id' => $request->user()?->id,
            'body' => $validated['body'],
            'is_official' => true,
            'is_approved' => true,
        ]);

        return back()->with('success', 'Đã trả lời câu hỏi');
    }

    public function toggleAnswer(ProductAnswer $answer)
    {
        $answer->update(['is_approved' => ! $answer->is_approved]);

        return back()->with('success', $answer->is_approved ? 'Đã duyệt câu trả lời' : 'Đã ẩn câu trả lời');
    }

    public function destroy(ProductQuestion $question)
    {
        $question->delete();

        return back()->with('success', 'Đã xóa câu hỏi');
    }
}
