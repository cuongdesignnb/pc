<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomepageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_reference_sections_and_safe_product_cards(): void
    {
        $usedLaptops = $this->category('laptop-cu', 'Laptop cũ');
        $product = $this->product($usedLaptops, ['sold_count' => 120, 'is_featured' => true]);

        Banner::create([
            'title' => 'Hero test',
            'image' => 'https://example.test/hero.webp',
            'link' => '/laptop-cu',
            'position' => 'hero',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Sidebar test',
            'image' => 'https://example.test/sidebar.webp',
            'link' => '/laptop-cu',
            'position' => 'sidebar',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonStructure([
                'hero_banners',
                'sidebar_banners',
                'category_sidebar',
                'category_sections',
                'flash_sale' => ['enabled', 'ends_at', 'products'],
                'product_tabs',
                'store_activities',
                'posts',
                'testimonials',
            ])
            ->assertJsonMissingPath('best_sellers')
            ->assertJsonMissingPath('pc_builder_banner')
            ->assertJsonMissingPath('featured_categories')
            ->assertJsonPath('hero_banners.0.title', 'Hero test')
            ->assertJsonPath('sidebar_banners.0.title', 'Sidebar test')
            // The default tabs point at laptop-cu / laptop-moi / linh-kien;
            // only the one whose category exists and has products is shown.
            ->assertJsonCount(1, 'product_tabs')
            ->assertJsonPath('product_tabs.0.label', 'LAPTOP CŨ')
            ->assertJsonPath('product_tabs.0.category.slug', 'laptop-cu')
            ->assertJsonPath('product_tabs.0.products.0.id', $product->id)
            ->assertJsonPath('product_tabs.0.products.0.sold_count', 120)
            ->assertJsonMissingPath('product_tabs.0.products.0.cost_price');
    }

    public function test_homepage_returns_selected_category_sections_with_visible_products(): void
    {
        $laptop = $this->category('laptop', 'Laptop');
        $gaming = $this->category('pc-gaming', 'PC Gaming');
        $laptopProduct = $this->product($laptop, ['sold_count' => 60, 'is_featured' => true]);
        $gamingProduct = $this->product($gaming, ['sold_count' => 90, 'is_featured' => true]);
        $gamingOtherProduct = $this->product($gaming, ['sold_count' => 40]);
        $this->product($gaming, ['sold_count' => 100, 'show_on_pc_website' => false]);

        Setting::set('homepage_featured_category_slugs', [$laptop->slug, $gaming->slug]);
        Setting::set('homepage_products_per_section', 2);

        $response = $this->getJson('/api/v1/homepage')->assertOk();
        $sections = $response->json('category_sections');

        $this->assertSame(
            [$laptop->slug, $gaming->slug],
            array_map(fn (array $section): string => $section['category']['slug'], $sections),
        );
        $this->assertSame($laptopProduct->id, $sections[0]['products'][0]['id']);
        $this->assertSame($gamingProduct->id, $sections[1]['products'][0]['id']);
        $this->assertSame(1, $sections[0]['product_count']);
        $this->assertSame(2, $sections[1]['product_count']);
        $this->assertCount(2, $sections[1]['products']);
        $this->assertSame($gamingOtherProduct->id, $sections[1]['products'][1]['id']);
        $this->assertSame([], $sections[0]['children']);
    }

    public function test_category_sections_list_child_categories_and_need_a_configured_slug(): void
    {
        $components = $this->category('linh-kien', 'Linh kiện');
        $ram = $this->category('ram-laptop', 'RAM laptop', $components->id);
        $this->category('an-di', 'Ẩn đi', $components->id)->update(['show_on_pc_website' => false]);
        $this->product($ram, ['sold_count' => 5]);

        Setting::set('homepage_featured_category_slugs', []);
        $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(0, 'category_sections');

        Setting::set('homepage_featured_category_slugs', [$components->slug]);
        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonPath('category_sections.0.category.slug', 'linh-kien')
            ->assertJsonCount(1, 'category_sections.0.children')
            ->assertJsonPath('category_sections.0.children.0.slug', 'ram-laptop');
    }

    public function test_homepage_flash_sale_only_contains_visible_discounted_products(): void
    {
        $category = $this->category('pc-gaming', 'PC Gaming');
        $visible = $this->product($category, [
            'price' => 20000000,
            'sale_price' => 15000000,
            'sold_count' => 50,
        ]);
        $hidden = $this->product($category, [
            'price' => 18000000,
            'sale_price' => 12000000,
            'show_on_pc_website' => false,
        ]);

        Setting::updateOrCreate(['key' => 'homepage_flash_sale_enabled'], [
            'value' => '1',
            'group' => 'homepage',
            'type' => 'boolean',
            'label' => 'Flash Sale',
            'is_public' => true,
        ]);
        Setting::updateOrCreate(['key' => 'homepage_flash_sale_ends_at'], [
            'value' => '2030-01-01T23:59:59+07:00',
            'group' => 'homepage',
            'type' => 'text',
            'label' => 'Flash Sale ends at',
            'is_public' => true,
        ]);
        Setting::clearCache();

        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonPath('flash_sale.enabled', true)
            ->assertJsonPath('flash_sale.products.0.id', $visible->id)
            ->assertJsonMissing(['id' => $hidden->id]);
    }

    public function test_newsletter_subscription_is_normalized_and_idempotent(): void
    {
        $this->postJson('/api/v1/newsletter/subscribe', ['email' => ' Customer@Example.COM '])
            ->assertOk()
            ->assertJsonPath('message', 'Đăng ký nhận tin thành công.');

        $this->postJson('/api/v1/newsletter/subscribe', ['email' => 'customer@example.com'])
            ->assertOk();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'customer@example.com',
            'is_active' => true,
        ]);
        $this->assertSame('customer@example.com', NewsletterSubscriber::query()->value('email'));
    }

    public function test_product_tabs_follow_settings_and_never_expose_internal_fields(): void
    {
        $parent = $this->category('laptop-moi', 'Laptop mới');
        $child = $this->category('laptop-moi-dell', 'Dell', $parent->id);
        $low = $this->product($child, ['sold_count' => 1, 'is_featured' => true]);
        $high = $this->product($child, ['sold_count' => 100, 'cost_price' => 123456]);
        $this->product($child, ['sold_count' => 1000, 'show_on_pc_website' => false]);
        $this->product($child, ['sold_count' => 2000, 'is_active' => false]);
        $hidden = $this->category('an', 'Ẩn');
        $hidden->update(['show_on_pc_website' => false]);
        $this->product($hidden, ['sold_count' => 9]);

        Setting::set('homepage_product_tabs', [
            ['label' => '', 'category_slug' => 'laptop-moi'],
            ['label' => 'Ẩn', 'category_slug' => 'an'],
            ['label' => 'Không có', 'category_slug' => 'khong-ton-tai'],
        ]);

        $response = $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(1, 'product_tabs');
        $this->assertSame('Laptop mới', $response->json('product_tabs.0.label'));
        $cards = $response->json('product_tabs.0.products');
        $this->assertSame([$high->id, $low->id], array_column($cards, 'id'));
        foreach ($cards as $card) {
            foreach (array_keys($card) as $key) {
                $this->assertFalse(str_starts_with($key, 'kiot_'), $key);
            }
            $this->assertArrayNotHasKey('cost_price', $card);
            $this->assertArrayNotHasKey('inventory_source', $card);
        }

        Setting::set('homepage_product_tabs', []);
        $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(0, 'product_tabs');
    }

    public function test_category_sidebar_follows_admin_order_and_menus_fall_back_to_categories(): void
    {
        $accessories = $this->category('phu-kien', 'Phụ kiện');
        $accessories->update(['sort_order' => 2]);
        $usedLaptops = $this->category('laptop-cu', 'Laptop cũ');
        $usedLaptops->update(['sort_order' => 1]);

        $this->category('ram-laptop', 'RAM laptop', $accessories->id)->update(['sort_order' => 0]);

        // Only top-level categories by default.
        $response = $this->getJson('/api/v1/homepage')->assertOk();
        $this->assertSame(['laptop-cu', 'phu-kien'], array_column($response->json('category_sidebar'), 'slug'));

        // Categories picked in Cài đặt, in the picked order.
        Setting::set('homepage_sidebar_category_slugs', ['ram-laptop', 'laptop-cu', 'khong-ton-tai']);
        $response = $this->getJson('/api/v1/homepage')->assertOk();
        $this->assertSame(['ram-laptop', 'laptop-cu'], array_column($response->json('category_sidebar'), 'slug'));
        Setting::set('homepage_sidebar_category_slugs', []);

        foreach (['header', 'sidebar'] as $location) {
            $this->getJson('/api/v1/menus/'.$location)
                ->assertOk()
                ->assertJsonPath('items.0.category.slug', 'laptop-cu')
                ->assertJsonPath('items.1.category.slug', 'phu-kien');
        }
        $this->getJson('/api/v1/menus/mobile')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_product_listing_can_show_only_discounted_products(): void
    {
        $category = $this->category('laptop-cu', 'Laptop cũ');
        $this->product($category, ['slug' => 'giam-it', 'price' => 10000000, 'sale_price' => 9500000]);
        $this->product($category, ['slug' => 'giam-nhieu', 'price' => 10000000, 'sale_price' => 7000000]);
        $this->product($category, ['slug' => 'khong-giam', 'price' => 10000000]);
        $this->product($category, ['slug' => 'gia-sai', 'price' => 10000000, 'sale_price' => 12000000]);

        $response = $this->getJson('/api/v1/products?on_sale=1')->assertOk();
        $this->assertSame(['giam-nhieu', 'giam-it'], array_column($response->json('data'), 'slug'));
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_posts_respect_the_homepage_setting(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create(['name' => 'Tin', 'slug' => 'tin']);
        Post::create([
            'user_id' => $author->id, 'post_category_id' => $category->id, 'title' => 'Tin nổi bật', 'slug' => 'tin-noi-bat',
            'body' => '<p>Nội dung</p>', 'status' => 'published', 'is_featured' => true, 'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(1, 'posts');
        Setting::set('homepage_show_posts', '0');
        $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(0, 'posts');
    }

    public function test_only_current_active_banners_are_returned(): void
    {
        foreach ([
            'Current' => [],
            'Inactive' => ['is_active' => false],
            'Future' => ['starts_at' => now()->addDay()],
            'Expired' => ['ends_at' => now()->subDay()],
        ] as $title => $overrides) {
            Banner::create(array_merge([
                'title' => $title,
                'image' => 'https://example.test/banner.webp',
                'position' => 'hero',
                'is_active' => true,
            ], $overrides));
        }
        $this->getJson('/api/v1/homepage')->assertOk()
            ->assertJsonCount(1, 'hero_banners')
            ->assertJsonPath('hero_banners.0.title', 'Current');
    }

    public function test_flash_sale_excludes_invalid_prices_and_respects_disabled_setting(): void
    {
        $category = $this->category('pc-gaming', 'PC Gaming');
        foreach ([null, 0, 100, 101] as $sale) {
            $this->product($category, ['price' => 100, 'sale_price' => $sale]);
        }
        $valid = $this->product($category, ['price' => 100, 'sale_price' => 50]);
        $this->getJson('/api/v1/homepage')->assertOk()
            ->assertJsonCount(1, 'flash_sale.products')
            ->assertJsonPath('flash_sale.products.0.id', $valid->id);
        Setting::set('homepage_flash_sale_enabled', '0');
        $this->getJson('/api/v1/homepage')->assertOk()
            ->assertJsonPath('flash_sale.enabled', false)
            ->assertJsonCount(0, 'flash_sale.products');
    }

    public function test_testimonials_exclude_unapproved_reviews_and_private_customer_fields(): void
    {
        $category = $this->category('pc-gaming', 'PC Gaming');
        $product = $this->product($category);
        $user = User::factory()->create(['email' => 'private@example.test']);
        Review::create([
            'user_id' => $user->id, 'product_id' => $product->id,
            'guest_email' => 'guest-private@example.test',
            'rating' => 4, 'body' => 'Approved review', 'is_approved' => true,
        ]);
        Review::create([
            'product_id' => $product->id, 'guest_name' => 'Hidden guest',
            'rating' => 5, 'body' => 'Pending review', 'is_approved' => false,
        ]);
        $response = $this->getJson('/api/v1/homepage')->assertOk()
            ->assertJsonCount(1, 'testimonials')
            ->assertJsonPath('testimonials.0.rating', 4)
            ->assertJsonPath('testimonials.0.city', null)
            ->assertJsonPath('testimonials.0.verified_purchase', false);
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'avatar', 'city', 'rating', 'body', 'verified_purchase'],
            array_keys($response->json('testimonials.0'))
        );
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
    }

    public function test_newsletter_rejects_invalid_email(): void
    {
        foreach ([[], ['email' => 'invalid'], ['email' => str_repeat('a', 256).'@example.test']] as $data) {
            $this->postJson('/api/v1/newsletter/subscribe', $data)
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_homepage_settings_migration_preserves_admin_values_on_rollback(): void
    {
        $migration = require database_path('migrations/2026_09_05_000002_add_homepage_reference_settings.php');
        Setting::set('homepage_service_shipping_text', 'Admin delivery terms');
        Setting::set('homepage_flash_sale_ends_at', '2030-02-01T00:00:00Z');
        $migration->up();
        $migration->down();
        $migration->up();
        Setting::clearCache();
        $this->assertSame('Admin delivery terms', Setting::get('homepage_service_shipping_text'));
        $this->assertSame('2030-02-01T00:00:00Z', Setting::get('homepage_flash_sale_ends_at'));
    }

    private function category(string $slug, string $name, ?int $parentId = null): Category
    {
        return Category::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Homepage product '.Str::random(6),
            'slug' => 'homepage-product-'.Str::lower(Str::random(10)),
            'sku' => 'HOMEPAGE-'.Str::upper(Str::random(8)),
            'price' => 10000000,
            'stock_quantity' => 10,
            'is_active' => true,
            'is_featured' => false,
            'inventory_source' => 'local',
            'show_on_pc_website' => true,
        ], $overrides));
    }
}
