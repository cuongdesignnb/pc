<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Seo\SlugRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seo.site_origin' => 'https://storefront.example.test']);
    }

    public function test_missing_menu_automatically_lists_only_published_pages_without_private_content(): void
    {
        $published = $this->page('chinh-sach-gia', 'CHÍNH SÁCH GIÁ');
        $hidden = $this->page('chinh-sach-an', 'Chính sách ẩn', false);
        $before = [$published->fresh()->getAttributes(), $hidden->fresh()->getAttributes()];

        $this->getJson('/api/v1/menus/footer')->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.title', 'CHÍNH SÁCH')
            ->assertJsonCount(1, 'items.0.children')
            ->assertJsonPath('items.0.children.0.title', 'CHÍNH SÁCH GIÁ')
            ->assertJsonPath('items.0.children.0.url', '/chinh-sach-gia')
            ->assertJsonPath('items.0.children.0.target', '_self')
            ->assertJsonMissingPath('items.0.children.0.body')
            ->assertDontSee('Chính sách ẩn')->assertDontSee('Nội dung không được đưa vào menu')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
        $this->assertSame($before, [$published->fresh()->getAttributes(), $hidden->fresh()->getAttributes()]);
        $this->assertDatabaseCount('menus', 0);
        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_header_is_not_populated_with_pages(): void
    {
        $this->page('chinh-sach-gia', 'Chính sách giá');
        Category::create(['name' => 'CPU fixture', 'slug' => 'cpu-fixture', 'is_active' => true]);
        $this->getJson('/api/v1/menus/header')->assertOk()->assertDontSee('chinh-sach-gia')
            ->assertJsonPath('items.0.type', 'category');
    }

    public function test_existing_footer_columns_and_external_links_are_preserved(): void
    {
        $menu = $this->menu();
        $parent = $this->link($menu, 'Hỗ trợ', '#');
        $child = $this->link($menu, 'Liên hệ ngoài', 'https://external.example.test/contact', $parent);
        $this->page('chinh-sach-gia', 'Chính sách giá');

        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.id', $parent->id)
            ->assertJsonPath('items.0.children.0.id', $child->id)
            ->assertJsonPath('items.0.children.0.url', $child->url)
            ->assertJsonPath('items.1.children.0.url', '/chinh-sach-gia');
        $this->assertDatabaseCount('menus', 1);
        $this->assertDatabaseCount('menu_items', 2);
    }

    public function test_new_pages_are_merged_into_an_existing_policy_column_without_duplicates(): void
    {
        $menu = $this->menu();
        $parent = $this->link($menu, 'Chính sách', '#');
        $this->link($menu, 'Nhãn chính sách đã cấu hình', 'https://storefront.example.test/chinh-sach-gia/?ref=menu', $parent);
        $this->page('chinh-sach-gia', 'Chính sách giá');
        $this->page('dieu-khoan', 'Điều khoản');

        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonCount(2, 'items.0.children')
            ->assertJsonPath('items.0.children.0.title', 'Nhãn chính sách đã cấu hình')
            ->assertJsonPath('items.0.children.1.url', '/dieu-khoan');
    }

    public function test_flat_configured_menu_stays_one_links_column_when_policies_are_added(): void
    {
        $menu = $this->menu();
        $link = $this->link($menu, 'Liên hệ', '/lien-he');
        $this->page('chinh-sach-gia', 'Chính sách giá');
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.title', 'Liên kết')
            ->assertJsonPath('items.0.children.0.id', $link->id)
            ->assertJsonPath('items.1.title', 'CHÍNH SÁCH');
    }

    public function test_configured_alias_does_not_duplicate_its_published_page(): void
    {
        $page = $this->page('chinh-sach-moi', 'Chính sách mới');
        app(SlugRedirectService::class)->recordPageChange($page, 'chinh-sach-cu');
        $menu = $this->menu();
        $link = $this->link($menu, 'Chính sách đã đặt tên', '/chinh-sach-cu');
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $link->id)->assertJsonPath('items.0.url', '/chinh-sach-cu');
    }

    public function test_known_unpublished_page_is_removed_even_from_a_configured_menu_without_changing_menu_rows(): void
    {
        $this->page('chinh-sach-an', 'Không xuất bản', false);
        $menu = $this->menu();
        $link = $this->link($menu, 'Không đưa bản nháp ra footer', '/chinh-sach-an');
        $before = $link->fresh()->getAttributes();
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonPath('items', []);
        $this->assertSame($before, $link->fresh()->getAttributes());
    }

    public function test_invalid_reserved_and_category_owned_page_urls_are_not_added(): void
    {
        $category = Category::create(['name' => 'Category', 'slug' => 'catalogue-moi', 'is_active' => true]);
        app(SlugRedirectService::class)->recordCategoryChange($category, 'catalogue-cu');
        foreach (['catalogue-moi', 'catalogue-cu', 'admin', 'bad/slug'] as $slug) {
            $this->page($slug, 'Không phải chính sách public');
        }
        $this->page('trang-khong-ten', '   ');
        $this->page('chinh-sach-hop-le', 'Chính sách hợp lệ');
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(1, 'items.0.children')
            ->assertJsonPath('items.0.children.0.url', '/chinh-sach-hop-le');
    }

    public function test_publish_unpublish_and_title_updates_are_visible_without_a_cached_footer(): void
    {
        $page = $this->page('chinh-sach-gia', 'Tên cũ', false);
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonPath('items', []);
        $page->update(['is_active' => true, 'title' => 'Tên mới']);
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonPath('items.0.children.0.title', 'Tên mới');
        $page->update(['is_active' => false]);
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonPath('items', []);
    }

    public function test_links_from_other_domains_do_not_suppress_a_storefront_policy(): void
    {
        $menu = $this->menu();
        $this->link($menu, 'Chính sách site khác', 'https://other.example.test/chinh-sach-gia');
        $this->page('chinh-sach-gia', 'Chính sách giá');
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.children.0.url', 'https://other.example.test/chinh-sach-gia')
            ->assertJsonPath('items.1.children.0.url', '/chinh-sach-gia');
    }

    public function test_a_discarded_empty_group_does_not_suppress_its_published_page(): void
    {
        $menu = $this->menu();
        $parent = $this->link($menu, 'Nhóm cũ', '/chinh-sach-gia');
        $this->link($menu, 'Trang ẩn', '/trang-an', $parent);
        $this->page('trang-an', 'Trang ẩn', false);
        $this->page('chinh-sach-gia', 'Chính sách giá');
        $this->getJson('/api/v1/menus/footer')->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'CHÍNH SÁCH')
            ->assertJsonPath('items.0.children.0.url', '/chinh-sach-gia');
    }

    private function page(string $slug, string $title, bool $active = true): Page
    {
        return Page::create(['title' => $title, 'slug' => $slug, 'is_active' => $active,
            'body' => '<p>Nội dung không được đưa vào menu</p>']);
    }

    private function menu(): Menu
    {
        return Menu::create(['name' => 'Footer fixture', 'slug' => 'footer-fixture', 'location' => 'footer', 'is_active' => true]);
    }

    private function link(Menu $menu, string $title, string $url, ?MenuItem $parent = null): MenuItem
    {
        return MenuItem::create(['menu_id' => $menu->id, 'parent_id' => $parent?->id,
            'title' => $title, 'url' => $url, 'type' => 'custom', 'is_active' => true]);
    }
}
