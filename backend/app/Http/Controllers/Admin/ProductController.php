<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ComponentType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\SpecificationKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'componentType', 'primaryImage'])
            ->latest();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->status) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => Category::where('is_active', true)->get(),
            'brands' => Brand::where('is_active', true)->get(),
            'filters' => $request->only(['search', 'category_id', 'brand_id', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Create', [
            'categories' => Category::where('is_active', true)->get(),
            'brands' => Brand::where('is_active', true)->get(),
            'componentTypes' => ComponentType::with(['specificationKeys' => fn ($q) => $q->orderBy('display_order')])->orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products',
            'sku' => 'required|string|max:100|unique:products',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'component_type_id' => 'nullable|exists:component_types,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'warranty_months' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            // Images
            'thumbnail' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'string',
            // Specifications
            'specifications_text' => 'nullable|string',
            'compatibility_specs' => 'nullable|array',
            'compatibility_specs.*.specification_key_id' => 'required|exists:specification_keys,id',
            'compatibility_specs.*.value' => 'nullable|string|max:500',
        ]);

        // Check slug collision with categories
        if (Category::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "' . $validated['slug'] . '" đã được sử dụng bởi một danh mục.'])->withInput();
        }

        $productData = collect($validated)->except(['thumbnail', 'gallery', 'compatibility_specs'])->toArray();
        $product = Product::create($productData);

        // Save images
        $sortOrder = 0;
        if (!empty($validated['thumbnail'])) {
            $product->images()->create([
                'url' => $validated['thumbnail'],
                'is_primary' => true,
                'sort_order' => $sortOrder++,
            ]);
        }
        if (!empty($validated['gallery'])) {
            foreach ($validated['gallery'] as $url) {
                if ($url !== ($validated['thumbnail'] ?? '')) {
                    $product->images()->create([
                        'url' => $url,
                        'is_primary' => false,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }
        }

        // Save compatibility specifications
        if (!empty($validated['compatibility_specs'])) {
            foreach ($validated['compatibility_specs'] as $spec) {
                if (!empty($spec['value'])) {
                    $specKey = SpecificationKey::find($spec['specification_key_id']);
                    $data = [
                        'product_id' => $product->id,
                        'specification_key_id' => $spec['specification_key_id'],
                    ];
                    if ($specKey && in_array($specKey->data_type, ['integer', 'decimal'])) {
                        $data['value_numeric'] = $spec['value'];
                    } else {
                        $data['value_string'] = $spec['value'];
                    }
                    ProductSpecification::create($data);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Tạo sản phẩm thành công');
    }

    public function edit(Product $product)
    {
        $product->load(['category', 'brand', 'componentType', 'images', 'specifications.specificationKey']);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => Category::where('is_active', true)->get(),
            'brands' => Brand::where('is_active', true)->get(),
            'componentTypes' => ComponentType::with(['specificationKeys' => fn ($q) => $q->orderBy('display_order')])->orderBy('display_order')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug,' . $product->id,
            'sku' => 'required|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'component_type_id' => 'nullable|exists:component_types,id',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'warranty_months' => 'nullable|integer|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            // Images
            'thumbnail' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'string',
            // Specifications
            'specifications_text' => 'nullable|string',
            'compatibility_specs' => 'nullable|array',
            'compatibility_specs.*.specification_key_id' => 'required|exists:specification_keys,id',
            'compatibility_specs.*.value' => 'nullable|string|max:500',
        ]);

        // Check slug collision with categories
        if (Category::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['slug' => 'Slug "' . $validated['slug'] . '" đã được sử dụng bởi một danh mục.'])->withInput();
        }

        $productData = collect($validated)->except(['thumbnail', 'gallery', 'compatibility_specs'])->toArray();
        $product->update($productData);

        // Rebuild images
        $product->images()->delete();
        $sortOrder = 0;
        if (!empty($validated['thumbnail'])) {
            $product->images()->create([
                'url' => $validated['thumbnail'],
                'is_primary' => true,
                'sort_order' => $sortOrder++,
            ]);
        }
        if (!empty($validated['gallery'])) {
            foreach ($validated['gallery'] as $url) {
                if ($url !== ($validated['thumbnail'] ?? '')) {
                    $product->images()->create([
                        'url' => $url,
                        'is_primary' => false,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }
        }

        // Rebuild compatibility specifications
        $product->specifications()->delete();
        if (!empty($validated['compatibility_specs'])) {
            foreach ($validated['compatibility_specs'] as $spec) {
                if (!empty($spec['value'])) {
                    $specKey = SpecificationKey::find($spec['specification_key_id']);
                    $data = [
                        'product_id' => $product->id,
                        'specification_key_id' => $spec['specification_key_id'],
                    ];
                    if ($specKey && in_array($specKey->data_type, ['integer', 'decimal'])) {
                        $data['value_numeric'] = $spec['value'];
                    } else {
                        $data['value_string'] = $spec['value'];
                    }
                    ProductSpecification::create($data);
                }
            }
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Cập nhật sản phẩm thành công');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Xóa sản phẩm thành công');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Product::with(['category', 'brand', 'primaryImage'])->latest();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->brand_id) $query->where('brand_id', $request->brand_id);

        $products = $query->get();
        $filename = 'san-pham-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            // Header
            fputcsv($handle, [
                'ID', 'Tên sản phẩm', 'SKU', 'Slug', 'Danh mục', 'Thương hiệu',
                'Giá gốc', 'Giá sale', 'Tồn kho', 'Trạng thái', 'Nổi bật',
                'Mô tả ngắn', 'Thông số KT', 'Bảo hành (tháng)',
                'Ảnh chính', 'Meta Title', 'Meta Description',
            ]);
            foreach ($products as $p) {
                fputcsv($handle, [
                    $p->id, $p->name, $p->sku, $p->slug,
                    $p->category?->name, $p->brand?->name,
                    $p->price, $p->sale_price, $p->stock_quantity,
                    $p->is_active ? 'active' : 'inactive',
                    $p->is_featured ? '1' : '0',
                    $p->short_description, $p->specifications_text,
                    $p->warranty_months,
                    $p->primaryImage?->url,
                    $p->meta_title, $p->meta_description,
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), 'r');

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // Read header
        $header = fgetcsv($handle);
        if (!$header || count($header) < 5) {
            fclose($handle);
            return back()->with('error', 'File CSV không đúng định dạng');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 5) continue;

            try {
                $id = trim($row[0] ?? '');
                $name = trim($row[1] ?? '');
                $sku = trim($row[2] ?? '');
                $slug = trim($row[3] ?? '') ?: Str::slug($name);

                if (!$name) continue;

                // Find category by name
                $categoryName = trim($row[4] ?? '');
                $category = $categoryName ? Category::where('name', $categoryName)->first() : null;

                // Find brand by name
                $brandName = trim($row[5] ?? '');
                $brand = $brandName ? Brand::where('name', $brandName)->first() : null;

                $data = [
                    'name' => $name,
                    'sku' => $sku ?: Str::upper(Str::random(8)),
                    'slug' => $slug,
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'price' => floatval($row[6] ?? 0),
                    'sale_price' => !empty($row[7]) ? floatval($row[7]) : null,
                    'stock_quantity' => intval($row[8] ?? 0),
                    'is_active' => ($row[9] ?? 'active') === 'active',
                    'is_featured' => boolval($row[10] ?? false),
                    'short_description' => $row[11] ?? null,
                    'specifications_text' => $row[12] ?? null,
                    'warranty_months' => intval($row[13] ?? 0) ?: null,
                    'meta_title' => $row[15] ?? null,
                    'meta_description' => $row[16] ?? null,
                ];

                if ($id && Product::find($id)) {
                    Product::where('id', $id)->update($data);
                    $updated++;
                } else {
                    // Ensure unique slug
                    $baseSlug = $data['slug'];
                    $counter = 1;
                    while (Product::where('slug', $data['slug'])->exists() || Category::where('slug', $data['slug'])->exists()) {
                        $data['slug'] = $baseSlug . '-' . $counter++;
                    }
                    // Ensure unique SKU
                    $baseSku = $data['sku'];
                    $counter = 1;
                    while (Product::where('sku', $data['sku'])->exists()) {
                        $data['sku'] = $baseSku . '-' . $counter++;
                    }
                    $product = Product::create($data);

                    // Import thumbnail if provided
                    $thumbnail = trim($row[14] ?? '');
                    if ($thumbnail) {
                        $product->images()->create(['url' => $thumbnail, 'is_primary' => true, 'sort_order' => 0]);
                    }
                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = "Dòng {$line}: {$e->getMessage()}";
            }
        }
        fclose($handle);

        $msg = "Import xong: {$created} sản phẩm mới, {$updated} cập nhật.";
        if (count($errors) > 0) {
            $msg .= ' Lỗi: ' . implode('; ', array_slice($errors, 0, 5));
        }

        return back()->with('success', $msg);
    }
}
