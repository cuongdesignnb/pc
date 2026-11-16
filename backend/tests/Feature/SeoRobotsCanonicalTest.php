<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoRobotsCanonicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.test');
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop', 'is_active' => true, 'show_on_pc_website' => true]);

        return Product::create($attributes + [
            'name' => 'Dell Latitude 7420', 'slug' => 'dell-latitude-7420', 'sku' => 'DL7420',
            'category_id' => $category->id, 'price' => 9_000_000, 'stock_quantity' => 3,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    private function makePost(User $user, array $attributes = []): Post
    {
        return Post::create($attributes + [
            'user_id' => $user->id, 'title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);
    }

    public function test_defaults_are_index_follow_and_the_own_url(): void
    {
        $product = $this->product();

        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.seo.robots', 'index,follow')
            ->assertJsonPath('product.seo.canonical_path', '/laptop/dell-latitude-7420')
            ->assertJsonPath('product.seo.canonical_url', 'https://laptopplus.test/laptop/dell-latitude-7420');
    }

    public function test_admin_sets_robots_and_canonical_on_a_post_and_the_api_uses_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = $this->makePost($admin);

        $payload = ['title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>', 'status' => 'published'];
        $this->actingAs($admin)->put("/admin/posts/{$post->id}", $payload + ['meta_robots' => 'noindex'])
            ->assertSessionHasErrors('meta_robots');
        $this->actingAs($admin)->put("/admin/posts/{$post->id}", $payload + ['canonical_url' => 'http://khac.vn/bai'])
            ->assertSessionHasErrors('canonical_url');
        $this->actingAs($admin)->put("/admin/posts/{$post->id}", $payload + [
            'meta_robots' => 'noindex,follow', 'canonical_url' => ' /tin-tuc/bai-goc ', 'published_at' => '2026-11-01T10:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('/tin-tuc/bai-goc', $post->fresh()->canonical_url);
        $this->getJson('/api/v1/blog/bai-viet')->assertOk()
            ->assertJsonPath('post.seo.robots', 'noindex,follow')
            // canonical_path stays the page's own path (storefront routing).
            ->assertJsonPath('post.seo.canonical_path', '/tin-tuc/bai-viet')
            ->assertJsonPath('post.seo.canonical_url', 'https://laptopplus.test/tin-tuc/bai-goc');
    }

    public function test_page_and_product_overrides_reach_the_public_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = Page::create(['title' => 'Giới thiệu', 'slug' => 'chinh-sach-doi-tra', 'body' => '<p>Về chúng tôi</p>', 'is_active' => true]);

        $this->actingAs($admin)->put("/admin/pages/{$page->id}", [
            'title' => 'Giới thiệu', 'slug' => 'chinh-sach-doi-tra', 'body' => '<p>Về chúng tôi</p>', 'is_active' => true,
            'meta_robots' => 'noindex,nofollow', 'canonical_url' => 'https://laptopplus.vn/chinh-sach-doi-tra',
        ])->assertSessionHasNoErrors();

        $this->getJson('/api/v1/pages/chinh-sach-doi-tra')->assertOk()
            ->assertJsonPath('page.robots', 'noindex,nofollow')
            ->assertJsonPath('page.canonical_path', '/chinh-sach-doi-tra')
            ->assertJsonPath('page.canonical_url', 'https://laptopplus.vn/chinh-sach-doi-tra');

        $product = $this->product(['meta_robots' => 'index,nofollow', 'canonical_url' => '/laptop/ban-goc']);
        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.seo.robots', 'index,nofollow')
            ->assertJsonPath('product.seo.canonical_path', '/laptop/dell-latitude-7420')
            ->assertJsonPath('product.seo.canonical_url', 'https://laptopplus.test/laptop/ban-goc');
    }

    public function test_admin_api_writes_product_and_post_seo_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product();
        $post = $this->makePost($admin);
        $token = app(AdminApiTokens::class)->issue($admin, 'n8n', 'content')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['meta_robots' => 'nofollow'])
            ->assertUnprocessable()->assertJsonValidationErrors('meta_robots');
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", [
            'meta_robots' => 'noindex,follow', 'canonical_url' => 'https://laptopplus.test/laptop/khac',
        ])->assertOk()
            ->assertJsonPath('data.meta_robots', 'noindex,follow')
            ->assertJsonPath('data.canonical_url', 'https://laptopplus.test/laptop/khac');

        $this->withToken($token)->patchJson("/api/v1/admin-api/posts/{$post->id}", ['meta_robots' => 'noindex,nofollow', 'canonical_url' => null])
            ->assertOk()->assertJsonPath('data.meta_robots', 'noindex,nofollow');
    }

    public function test_sitemaps_skip_noindex_products_posts_and_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->product();
        $this->product(['name' => 'Ẩn', 'slug' => 'san-pham-an', 'sku' => 'AN1', 'meta_robots' => 'noindex,follow']);
        $this->makePost($admin);
        $this->makePost($admin, ['title' => 'Ẩn', 'slug' => 'bai-an', 'meta_robots' => 'noindex,nofollow']);
        Page::create(['title' => 'Hiện', 'slug' => 'trang-hien', 'body' => '<p>x</p>', 'is_active' => true]);
        Page::create(['title' => 'Ẩn', 'slug' => 'trang-an', 'body' => '<p>x</p>', 'is_active' => true, 'meta_robots' => 'noindex,follow']);

        $this->get('/sitemaps/products-1.xml')->assertOk()
            ->assertSee('/laptop/dell-latitude-7420', false)->assertDontSee('san-pham-an', false);
        $this->get('/sitemaps/posts-1.xml')->assertOk()
            ->assertSee('/tin-tuc/bai-viet', false)->assertDontSee('bai-an', false);
        $this->get('/sitemaps/static.xml')->assertOk()
            ->assertSee('/trang-hien', false)->assertDontSee('trang-an', false);
    }
}
