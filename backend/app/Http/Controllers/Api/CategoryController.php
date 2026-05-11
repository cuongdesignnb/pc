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

        // Load assigned filters for this category (also check parent's filters)
        $filterCategoryId = $category->id;
        $assignedFilters = $category->filters()
            ->where('is_active', true)
            ->with(['activeValues'])
            ->get();

        // If no filters assigned and has parent, try parent's filters
        if ($assignedFilters->isEmpty() && $category->parent_id) {
            $parent = Category::find($category->parent_id);
            if ($parent) {
                $assignedFilters = $parent->filters()
                    ->where('is_active', true)
                    ->with(['activeValues'])
                    ->get();
            }
        }

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

        // Brand filter (comma-separated slugs) — kept for backward compat
        if ($request->filled('brands')) {
            $brandSlugs = explode(',', $request->brands);
            $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $brandSlugs));
        }

        // In stock only
        if ($request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Dynamic filter matching from assigned filters
        foreach ($assignedFilters as $filter) {
            $paramKey = 'f_' . $filter->slug; // e.g. f_cpu=intel-core-i5,intel-core-i7
            if (!$request->filled($paramKey)) continue;

            $selectedSlugs = explode(',', $request->input($paramKey));

            // Get match values for selected slugs
            $selectedValues = $filter->activeValues
                ->whereIn('slug', $selectedSlugs);

            if ($selectedValues->isEmpty()) continue;

            switch ($filter->match_field) {
                case 'specifications_text':
                    $query->where(function ($q) use ($selectedValues) {
                        foreach ($selectedValues as $val) {
                            $q->orWhere('specifications_text', 'LIKE', "%{$val->match_value}%");
                        }
                    });
                    break;

                case 'product_name':
                    $query->where(function ($q) use ($selectedValues) {
                        foreach ($selectedValues as $val) {
                            $q->orWhere('name', 'LIKE', "%{$val->match_value}%");
                        }
                    });
                    break;

                case 'brand':
                    $brandSlugsFromFilter = $selectedValues->pluck('match_value')->toArray();
                    $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $brandSlugsFromFilter));
                    break;

                case 'price':
                    $query->where(function ($q) use ($selectedValues) {
                        foreach ($selectedValues as $val) {
                            $q->orWhere(function ($q2) use ($val) {
                                if ($val->price_min !== null) {
                                    $q2->where(function ($q3) use ($val) {
                                        $q3->where('sale_price', '>=', $val->price_min)
                                            ->orWhere(function ($q4) use ($val) {
                                                $q4->whereNull('sale_price')
                                                    ->where('price', '>=', $val->price_min);
                                            });
                                    });
                                }
                                if ($val->price_max !== null) {
                                    $q2->where(function ($q3) use ($val) {
                                        $q3->where(function ($q4) use ($val) {
                                            $q4->whereNotNull('sale_price')
                                                ->where('sale_price', '<=', $val->price_max);
                                        })->orWhere(function ($q4) use ($val) {
                                            $q4->whereNull('sale_price')
                                                ->where('price', '<=', $val->price_max);
                                        });
                                    });
                                }
                            });
                        }
                    });
                    break;
            }
        }

        // Legacy spec filters (spec_<key_id>=value) — backward compat
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

        // Legacy price range — backward compat
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

        // Build filter groups with product counts
        $filterGroups = [];
        foreach ($assignedFilters as $filter) {
            $group = [
                'id'         => $filter->id,
                'name'       => $filter->name,
                'slug'       => $filter->slug,
                'type'       => $filter->type,
                'match_field' => $filter->match_field,
                'values'     => [],
            ];

            foreach ($filter->activeValues as $val) {
                // Count products matching this filter value
                $countQuery = Product::whereIn('category_id', $catIdsArr)
                    ->where('is_active', true);

                switch ($filter->match_field) {
                    case 'specifications_text':
                        $countQuery->where('specifications_text', 'LIKE', "%{$val->match_value}%");
                        break;
                    case 'product_name':
                        $countQuery->where('name', 'LIKE', "%{$val->match_value}%");
                        break;
                    case 'brand':
                        $countQuery->whereHas('brand', fn ($q) => $q->where('slug', $val->match_value));
                        break;
                    case 'price':
                        if ($val->price_min !== null) {
                            $countQuery->where(function ($q) use ($val) {
                                $q->where('sale_price', '>=', $val->price_min)
                                    ->orWhere(function ($q2) use ($val) {
                                        $q2->whereNull('sale_price')->where('price', '>=', $val->price_min);
                                    });
                            });
                        }
                        if ($val->price_max !== null) {
                            $countQuery->where(function ($q) use ($val) {
                                $q->where(function ($q2) use ($val) {
                                    $q2->whereNotNull('sale_price')->where('sale_price', '<=', $val->price_max);
                                })->orWhere(function ($q2) use ($val) {
                                    $q2->whereNull('sale_price')->where('price', '<=', $val->price_max);
                                });
                            });
                        }
                        break;
                }

                $group['values'][] = [
                    'label' => $val->label,
                    'slug'  => $val->slug,
                    'count' => $countQuery->count(),
                ];
            }

            $filterGroups[] = $group;
        }

        // Legacy spec filters (if no assigned filters, fallback)
        $specFilters = [];
        if ($assignedFilters->isEmpty()) {
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
        }

        return response()->json([
            'category' => $category,
            'products' => $products,
            'filters'  => [
                'brands'        => $brands,
                'price_range'   => [
                    'min' => (int) ($priceStats->min_price ?? 0),
                    'max' => (int) ($priceStats->max_price ?? 0),
                ],
                'groups'        => $filterGroups,
                'specs'         => $specFilters, // Legacy fallback
            ],
        ]);
    }
}

