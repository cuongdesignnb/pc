<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use App\Services\Seo\ProductStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaptopPlusBatchSixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('seo.site_origin', 'https://laptopplus.vn');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_api_writes_specifications_and_reports_ready_quantity(): void
    {
        $category = Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $product = Product::create([
            'name' => 'Dell 7420', 'slug' => 'dell-7420', 'sku' => 'D7420', 'category_id' => $category->id,
            'price' => 9_000_000, 'stock_quantity' => 3, 'is_active' => true, 'show_on_pc_website' => true,
            'inventory_source' => 'kiot', 'provider' => 'kiot', 'kiot_physical_quantity' => 5,
            'kiot_reserved_quantity' => 1, 'kiot_available_quantity' => 4, 'kiot_ready_quantity' => 3,
        ]);
        $token = app(AdminApiTokens::class)->issue($this->admin, 'AI', 'content')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", [
            'specifications' => [
                ['label' => 'CPU', 'value' => 'Intel Core i5-1145G7'],
                ['label' => 'RAM: dung lượng', 'value' => "16GB\nDDR4"],
            ],
        ])->assertOk()
            ->assertJsonPath('data.specifications.0', ['label' => 'CPU', 'value' => 'Intel Core i5-1145G7'])
            ->assertJsonPath('data.specifications.1', ['label' => 'RAM dung lượng', 'value' => '16GB DDR4'])
            ->assertJsonPath('data.ready_quantity', 3)
            ->assertJsonPath('data.kiot.stock_quantity', 5);

        $this->assertSame('CPU', $product->fresh()->parsed_specifications[0]['label']);

        // Price and stock still cannot be written.
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['stock_quantity' => 9])->assertUnprocessable();
    }

    public function test_admin_api_lists_post_categories_and_schedules_posts(): void
    {
        $news = PostCategory::create(['name' => 'Tin công nghệ', 'slug' => 'tin-cong-nghe', 'sort_order' => 1]);
        $token = app(AdminApiTokens::class)->issue($this->admin, 'AI', 'content')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/admin-api/post-categories')->assertOk()
            ->assertJsonPath('data.0.slug', 'tin-cong-nghe');

        $id = $this->withToken($token)->postJson('/api/v1/admin-api/posts', [
            'title' => 'Bài hẹn giờ', 'body' => '<p>Nội dung</p>', 'status' => 'published',
            'post_category_id' => $news->id, 'published_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.is_scheduled', true)->json('data.id');

        $this->assertFalse(Post::published()->whereKey($id)->exists());
        $this->travel(2)->days();
        $this->assertTrue(Post::published()->whereKey($id)->exists());
    }

    public function test_redirects_are_managed_in_admin_and_served_to_the_storefront(): void
    {
        $this->getJson('/api/v1/redirects')->assertOk()
            ->assertJsonFragment(['from' => '/gioi-thieu', 'to' => '/gioi-thieu-laptop-plus', 'status' => 301]);

        $this->actingAs($this->admin)->post('/admin/redirects', [
            'source_path' => 'https://laptopplus.vn/Laptop-5-8-Trieu/?a=1', 'target' => '/laptop-tu-5-den-8-trieu', 'status_code' => 301, 'is_active' => true,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('redirects', ['source_path' => '/laptop-5-8-trieu']);

        $this->actingAs($this->admin)->post('/admin/redirects', ['source_path' => '/', 'target' => '/a', 'status_code' => 301])
            ->assertSessionHasErrors('source_path');
        $this->actingAs($this->admin)->post('/admin/redirects', ['source_path' => '/x', 'target' => 'javascript:alert(1)', 'status_code' => 301])
            ->assertSessionHasErrors('target');
        $this->actingAs($this->admin)->post('/admin/redirects', ['source_path' => '/y', 'target' => '/gioi-thieu', 'status_code' => 301])
            ->assertSessionHasErrors('target');

        $rule = Redirect::where('source_path', '/laptop-5-8-trieu')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/redirects/{$rule->id}", [
            'source_path' => '/laptop-5-8-trieu', 'target' => '/laptop-tu-5-den-8-trieu', 'status_code' => 302, 'is_active' => false,
        ])->assertSessionHas('success');
        $this->assertNotContains('/laptop-5-8-trieu', array_column($this->getJson('/api/v1/redirects')->json('data'), 'from'));

        $this->actingAs($this->admin)->delete("/admin/redirects/{$rule->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('redirects', ['id' => $rule->id]);
    }

    public function test_page_schema_is_validated_and_returned(): void
    {
        $page = Page::create(['title' => 'Thu mua laptop cũ', 'slug' => 'thu-mua-laptop-cu', 'body' => '<p>x</p>', 'is_active' => true]);
        $payload = ['title' => $page->title, 'slug' => $page->slug, 'body' => '<p>x</p>', 'is_active' => true];

        $this->actingAs($this->admin)->put("/admin/pages/{$page->id}", $payload + ['schema_json' => '{"name": "thiếu type"}'])
            ->assertSessionHasErrors('schema_json');
        $this->actingAs($this->admin)->put("/admin/pages/{$page->id}", $payload + ['schema_json' => json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'LocalBusiness', 'name' => 'Laptop Plus', 'telephone' => '0899302222'],
                ['@type' => 'FAQPage', 'mainEntity' => [['@type' => 'Question', 'name' => 'Thu mua tận nơi?']]],
            ],
        ])])->assertSessionHasNoErrors();

        $this->getJson('/api/v1/pages/thu-mua-laptop-cu')->assertOk()
            ->assertJsonPath('page.schema.0.@type', 'LocalBusiness')
            ->assertJsonPath('page.schema.1.@type', 'FAQPage')
            ->assertJsonMissingPath('page.schema.0.@context');
    }

    public function test_gtin_needs_a_valid_check_digit_and_mpn_comes_from_specifications(): void
    {
        $structured = app(ProductStructuredData::class);
        $this->assertSame('4006381333931', $structured->gtin('4006381333931'));
        $this->assertNull($structured->gtin('4006381333932'));
        $this->assertNull($structured->gtin('SN-ABC123'));

        $product = new Product(['specifications_text' => "CPU: i5\nPart number: 5CG1234XYZ"]);
        $this->assertSame('5CG1234XYZ', $structured->mpn($product));
        $this->assertNull($structured->mpn(new Product(['specifications_text' => 'CPU: i5'])));
    }
}
