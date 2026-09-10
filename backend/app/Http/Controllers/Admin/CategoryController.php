<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ComponentType;
use App\Models\Filter;
use App\Models\Page;
use App\Models\Product;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent', 'children', 'componentType')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Categories/Create', [
            'categories' => Category::whereNull('parent_id')->get(),
            'componentTypes' => ComponentType::orderBy('display_order')->get(),
        ]);
    }

    // Reserved slugs that conflict with frontend routes
    private static array $reservedSlugs = [
        // Legacy English routes
        'about', 'account', 'auth', 'blog', 'cart', 'checkout',
        'configurator', 'contact', 'orders', 'shipping', 'warranty',
        // Vietnamese routes
        'gioi-thieu', 'tai-khoan', 'tin-tuc', 'gio-hang', 'thanh-toan',
        'cau-hinh', 'lien-he', 'don-hang', 'van-chuyen', 'bao-hanh',
        'dang-nhap', 'dang-ky', 'quen-mat-khau',
        // System
        'admin', 'api', 'san-pham',
    ];

    public function store(Request $request, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255',
                'unique:categories',
                Rule::notIn(self::$reservedSlugs),
            ],
            'parent_id' => 'nullable|exists:categories,id',
            'component_type_id' => 'nullable|exists:component_types,id',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'icon' => 'nullable|string',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_on_pc_website' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
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
            $redirects->assertLegacySlugAvailable('category', $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }

        // Check slug collision with products
        if (Product::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một sản phẩm.'])->withInput();
        }
        if (Page::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một trang.'])->withInput();
        }

        $validated += [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => now(),
        ];
        Category::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Tạo danh mục thành công');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Admin/Categories/Edit', [
            'category' => $category,
            'categories' => Category::whereNull('parent_id')
                ->where('id', '!=', $category->id)
                ->get(),
            'componentTypes' => ComponentType::orderBy('display_order')->get(),
        ]);
    }

    public function update(Request $request, Category $category, VietnameseSlugNormalizer $slugs, SlugRedirectService $redirects)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->ignore($category->id),
                Rule::notIn(self::$reservedSlugs),
            ],
            'parent_id' => 'nullable|exists:categories,id',
            'component_type_id' => 'nullable|exists:component_types,id',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'icon' => 'nullable|string',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'show_on_pc_website' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
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
            $redirects->assertSlugChangeAllowed($category, $validated['slug']);
        } catch (\LogicException $exception) {
            return back()->withErrors(['slug' => $exception->getMessage()])->withInput();
        }

        // Check slug collision with products
        if (Product::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một sản phẩm.'])->withInput();
        }
        if (Page::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "'.$validated['slug'].'" đã được sử dụng bởi một trang.'])->withInput();
        }

        $oldSlug = (string) $category->slug;
        $category->update($validated + [
            'slug_source' => $validated['name'],
            'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
            'slug_locked_at' => $category->slug_locked_at ?: now(),
        ]);
        $redirects->recordCategoryChange($category, $oldSlug, $request->user()?->id);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Cập nhật danh mục thành công');
    }

    public function destroy(Category $category)
    {
        if ($category->children()->count() > 0) {
            return back()->with('error', 'Không thể xóa danh mục có danh mục con');
        }

        if ($category->products()->count() > 0) {
            return back()->with('error', 'Không thể xóa danh mục có sản phẩm');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Xóa danh mục thành công');
    }

    /**
     * Show filter assignment page for a category
     */
    public function editFilters(Category $category)
    {
        $category->load('filters');
        $allFilters = Filter::where('is_active', true)
            ->withCount('values')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/Categories/Filters', [
            'category' => $category,
            'allFilters' => $allFilters,
            'assignedFilterIds' => $category->filters->pluck('id')->toArray(),
        ]);
    }

    /**
     * Update filter assignment for a category
     */
    public function updateFilters(Request $request, Category $category)
    {
        $validated = $request->validate([
            'filter_ids' => 'nullable|array',
            'filter_ids.*' => 'exists:filters,id',
        ]);

        // Sync with sort order
        $syncData = [];
        foreach ($validated['filter_ids'] ?? [] as $index => $filterId) {
            $syncData[$filterId] = ['sort_order' => $index];
        }
        $category->filters()->sync($syncData);

        return redirect()->route('admin.categories.index')
            ->with('success', "Cập nhật bộ lọc cho \"{$category->name}\" thành công");
    }
}
