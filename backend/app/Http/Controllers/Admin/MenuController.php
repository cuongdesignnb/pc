<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $menus = Menu::withCount('allItems')->get();

        return Inertia::render('Admin/Menus/Index', [
            'menus' => $menus,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Menus/Create', [
            'locations' => $this->getLocations(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:menus',
            'location'    => 'required|string',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        Menu::create($validated);

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menu đã được tạo thành công.');
    }

    public function edit(Menu $menu): Response
    {
        $menu->load([
            'items' => function ($q) {
                $q->with([
                    'category',
                    'children' => function ($q2) {
                        $q2->orderBy('sort_order')->with([
                            'category',
                            'children' => function ($q3) {
                                $q3->orderBy('sort_order')->with([
                                    'category',
                                    'children' => fn($q4) => $q4->orderBy('sort_order')->with('category'),
                                ]);
                            },
                        ]);
                    },
                ]);
            },
        ]);

        return Inertia::render('Admin/Menus/Edit', [
            'menu'       => $menu,
            'locations'  => $this->getLocations(),
            'categories' => Category::with('children.children')
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'location'    => 'required|string',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $menu->update($validated);

        return redirect()->route('admin.menus.edit', $menu)
            ->with('success', 'Menu đã được cập nhật.');
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menu đã được xóa.');
    }

    // ---- Menu Items ----

    public function storeItem(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'parent_id'    => 'nullable|exists:menu_items,id',
            'title'        => 'required|string|max:255',
            'url'          => 'nullable|string|max:500',
            'type'         => 'required|in:custom,category,page',
            'category_id'  => 'nullable|exists:categories,id',
            'icon'         => 'nullable|string|max:50',
            'badge_text'   => 'nullable|string|max:20',
            'badge_color'  => 'nullable|string|max:20',
            'css_class'    => 'nullable|string|max:100',
            'target'       => 'in:_self,_blank',
            'sort_order'   => 'integer|min:0',
            'is_active'    => 'boolean',
            'is_mega'      => 'boolean',
            'mega_columns' => 'integer|min:1|max:6',
            'description'  => 'nullable|string|max:500',
            'image'        => 'nullable|string|max:500',
        ]);

        $validated['menu_id'] = $menu->id;
        MenuItem::create($validated);

        return redirect()->route('admin.menus.edit', $menu)
            ->with('success', 'Menu item đã được thêm.');
    }

    public function updateItem(Request $request, Menu $menu, MenuItem $item)
    {
        $validated = $request->validate([
            'parent_id'    => 'nullable|exists:menu_items,id',
            'title'        => 'required|string|max:255',
            'url'          => 'nullable|string|max:500',
            'type'         => 'required|in:custom,category,page',
            'category_id'  => 'nullable|exists:categories,id',
            'icon'         => 'nullable|string|max:50',
            'badge_text'   => 'nullable|string|max:20',
            'badge_color'  => 'nullable|string|max:20',
            'css_class'    => 'nullable|string|max:100',
            'target'       => 'in:_self,_blank',
            'sort_order'   => 'integer|min:0',
            'is_active'    => 'boolean',
            'is_mega'      => 'boolean',
            'mega_columns' => 'integer|min:1|max:6',
            'description'  => 'nullable|string|max:500',
            'image'        => 'nullable|string|max:500',
        ]);

        $item->update($validated);

        return redirect()->route('admin.menus.edit', $menu)
            ->with('success', 'Menu item đã được cập nhật.');
    }

    public function destroyItem(Menu $menu, MenuItem $item)
    {
        $item->delete();

        return redirect()->route('admin.menus.edit', $menu)
            ->with('success', 'Menu item đã được xóa.');
    }

    public function reorderItems(Request $request, Menu $menu)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menu_items,id',
            'items.*.parent_id' => 'nullable|exists:menu_items,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $data) {
            MenuItem::where('id', $data['id'])->update([
                'parent_id'  => $data['parent_id'],
                'sort_order' => $data['sort_order'],
            ]);
        }

        return redirect()->route('admin.menus.edit', $menu)
            ->with('success', 'Thứ tự menu đã được cập nhật.');
    }

    private function getLocations(): array
    {
        return [
            ['value' => 'header', 'label' => 'Header (Menu chính)'],
            ['value' => 'footer', 'label' => 'Footer (Chân trang)'],
            ['value' => 'sidebar', 'label' => 'Sidebar (Thanh bên)'],
            ['value' => 'mobile', 'label' => 'Mobile (Menu di động)'],
        ];
    }
}
