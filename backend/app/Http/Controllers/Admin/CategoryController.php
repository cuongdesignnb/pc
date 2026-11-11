<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ComponentType;
use App\Models\Filter;
use App\Models\Page;
use App\Models\Product;
use App\Services\Affiliate\CommissionRules;
use App\Services\Catalog\WarrantyPackages;
use App\Services\Seo\SlugRedirectService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    /** Defaults for the product information required by Nghị định 37/2026. */
    private const COMPLIANCE_RULES = [
        'is_refurbished' => 'boolean',
        'warning_info' => 'nullable|string|max:5000',
        'usage_instructions' => 'nullable|string|max:10000',
    ];

    public function index()
    {
        $categories = Category::with('parent', 'children.children.componentType', 'children.componentType', 'componentType')
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
            'categories' => $this->parentOptions(),
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
        'cau-hinh', 'lien-he', 'don-hang', 'van-chuyen', 'bao-hanh', 'tra-cuu-bao-hanh',
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
            'technical_heading' => 'nullable|in:auto,configuration,specifications',
        ] + WarrantyPackages::validationRules() + CommissionRules::validationRules() + self::COMPLIANCE_RULES);
        if (array_key_exists('warranty_packages', $validated)) {
            $validated['warranty_packages'] = WarrantyPackages::forStorage($validated['warranty_packages']);
        }
        if (array_key_exists('affiliate_commission', $validated)) {
            $validated['affiliate_commission'] = CommissionRules::normalise($validated['affiliate_commission']);
        }

        $validated['technical_heading'] = $validated['technical_heading'] ?? 'auto';
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

        if ($error = $this->parentError($validated['parent_id'] ?? null)) {
            return back()->withErrors(['parent_id' => $error])->withInput();
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
            'categories' => $this->parentOptions($category),
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
            'technical_heading' => 'nullable|in:auto,configuration,specifications',
        ] + WarrantyPackages::validationRules() + CommissionRules::validationRules() + self::COMPLIANCE_RULES);
        if (array_key_exists('warranty_packages', $validated)) {
            $validated['warranty_packages'] = WarrantyPackages::forStorage($validated['warranty_packages']);
        }
        if (array_key_exists('affiliate_commission', $validated)) {
            $validated['affiliate_commission'] = CommissionRules::normalise($validated['affiliate_commission']);
        }

        $validated['technical_heading'] = $validated['technical_heading'] ?? $category->technical_heading ?? 'auto';
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

        if ($error = $this->parentError($validated['parent_id'] ?? null, $category)) {
            return back()->withErrors(['parent_id' => $error])->withInput();
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
     * Categories can be three levels deep (Laptop cũ → Dell → Latitude), so a
     * parent is a top-level or second-level category. When editing, the
     * category itself and its own sub-categories are left out.
     */
    private function parentOptions(?Category $editing = null): array
    {
        $roots = Category::with('children')->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->get();
        $excluded = $editing ? $this->subtreeIds($editing) : [];
        $options = [];
        foreach ($roots as $root) {
            if (in_array($root->id, $excluded, true)) {
                continue;
            }
            $options[] = ['id' => $root->id, 'name' => $root->name];
            // A category with sub-categories of its own can only go one level down.
            if ($editing && $editing->children()->exists()) {
                continue;
            }
            foreach ($root->children->sortBy('sort_order') as $child) {
                if (! in_array($child->id, $excluded, true)) {
                    $options[] = ['id' => $child->id, 'name' => $root->name.' → '.$child->name];
                }
            }
        }

        return $options;
    }

    public function parentError(mixed $parentId, ?Category $editing = null): ?string
    {
        if (! $parentId) {
            return null;
        }
        $parent = Category::with('parent')->find($parentId);
        if (! $parent) {
            return null;
        }
        if ($editing && in_array($parent->id, $this->subtreeIds($editing), true)) {
            return 'Không thể chọn chính danh mục này hoặc danh mục con của nó làm danh mục cha.';
        }
        $parentDepth = $parent->parent_id ? 2 : 1;
        $ownDepth = $editing ? $this->subtreeDepth($editing) : 1;
        if ($parent->parent?->parent_id || $parentDepth + $ownDepth > 3) {
            return 'Danh mục chỉ được tối đa 3 cấp (ví dụ Laptop cũ → Dell → Latitude).';
        }

        return null;
    }

    /** @return list<int> the category and everything below it */
    private function subtreeIds(Category $category): array
    {
        $ids = [$category->id];
        for ($level = [$category->id], $depth = 0; $level !== [] && $depth < 5; $depth++) {
            $level = Category::whereIn('parent_id', $level)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /** Levels in the category's own subtree: 1 alone, 2 with children, … */
    private function subtreeDepth(Category $category): int
    {
        $depth = 0;
        for ($level = [$category->id]; $level !== [] && $depth < 6; $depth++) {
            $level = Category::whereIn('parent_id', $level)->pluck('id')->all();
        }

        return $depth;
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
