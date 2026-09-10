<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::latest()->get();

        return Inertia::render('Admin/Pages/Index', [
            'pages' => $pages,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Pages/Create');
    }

    public function store(Request $request, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:pages',
            'body' => 'required|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean',
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
            $redirects->assertLegacySlugAvailable('page', $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        if (Category::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một danh mục.'])->withInput();
        }
        Page::create($validated + [
            'slug_source' => $validated['title'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => now(),
        ]);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Tạo trang thành công');
    }

    public function edit(Page $page)
    {
        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page,
        ]);
    }

    public function update(Request $request, Page $page, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:pages,slug,'.$page->id,
            'body' => 'required|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean',
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
            $redirects->assertSlugChangeAllowed($page, $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        if (Category::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một danh mục.'])->withInput();
        }
        $oldSlug = (string) $page->slug;
        $page->update($validated + [
            'slug_source' => $validated['title'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => $page->slug_locked_at ?: now(),
        ]);
        $redirects->recordPageChange($page, $oldSlug, $request->user()?->id);

        return redirect()->route('admin.pages.index')
            ->with('success', 'Cập nhật trang thành công');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Xóa trang thành công');
    }
}
