<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('position')
            ->orderBy('sort_order')
            ->paginate(50);

        return Inertia::render('Admin/Banners/Index', [
            'banners' => $banners,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Banners/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'badge' => 'nullable|string|max:100',
            'image' => 'required|string',
            'link' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'metadata' => 'nullable|array',
            'metadata.gradient' => 'nullable|string',
            'metadata.cta_label' => 'nullable|string',
            'metadata.cta_link' => 'nullable|string',
            'metadata.cta2_label' => 'nullable|string',
            'metadata.cta2_link' => 'nullable|string',
            'metadata.glow_a' => 'nullable|string',
            'metadata.glow_b' => 'nullable|string',
        ]);

        Banner::create($validated);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Tạo banner thành công');
    }

    public function edit(Banner $banner)
    {
        return Inertia::render('Admin/Banners/Edit', [
            'banner' => $banner,
        ]);
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'badge' => 'nullable|string|max:100',
            'image' => 'required|string',
            'link' => 'nullable|string|max:500',
            'position' => 'required|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'metadata' => 'nullable|array',
            'metadata.gradient' => 'nullable|string',
            'metadata.cta_label' => 'nullable|string',
            'metadata.cta_link' => 'nullable|string',
            'metadata.cta2_label' => 'nullable|string',
            'metadata.cta2_link' => 'nullable|string',
            'metadata.glow_a' => 'nullable|string',
            'metadata.glow_b' => 'nullable|string',
        ]);

        $banner->update($validated);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Cập nhật banner thành công');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Xóa banner thành công');
    }
}
