<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Brands/Create');
    }

    public function store(Request $request, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:brands',
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
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
            $redirects->assertLegacySlugAvailable('brand', $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        Brand::create($validated + [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => now(),
        ]);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Tạo thương hiệu thành công');
    }

    public function edit(Brand $brand)
    {
        return Inertia::render('Admin/Brands/Edit', [
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, Brand $brand, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:160|unique:brands,slug,'.$brand->id,
            'logo' => 'nullable|string',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
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
            $redirects->assertSlugChangeAllowed($brand, $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }
        $oldSlug = (string) $brand->slug;
        $brand->update($validated + [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => $brand->slug_locked_at ?: now(),
        ]);
        $redirects->recordBrandChange($brand, $oldSlug, $request->user()?->id);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Cập nhật thương hiệu thành công');
    }

    public function destroy(Brand $brand)
    {
        if ($brand->products()->count() > 0) {
            return back()->with('error', 'Không thể xóa thương hiệu có sản phẩm');
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Xóa thương hiệu thành công');
    }
}
