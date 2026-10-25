<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\Page;
use App\Models\SlugHistory;
use App\Models\User;
use App\Services\Seo\SlugRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['seo.site_origin' => 'https://storefront.example.test']);
    }

    public function test_published_page_is_readable_without_auth_and_has_a_storefront_canonical(): void
    {
        $page = $this->page('chinh-sach-fixture');

        $this->getJson('/api/v1/pages/'.$page->slug)->assertOk()
            ->assertJsonPath('page.id', $page->id)
            ->assertJsonPath('page.title', $page->title)
            ->assertJsonPath('page.body', '<p>Nội dung fixture.</p>')
            ->assertJsonPath('page.canonical_path', '/chinh-sach-fixture')
            ->assertJsonPath('page.meta_title', 'Tiêu đề SEO fixture')
            ->assertJsonPath('page.meta_description', 'Mô tả SEO fixture')
            ->assertJsonMissingPath('page.slug_source')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $this->assertSame('<p>Nội dung fixture.</p>', $page->fresh()->body);
    }

    public function test_hidden_page_and_missing_page_do_not_expose_content_even_to_a_logged_in_admin(): void
    {
        $hidden = $this->page('trang-an-fixture', ['is_active' => false]);
        $this->getJson('/api/v1/pages/'.$hidden->slug)->assertNotFound()->assertJsonMissingPath('page');
        $this->getJson('/api/v1/pages/trang-khong-co')->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/pages/'.$hidden->slug)->assertNotFound()->assertDontSee('Nội dung fixture.');
        $this->assertFalse($hidden->fresh()->is_active);
    }

    public function test_unpublishing_and_editing_a_page_take_effect_without_retaining_a_cached_copy(): void
    {
        $page = $this->page('trang-cap-nhat');
        $this->getJson('/api/v1/pages/'.$page->slug)->assertOk();
        $page->update(['body' => '<p>Nội dung mới.</p>']);
        $this->getJson('/api/v1/pages/'.$page->slug)->assertOk()->assertJsonPath('page.body', '<p>Nội dung mới.</p>');
        $page->update(['is_active' => false]);
        $this->getJson('/api/v1/pages/'.$page->slug)->assertNotFound();
    }

    public function test_html_is_sanitized_while_preserving_tables_and_safe_contact_links(): void
    {
        $page = $this->page('html-fixture', ['body' => '<h1>Tiêu đề trong nội dung</h1><script>alert(1)</script>'
            .'<p onclick="alert(1)"><a href="javascript:alert(1)">Unsafe</a>'
            .'<a href="tel:0123456789">Điện thoại</a></p>'
            .'<table><tbody><tr><td>Điều kiện</td></tr></tbody></table>'
            .'<a href="https://example.test/contact" target="_blank">Liên hệ</a>']);

        $response = $this->getJson('/api/v1/pages/'.$page->slug)->assertOk();
        $body = $response->json('page.body');
        $this->assertStringContainsString('<table>', $body);
        $this->assertStringContainsString('href="tel:0123456789"', $body);
        $this->assertStringContainsString('rel="noopener noreferrer"', $body);
        foreach (['<script', 'onclick', 'javascript:', '<h1'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $body);
        }
        $this->assertSame($page->body, $page->fresh()->body);
    }

    public function test_page_alias_resolves_to_the_current_record_and_canonical_path(): void
    {
        $page = $this->page('nouvelle-politique');
        app(SlugRedirectService::class)->recordPageChange($page, 'ancienne-politique');
        $this->getJson('/api/v1/pages/ancienne-politique')->assertOk()
            ->assertJsonPath('page.id', $page->id)
            ->assertJsonPath('page.slug', 'nouvelle-politique')
            ->assertJsonPath('page.canonical_path', '/nouvelle-politique');

        $page->update(['is_active' => false]);
        $this->getJson('/api/v1/pages/ancienne-politique')->assertNotFound();
        $page->delete();
        $this->getJson('/api/v1/pages/ancienne-politique')->assertNotFound();
    }

    public function test_aliases_from_other_site_scopes_are_not_used(): void
    {
        $page = $this->page('politique-actuelle');
        app(SlugRedirectService::class)->recordPageChange($page, 'ancien-slug');
        SlugHistory::query()->where('legacy_slug', 'ancien-slug')->update(['site_key' => 'other-site']);
        $this->getJson('/api/v1/pages/ancien-slug')->assertNotFound();
    }

    public function test_a_page_cannot_take_over_a_category_or_reserved_system_route(): void
    {
        $category = Category::create(['name' => 'Fixture category', 'slug' => 'catalogue-fixture', 'is_active' => true]);
        $this->page($category->slug);
        $this->page('admin');
        $this->getJson('/api/v1/pages/'.$category->slug)->assertNotFound();
        $this->getJson('/api/v1/pages/admin')->assertNotFound();
        $this->getJson('/api/v1/categories/'.$category->slug)->assertOk()
            ->assertJsonPath('category.id', $category->id);
    }

    public function test_a_page_does_not_take_over_a_historical_category_url(): void
    {
        $category = Category::create(['name' => 'Fixture category', 'slug' => 'catalogue-actuel', 'is_active' => true]);
        app(SlugRedirectService::class)->recordCategoryChange($category, 'ancien-catalogue');
        $this->page('ancien-catalogue');
        $this->getJson('/api/v1/pages/ancien-catalogue')->assertNotFound();
        $this->get('/sitemaps/static.xml')->assertOk()->assertDontSee('/ancien-catalogue', false);
    }

    public function test_sitemap_contains_only_published_valid_page_urls(): void
    {
        $this->page('politique-publiee');
        $this->page('politique-cachee', ['is_active' => false]);
        $this->page('admin');
        $response = $this->get('/sitemaps/static.xml')->assertOk()
            ->assertSee('https://storefront.example.test/politique-publiee', false)
            ->assertDontSee('politique-cachee', false)
            ->assertDontSee('https://storefront.example.test/admin', false);
        $this->assertNotFalse(simplexml_load_string($response->getContent(), \SimpleXMLElement::class, LIBXML_NONET));
    }

    public function test_admin_list_exposes_the_storefront_link_only_for_published_pages(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        $hidden = $this->page('trang-an', ['is_active' => false]);
        $published = $this->page('trang-hien');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $version = app(HandleInertiaRequests::class)->version(request());
        $headers = ['X-Inertia' => 'true'];
        if ($version !== null) {
            $headers['X-Inertia-Version'] = $version;
        }
        $pages = $this->get('/admin/pages', $headers)->assertOk()->json('props.pages');
        $byId = collect($pages)->keyBy('id');
        $this->assertSame('https://storefront.example.test/trang-hien', $byId[$published->id]['public_url']);
        $this->assertNull($byId[$hidden->id]['public_url']);
        $this->assertFalse($hidden->fresh()->is_active);
    }

    private function page(string $slug, array $overrides = []): Page
    {
        return Page::create(array_replace([
            'title' => 'Trang fixture', 'slug' => $slug,
            'body' => '<p>Nội dung fixture.</p>', 'is_active' => true,
            'meta_title' => 'Tiêu đề SEO fixture', 'meta_description' => 'Mô tả SEO fixture',
        ], $overrides));
    }
}
