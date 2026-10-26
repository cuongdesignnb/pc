<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductAiSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);
    }

    public function test_ai_generated_page_is_persisted_on_save(): void
    {
        $category = $this->category();
        $product = $this->product(['category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id, $this->aiPayload($product, $category->id))
            ->assertSessionHasNoErrors();

        $product->refresh()->load(['highlights', 'detailBlocks']);
        $this->assertStringContainsString('<h2>Tổng quan</h2>', $product->description);
        $this->assertSame("CPU: Intel Core i5-6440HQ\nRAM: 8GB DDR4", $product->specifications_text);
        $this->assertSame(['Core i5 4 nhân', 'SSD 256GB'], $product->highlights->pluck('title')->all());
        $this->assertSame(['benchmark_cards', 'use_case_cards'], $product->detailBlocks->pluck('type')->all());
        $this->assertSame('💼', $product->detailBlocks->last()->payload['cards'][0]['icon']);
    }

    public function test_kiot_product_without_mapped_category_saves_content(): void
    {
        $product = $this->product([
            'category_id' => null,
            'inventory_source' => 'kiot',
            'provider' => 'kiot',
            'sku' => 'SP26102479619',
            'price' => 5_000_000,
            'stock_quantity' => 3,
        ]);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id, $this->aiPayload($product, '', [
                'price' => 1, 'stock_quantity' => 0, 'sku' => 'CHANGED',
            ]))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->category_id);
        $this->assertSame('SP26102479619', $product->sku);
        $this->assertSame("CPU: Intel Core i5-6440HQ\nRAM: 8GB DDR4", $product->specifications_text);
        $this->assertStringContainsString('Tổng quan', $product->description);
    }

    public function test_local_product_still_requires_category(): void
    {
        $product = $this->product(['category_id' => null]);

        $this->actingAs($this->admin())
            ->put('/admin/products/'.$product->id, $this->aiPayload($product, ''))
            ->assertSessionHasErrors('category_id');

        $this->assertNull($product->fresh()->specifications_text);
    }

    public function test_edit_page_keeps_hidden_current_category_selectable(): void
    {
        $this->withoutVite();
        $hidden = $this->category(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => false]);
        $otherHidden = $this->category(['name' => 'Khác', 'slug' => 'khac', 'is_active' => false]);
        $product = $this->product(['category_id' => $hidden->id]);

        $this->actingAs($this->admin())
            ->get('/admin/products/'.$product->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Edit')
                ->where('categories', fn ($categories) => collect($categories)->pluck('id')->contains($hidden->id)
                    && ! collect($categories)->pluck('id')->contains($otherHidden->id)));
    }

    private function aiPayload(Product $product, int|string $categoryId, array $overrides = []): array
    {
        return array_replace([
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category_id' => $categoryId,
            'price' => (float) $product->price,
            'stock_quantity' => (int) $product->stock_quantity,
            'is_active' => true,
            'is_featured' => false,
            'show_on_pc_website' => true,
            'short_description' => 'Laptop doanh nhân bền bỉ.',
            'description' => '<h2>Tổng quan</h2><p>Dell Latitude 5480 bền bỉ.</p>',
            'meta_title' => 'Dell Latitude 5480',
            'meta_description' => 'Laptop Dell Latitude 5480.',
            'specifications_text' => "CPU: Intel Core i5-6440HQ\nRAM: 8GB DDR4",
            'gallery' => [],
            'variants' => [],
            'compatibility_specs' => [],
            'relations' => [],
            // Exactly what applyProductAiResult() puts into the form.
            'highlights' => [
                ['title' => 'Core i5 4 nhân', 'icon' => '', 'is_active' => true],
                ['title' => 'SSD 256GB', 'icon' => '', 'is_active' => true],
            ],
            'detail_blocks' => [
                ['type' => 'benchmark_cards', 'title' => 'Cấu hình nổi bật', 'payload' => ['cards' => [['title' => 'CPU', 'value' => 'i5-6440HQ', 'description' => '4 nhân']]], 'is_active' => true],
                ['type' => 'use_case_cards', 'title' => 'Phù hợp cho', 'payload' => ['cards' => [['icon' => '💼', 'title' => 'Văn phòng']]], 'is_active' => true],
            ],
        ], $overrides);
    }

    private function category(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true, 'show_on_pc_website' => true,
        ], $overrides));
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Dell Latitude 5480 i5-6440HQ/Ram 8GB/SSD 256GB/14" FHD',
            'slug' => 'dell-latitude-5480',
            'sku' => 'DL5480',
            'price' => 5_000_000,
            'stock_quantity' => 2,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
