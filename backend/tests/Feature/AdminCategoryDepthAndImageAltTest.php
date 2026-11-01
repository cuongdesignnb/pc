<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCategoryDepthAndImageAltTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_can_be_three_levels_deep_but_not_four(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $used = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $dell = Category::create(['name' => 'Dell', 'slug' => 'laptop-cu-dell', 'is_active' => true, 'parent_id' => $used->id]);

        $this->actingAs($admin)->get('/admin/categories/create')->assertInertia(fn ($page) => $page
            ->where('categories.1.name', 'Laptop cũ → Dell'));

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Latitude', 'slug' => 'dell-latitude', 'parent_id' => $dell->id, 'sort_order' => 0,
        ])->assertSessionHasNoErrors();
        $latitude = Category::where('slug', 'dell-latitude')->firstOrFail();
        $this->assertSame($dell->id, $latitude->parent_id);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Latitude 7000', 'slug' => 'latitude-7000', 'parent_id' => $latitude->id, 'sort_order' => 0,
        ])->assertSessionHasErrors('parent_id');

        // Dell has a sub-category, so it cannot move under another second-level category.
        $hp = Category::create(['name' => 'HP', 'slug' => 'laptop-cu-hp', 'is_active' => true, 'parent_id' => $used->id]);
        $this->actingAs($admin)->put("/admin/categories/{$dell->id}", [
            'name' => 'Dell', 'slug' => 'laptop-cu-dell', 'parent_id' => $hp->id, 'sort_order' => 0,
        ])->assertSessionHasErrors('parent_id');
        $this->actingAs($admin)->put("/admin/categories/{$used->id}", [
            'name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'parent_id' => $latitude->id, 'sort_order' => 0,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_admin_sets_alt_text_per_image_and_kept_images_keep_their_row(): void
    {
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Dell Latitude 7420', 'slug' => 'dell-latitude-7420', 'sku' => 'SKU-'.Str::random(6),
            'category_id' => $category->id, 'price' => 9_000_000, 'stock_quantity' => 1,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
        $front = $product->images()->create(['url' => 'https://cdn.test/front.webp', 'is_primary' => true, 'sort_order' => 0]);
        $product->images()->create(['url' => 'https://cdn.test/old.webp', 'is_primary' => false, 'sort_order' => 1]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'slug' => $product->slug, 'sku' => $product->sku, 'category_id' => $category->id,
            'price' => 9_000_000, 'stock_quantity' => 1,
            'thumbnail' => 'https://cdn.test/front.webp',
            'gallery' => ['https://cdn.test/side.webp'],
            'image_alts' => [
                ['url' => 'https://cdn.test/front.webp', 'alt' => 'Dell Latitude 7420 mặt trước'],
                ['url' => 'https://cdn.test/side.webp', 'alt' => 'Cổng kết nối bên trái'],
            ],
        ])->assertSessionHasNoErrors();

        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertSame(['https://cdn.test/front.webp', 'https://cdn.test/side.webp'], $images->pluck('url')->all());
        $this->assertSame($front->id, $images[0]->id);
        $this->assertTrue($images[0]->is_primary);
        $this->assertSame('Dell Latitude 7420 mặt trước', $images[0]->alt);
        $this->assertSame('Cổng kết nối bên trái', $images[1]->alt);
    }
}
