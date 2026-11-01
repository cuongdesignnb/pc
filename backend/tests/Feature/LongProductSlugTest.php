<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LongProductSlugTest extends TestCase
{
    use RefreshDatabase;

    private const LONG_NAME = 'Laptop Dell Latitude 7420 Core i7 1185G7 RAM 16GB SSD 512GB màn hình 14 inch FHD cảm ứng '
        .'bàn phím có đèn nền vân tay bảo hành 12 tháng tặng balo chuột không dây và túi chống sốc chính hãng';

    public function test_long_names_are_cut_at_a_word_boundary(): void
    {
        $slugs = app(VietnameseSlugNormalizer::class);
        $slug = $slugs->normalizeShortened(self::LONG_NAME);

        $this->assertLessThanOrEqual(160, strlen($slug));
        $this->assertStringStartsWith('laptop-dell-latitude-7420-core-i7', $slug);
        $this->assertStringEndsNotWith('-', $slug);
        $this->assertSame($slug, $slugs->validateCustom($slug));
        $this->assertSame('abc-def', $slugs->shorten('abc-def-ghi', 9));
        $this->assertSame('abcdefghi', $slugs->shorten('abcdefghijkl', 9));
    }

    public function test_admin_creates_a_product_with_a_too_long_slug_and_gets_a_shortened_one(): void
    {
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);
        $longSlug = Str::slug(self::LONG_NAME);
        $this->assertGreaterThan(160, strlen($longSlug));

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/admin/products', [
            'name' => self::LONG_NAME, 'slug' => $longSlug, 'sku' => 'DELL-7420-LONG', 'category_id' => $category->id,
            'price' => 9_000_000, 'stock_quantity' => 1,
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'DELL-7420-LONG')->firstOrFail();
        $this->assertLessThanOrEqual(160, strlen($product->slug));
        $this->assertStringStartsWith(substr($product->slug, 0, 40), $longSlug);
    }

    public function test_migration_shortens_existing_overlong_slugs(): void
    {
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);
        $longSlug = Str::slug(self::LONG_NAME);
        $product = Product::create([
            'name' => self::LONG_NAME, 'slug' => $longSlug, 'sku' => 'SKU-554', 'category_id' => $category->id,
            'price' => 1, 'stock_quantity' => 1, 'is_active' => true, 'inventory_source' => 'local',
        ]);

        $migration = require database_path('migrations/2026_11_05_000001_shorten_overlong_product_slugs.php');
        $migration->up();

        $slug = $product->fresh()->slug;
        $this->assertLessThanOrEqual(160, strlen($slug));
        $this->assertSame($slug, app(VietnameseSlugNormalizer::class)->validateCustom($slug));
    }
}
