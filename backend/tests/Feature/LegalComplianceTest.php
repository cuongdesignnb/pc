<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\PolicyPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_policy_pages_are_installed_without_replacing_the_shops_own(): void
    {
        Page::create(['title' => 'CHÍNH SÁCH GIÁ', 'slug' => 'chinh-sach-gia', 'body' => '<p>Bản của shop</p>', 'is_active' => true]);

        PolicyPages::install();
        PolicyPages::install();

        $this->assertSame(8, Page::count());
        $this->assertSame('<p>Bản của shop</p>', Page::where('slug', 'chinh-sach-gia')->value('body'));
        $footer = collect($this->getJson('/api/v1/menus/footer')->assertOk()->json('items.0.children'))->pluck('url');
        $this->assertTrue($footer->contains('/quy-trinh-giai-quyet-khieu-nai'));
        $this->assertTrue($footer->contains('/quyen-va-nghia-vu-cac-ben'));

        $this->getJson('/api/v1/settings')
            ->assertJsonPath('legal_business_name', 'HỘ KINH DOANH SHOP CÔNG NGHỆ LAPTOPPLUS')
            ->assertJsonPath('legal_registration_number', '');
    }

    public function test_policy_page_tokens_are_filled_from_settings(): void
    {
        PolicyPages::install();
        Setting::set('contact_hotline', '0899302222');
        Setting::set('legal_email', 'shop@<b>example</b>.vn');
        Setting::clearCache();

        $body = $this->getJson('/api/v1/pages/quy-trinh-giai-quyet-khieu-nai')
            ->assertOk()
            ->json('page.body');

        $this->assertStringContainsString('HỘ KINH DOANH SHOP CÔNG NGHỆ LAPTOPPLUS', $body);
        $this->assertStringContainsString('0899302222', $body);
        $this->assertStringNotContainsString('{{', $body);
        $this->assertStringNotContainsString('<b>example', $body);
    }

    public function test_product_compliance_inherits_from_category_and_defaults(): void
    {
        $parent = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu-'.Str::random(4), 'is_active' => true,
            'is_refurbished' => true, 'warning_info' => 'Cảnh báo danh mục']);
        $child = Category::create(['name' => 'Dell cũ', 'slug' => 'dell-cu-'.Str::random(4), 'is_active' => true, 'parent_id' => $parent->id]);
        $product = Product::create([
            'name' => 'Dell Latitude', 'slug' => 'dell-'.Str::lower(Str::random(6)), 'sku' => 'D-'.Str::random(6),
            'category_id' => $child->id, 'price' => 5_000_000, 'stock_quantity' => 1, 'is_active' => true,
            'show_on_pc_website' => true, 'inventory_source' => 'local', 'manufacture_year' => 2021,
        ]);

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.compliance.manufacture_year', 2021)
            ->assertJsonPath('product.compliance.is_refurbished', true)
            ->assertJsonPath('product.compliance.refurbished_label', 'Sản phẩm tân trang làm mới')
            ->assertJsonPath('product.compliance.warning_info', 'Cảnh báo danh mục');
        $this->assertStringContainsString('Hướng dẫn bảo quản', $this->getJson('/api/v1/products/'.$product->slug)->json('product.compliance.usage_instructions'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'slug' => $product->slug, 'sku' => $product->sku, 'category_id' => $child->id,
            'price' => 5_000_000, 'stock_quantity' => 1, 'manufacture_year' => 2020, 'is_refurbished' => false,
            'usage_instructions' => 'Hướng dẫn riêng',
        ])->assertSessionHasNoErrors();

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertJsonPath('product.compliance.manufacture_year', 2020)
            ->assertJsonPath('product.compliance.is_refurbished', false)
            ->assertJsonPath('product.compliance.refurbished_label', null)
            ->assertJsonPath('product.compliance.usage_instructions', 'Hướng dẫn riêng');
    }
}
