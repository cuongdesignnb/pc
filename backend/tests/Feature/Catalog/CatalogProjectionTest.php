<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\CatalogProductValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['catalog.storefront_url' => 'https://laptopplus.test']);
    }

    public function test_visible_product_maps_to_deterministic_shared_projection(): void
    {
        $parent = $this->category(['name' => 'Máy tính', 'slug' => 'may-tinh', 'remote_category_id' => 10]);
        $category = $this->category([
            'name' => 'Laptop', 'slug' => 'laptop', 'remote_category_id' => 11, 'parent_id' => $parent->id,
        ]);
        $brand = Brand::create(['name' => 'Dell', 'slug' => 'dell', 'is_active' => true]);
        $product = $this->product($category, [
            'brand_id' => $brand->id,
            'name' => 'Laptop Đồ họa',
            'slug' => 'laptop-do-hoa',
            'sku' => 'DELL-001',
            'remote_product_id' => 9001,
            'description' => '<p>Cấu hình mạnh</p>',
        ]);
        $product->images()->create([
            'url' => 'https://cdn.laptopplus.test/main.jpg', 'is_primary' => true, 'sort_order' => 0,
        ]);
        $product->images()->create([
            'url' => 'https://cdn.laptopplus.test/side.jpg', 'is_primary' => false, 'sort_order' => 1,
        ]);

        $service = app(CatalogProductProjectionService::class);
        $first = $service->project($product->fresh());
        $second = $service->project($product->fresh());

        $this->assertSame('kiot:9001', $first->externalId);
        $this->assertSame('Máy tính > Laptop', $first->categoryPath);
        $this->assertSame('https://laptopplus.test/laptop/laptop-do-hoa', $first->productUrl);
        $this->assertSame('https://cdn.laptopplus.test/main.jpg', $first->imageUrl);
        $this->assertSame(['https://cdn.laptopplus.test/side.jpg'], $first->additionalImageUrls);
        $this->assertSame('in_stock', $first->availability);
        $this->assertSame(5, $first->inventory);
        $this->assertSame($first->checksum, $second->checksum);
        $this->assertTrue(app(CatalogProductValidator::class)->validate($first)->valid);
    }

    public function test_repairing_product_is_out_of_stock_and_invalid_product_reasons_are_explicit(): void
    {
        $category = $this->category();
        $repairing = $this->product($category, [
            'kiot_is_under_repair' => true,
            'kiot_availability_status' => 'repairing',
        ]);
        $repairing->images()->create(['url' => 'https://cdn.laptopplus.test/repair.jpg', 'is_primary' => true]);

        $projection = app(CatalogProductProjectionService::class)->project($repairing->fresh());
        $validation = app(CatalogProductValidator::class)->validate($projection);
        $this->assertSame('out_of_stock', $projection->availability);
        $this->assertSame(0, $projection->inventory);
        $this->assertTrue($validation->valid);
        $this->assertContains('UNDER_REPAIR', $validation->warnings);

        $invalid = $this->product($category, [
            'remote_product_id' => 102,
            'sku' => 'INVALID-102',
            'slug' => 'invalid-102',
            'price' => 0,
        ]);
        $result = app(CatalogProductValidator::class)->validate(
            app(CatalogProductProjectionService::class)->project($invalid),
        );
        $this->assertFalse($result->valid);
        $this->assertContains('PRICE_MISSING', $result->errors);
        $this->assertContains('IMAGE_MISSING', $result->errors);
    }

    public function test_hidden_category_hidden_product_deleted_product_and_non_kiot_product_are_not_commerce_eligible(): void
    {
        $hiddenCategory = $this->category(['show_on_pc_website' => false]);
        $hiddenCategoryProduct = $this->product($hiddenCategory);
        $categoryResult = app(CatalogProductValidator::class)->validate(
            app(CatalogProductProjectionService::class)->project($hiddenCategoryProduct),
        );
        $this->assertContains('CATEGORY_HIDDEN', $categoryResult->errors);

        $visibleCategory = $this->category(['remote_category_id' => 2, 'slug' => 'visible']);
        $hidden = $this->product($visibleCategory, [
            'remote_product_id' => 2, 'sku' => 'HIDDEN-2', 'slug' => 'hidden-2', 'show_on_pc_website' => false,
        ]);
        $this->assertContains('PRODUCT_HIDDEN', app(CatalogProductValidator::class)->validate(
            app(CatalogProductProjectionService::class)->project($hidden),
        )->errors);

        $deleted = $this->product($visibleCategory, [
            'remote_product_id' => 3, 'sku' => 'DELETED-3', 'slug' => 'deleted-3',
        ]);
        $deleted->delete();
        $deletedProjection = app(CatalogProductProjectionService::class)->project(Product::withTrashed()->findOrFail($deleted->id));
        $this->assertContains('PRODUCT_DELETED', app(CatalogProductValidator::class)->validate($deletedProjection)->errors);

        Product::create([
            'category_id' => $visibleCategory->id, 'name' => 'Local', 'slug' => 'local', 'sku' => 'LOCAL',
            'price' => 1000, 'stock_quantity' => 1, 'provider' => null,
        ]);
        $ids = [];
        app(CatalogProductProjectionService::class)->each(function ($projection) use (&$ids): void {
            $ids[] = $projection->externalId;
        });
        $this->assertNotContains('sku:local', $ids);
    }

    private function category(array $overrides = []): Category
    {
        return Category::create($overrides + [
            'provider' => 'kiot',
            'remote_category_id' => 1,
            'name' => 'Laptop',
            'slug' => 'laptop',
            'is_active' => true,
            'show_on_pc_website' => true,
            'provider_sync_status' => 'active',
        ]);
    }

    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create($overrides + [
            'category_id' => $category->id,
            'provider' => 'kiot',
            'remote_product_id' => 1,
            'name' => 'Laptop Test',
            'slug' => 'laptop-test',
            'sku' => 'SKU-1',
            'price' => 15000000,
            'stock_quantity' => 5,
            'inventory_source' => 'kiot',
            'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available',
            'kiot_sellable' => true,
            'kiot_available_quantity' => 5,
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }
}
