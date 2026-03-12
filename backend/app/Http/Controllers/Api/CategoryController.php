<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SpecificationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all categories (tree structure)
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    /**
     * Homepage sections: parent categories with product_count + sample products
     */
    public function homepageSections(): JsonResponse
    {
        $parents = Category::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $sections = [];

        foreach ($parents as $parent) {
            // Collect all category IDs (parent + children)
            $catIds = collect([$parent->id])
                ->merge($parent->children->pluck('id'))
                ->toArray();

            // Count products
            $productCount = Product::whereIn('category_id', $catIds)
                ->where('is_active', true)
                ->count();

            if ($productCount === 0) {
                continue;
            }

            // Get sample products (newest 8)
            $products = Product::with(['category', 'brand', 'images'])
                ->whereIn('category_id', $catIds)
                ->where('is_active', true)
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $sections[] = [
                'category' => [
                    'id'          => $parent->id,
                    'name'        => $parent->name,
                    'slug'        => $parent->slug,
                    'description' => $parent->description,
                    'image'       => $parent->image,
                    'icon'        => $parent->icon,
                ],
                'children'      => $parent->children->map(fn ($c) => [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'icon' => $c->icon,
                ]),
                'product_count' => $productCount,
                'products'      => $products,
            ];
        }

        return response()->json($sections);
    }

    /**
     * Get single category with filters metadata + paginated products
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        $category = Category::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }, 'parent'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Collect all category IDs (self + children)
        $catIds = collect([$category->id]);
        if ($category->children->isNotEmpty()) {
            $catIds = $catIds->merge($category->children->pluck('id'));
        }
        $catIdsArr = $catIds->toArray();

        // ---- Build product query with filters ----
        $query = Product::with(['category', 'brand', 'images'])
            ->whereIn('category_id', $catIdsArr)
            ->where('is_active', true);

        // Sub-category filter
        if ($request->filled('sub_category')) {
            $sub = Category::where('slug', $request->sub_category)
                ->where('parent_id', $category->id)
                ->first();
            if ($sub) {
                $query->where('category_id', $sub->id);
            }
        }

        // Brand filter (comma-separated slugs)
        if ($request->filled('brands')) {
            $brandSlugs = explode(',', $request->brands);
            $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $brandSlugs));
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_price', '>=', $request->min_price)
                    ->orWhere(function ($q2) use ($request) {
                        $q2->whereNull('sale_price')
                            ->where('price', '>=', $request->min_price);
                    });
            });
        }
        if ($request->filled('max_price')) {
            $query->where(function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->whereNotNull('sale_price')
                        ->where('sale_price', '<=', $request->max_price);
                })->orWhere(function ($q2) use ($request) {
                    $q2->whereNull('sale_price')
                        ->where('price', '<=', $request->max_price);
                });
            });
        }

        // In stock only
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Specification filters (spec_<key_id>=value)
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'spec_') && $value !== null && $value !== '') {
                $specKeyId = (int) str_replace('spec_', '', $key);
                $values = explode(',', $value);
                $query->whereHas('specifications', function ($q) use ($specKeyId, $values) {
                    $q->where('specification_key_id', $specKeyId)
                        ->whereIn('value_string', $values);
                });
            }
        }

        // Sorting
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderByRaw('COALESCE(sale_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) DESC'),
            'name_asc'   => $query->orderBy('name', 'asc'),
            'name_desc'  => $query->orderBy('name', 'desc'),
            'popular'    => $query->orderByDesc('sold_count'),
            'rating'     => $query->orderByDesc('views_count'),
            default      => $query->orderByDesc('created_at'),
        };

        $products = $query->paginate($request->input('per_page', 20));

        // ---- Build filter metadata ----

        // Brands available in this category
        $brandIds = Product::whereIn('category_id', $catIdsArr)
            ->where('is_active', true)
            ->whereNotNull('brand_id')
            ->distinct()
            ->pluck('brand_id');
        $brands = Brand::whereIn('id', $brandIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo']);

        // Price range for this category
        $priceStats = Product::whereIn('category_id', $catIdsArr)
            ->where('is_active', true)
            ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
            ->first();

        // Filterable specs for this category
        $specFilters = [];
        $componentTypeId = $category->component_type_id;
        if ($componentTypeId) {
            $specKeys = SpecificationKey::where('component_type_id', $componentTypeId)
                ->where('is_filterable', true)
                ->orderBy('display_order')
                ->get();

            foreach ($specKeys as $specKey) {
                $values = \DB::table('product_specifications')
                    ->join('products', 'products.id', '=', 'product_specifications.product_id')
                    ->whereIn('products.category_id', $catIdsArr)
                    ->where('products.is_active', true)
                    ->where('product_specifications.specification_key_id', $specKey->id)
                    ->whereNotNull('product_specifications.value_string')
                    ->distinct()
                    ->orderBy('product_specifications.value_string')
                    ->pluck('product_specifications.value_string');

                if ($values->isNotEmpty()) {
                    $specFilters[] = [
                        'key_id' => $specKey->id,
                        'label'  => $specKey->label,
                        'unit'   => $specKey->unit,
                        'type'   => $specKey->data_type,
                        'values' => $values,
                    ];
                }
            }
        }

        return response()->json([
            'category' => $category,
            'products' => $products,
            'filters'  => [
                'brands'       => $brands,
                'price_range'  => [
                    'min' => (int) ($priceStats->min_price ?? 0),
                    'max' => (int) ($priceStats->max_price ?? 0),
                ],
                'specs'        => $specFilters,
            ],
        ]);
    }
}
