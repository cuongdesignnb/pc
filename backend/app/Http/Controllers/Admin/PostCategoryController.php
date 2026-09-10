<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
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

    public function store(Request $request, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:post_categories',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $validated['slug'] = $slugs->validateCustom($validated['slug']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        if ($slugs->isReserved($validated['slug'])) {
            return back()->withErrors(['slug' => 'Slug này dành riêng cho route hệ thống.'])->withInput();
        }
        try {
            $redirects->assertLegacySlugAvailable('post-category', $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        PostCategory::create($validated + [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => now(),
        ]);

        return redirect()->route('admin.post-categories.index')
            ->with('success', 'Danh mục đã được tạo.');
    }

    public function edit(PostCategory $postCategory)
    {
        return Inertia::render('Admin/PostCategories/Edit', [
            'category' => $postCategory,
        ]);
    }

    public function update(Request $request, PostCategory $postCategory, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:post_categories,slug,'.$postCategory->id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $validated['slug'] = $slugs->validateCustom($validated['slug']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        if ($slugs->isReserved($validated['slug'])) {
            return back()->withErrors(['slug' => 'Slug này dành riêng cho route hệ thống.'])->withInput();
        }
        try {
            $redirects->assertSlugChangeAllowed($postCategory, $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        $oldSlug = (string) $postCategory->slug;
        $postCategory->update($validated + [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => $postCategory->slug_locked_at ?: now(),
        ]);
        $redirects->recordPostCategoryChange($postCategory, $oldSlug, $request->user()?->id);

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
