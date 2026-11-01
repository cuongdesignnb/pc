<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductManufactureYearTest extends TestCase
{
    use RefreshDatabase;

    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->laptops = Category::create(['name' => 'Laptop mới', 'slug' => 'laptop-moi', 'is_active' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_list_filters_products_without_a_year(): void
    {
        $this->product(['name' => 'Có năm', 'manufacture_year' => 2022]);
        $missing = $this->product(['name' => 'Chưa có năm']);

        $this->get('/admin/products?manufacture_year=missing')->assertInertia(fn ($page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.id', $missing->id));
    }

    public function test_bulk_sets_the_year_for_ticked_products_or_every_filtered_product(): void
    {
        $a = $this->product();
        $b = $this->product();
        $other = Category::create(['name' => 'Linh kiện', 'slug' => 'linh-kien', 'is_active' => true]);
        $part = $this->product(['category_id' => $other->id]);

        $this->post('/admin/products/manufacture-year', ['year' => 2023, 'product_ids' => [$a->id]])->assertSessionHasNoErrors();
        $this->assertSame(2023, $a->fresh()->manufacture_year);
        $this->assertNull($b->fresh()->manufacture_year);

        $this->post('/admin/products/manufacture-year', [
            'year' => 2024, 'all_filtered' => true, 'category_id' => $this->laptops->id, 'manufacture_year' => 'missing',
        ])->assertSessionHas('success');
        $this->assertSame(2023, $a->fresh()->manufacture_year);
        $this->assertSame(2024, $b->fresh()->manufacture_year);
        $this->assertNull($part->fresh()->manufacture_year);

        $this->post('/admin/products/manufacture-year', ['year' => 1800, 'product_ids' => [$a->id]])->assertSessionHasErrors('year');
    }

    public function test_excel_template_round_trips_through_import(): void
    {
        $a = $this->product(['sku' => 'DELL-5420']);
        $b = $this->product(['sku' => 'HP-840']);

        $response = $this->get('/admin/products/manufacture-year/template?manufacture_year=missing');
        $response->assertOk();
        $rows = SimpleXlsx::read($response->getFile()->getPathname());
        $this->assertSame(['ID', 'SKU', 'Tên sản phẩm', 'Danh mục', 'Năm sản xuất'], $rows[0]);
        $this->assertCount(3, $rows);

        $path = tempnam(sys_get_temp_dir(), 'year').'.xlsx';
        SimpleXlsx::write($path, $rows[0], [
            [$a->id, 'DELL-5420', $a->name, 'Laptop mới', 2021],
            ['', 'HP-840', $b->name, 'Laptop mới', 'abc'],
            ['', 'KHONG-CO', 'x', '', 2020],
        ]);
        $this->post('/admin/products/manufacture-year/import', [
            'file' => new UploadedFile($path, 'nam.xlsx', null, null, true),
        ])->assertSessionHas('error', fn ($message) => str_contains($message, 'Đã cập nhật năm sản xuất cho 1 sản phẩm')
            && str_contains($message, 'dòng 3')
            && str_contains($message, 'dòng 4'));
        $this->assertSame(2021, $a->fresh()->manufacture_year);
        $this->assertNull($b->fresh()->manufacture_year);

        $csv = UploadedFile::fake()->createWithContent('nam.csv', "\xEF\xBB\xBFSKU;Năm sản xuất\nHP-840;2020\n");
        $this->post('/admin/products/manufacture-year/import', ['file' => $csv])->assertSessionHas('success');
        $this->assertSame(2020, $b->fresh()->manufacture_year);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Laptop '.Str::random(5),
            'slug' => 'laptop-'.Str::lower(Str::random(8)),
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'category_id' => $this->laptops->id,
            'price' => 10_000_000,
            'stock_quantity' => 1,
            'is_active' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
