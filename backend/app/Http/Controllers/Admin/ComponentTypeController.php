<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComponentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComponentTypeController extends Controller
{
    public function index()
    {
        $types = ComponentType::withCount('products')
            ->orderBy('display_order')
            ->get();

        return Inertia::render('Admin/ComponentTypes/Index', [
            'types' => $types,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/ComponentTypes/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:component_types',
            'display_order' => 'integer|min:0',
            'is_required' => 'boolean',
        ]);

        ComponentType::create($validated);

        return redirect()->route('admin.component-types.index')
            ->with('success', 'Tạo loại linh kiện thành công');
    }

    public function edit(ComponentType $componentType)
    {
        return Inertia::render('Admin/ComponentTypes/Edit', [
            'type' => $componentType,
        ]);
    }

    public function update(Request $request, ComponentType $componentType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:component_types,slug,' . $componentType->id,
            'display_order' => 'integer|min:0',
            'is_required' => 'boolean',
        ]);

        $componentType->update($validated);

        return redirect()->route('admin.component-types.index')
            ->with('success', 'Cập nhật loại linh kiện thành công');
    }

    public function destroy(ComponentType $componentType)
    {
        if ($componentType->products()->count() > 0) {
            return back()->with('error', 'Không thể xóa loại linh kiện đang có sản phẩm');
        }

        $componentType->delete();

        return redirect()->route('admin.component-types.index')
            ->with('success', 'Xóa loại linh kiện thành công');
    }
}
