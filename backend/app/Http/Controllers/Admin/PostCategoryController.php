<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostCategoryController extends Controller
{
    public function index()
    {
        $categories = PostCategory::withCount('posts')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/PostCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/PostCategories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:post_categories',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        PostCategory::create($validated);

        return redirect()->route('admin.post-categories.index')
            ->with('success', 'Danh mục đã được tạo.');
    }

    public function edit(PostCategory $postCategory)
    {
        return Inertia::render('Admin/PostCategories/Edit', [
            'category' => $postCategory,
        ]);
    }

    public function update(Request $request, PostCategory $postCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:post_categories,slug,' . $postCategory->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $postCategory->update($validated);

        return redirect()->route('admin.post-categories.index')
            ->with('success', 'Danh mục đã được cập nhật.');
    }

    public function destroy(PostCategory $postCategory)
    {
        $postCategory->delete();

        return redirect()->route('admin.post-categories.index')
            ->with('success', 'Danh mục đã được xóa.');
    }
}
