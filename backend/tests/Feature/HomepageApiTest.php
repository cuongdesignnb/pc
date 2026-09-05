<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
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
        $pcGaming = $this->category('pc-gaming', 'PC Gaming');
        $laptop = $this->category('laptop', 'Laptop');
        $components = $this->category('linh-kien-pc', 'Linh kiện PC');
        $product = $this->product($pcGaming, ['sold_count' => 120, 'is_featured' => true]);

        Banner::create([
            'title' => 'Hero test',
            'image' => 'https://example.test/hero.webp',
            'link' => '/cau-hinh',
            'position' => 'hero',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Sidebar test',
            'image' => 'https://example.test/sidebar.webp',
            'link' => '/categories/pc-gaming',
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
                'featured_categories',
                'flash_sale' => ['enabled', 'ends_at', 'products'],
                'best_sellers' => ['laptop', 'pc_gaming', 'components'],
                'pc_builder_banner',
                'combo_banners',
                'setup_banners',
                'featured_accessories',
                'posts',
                'testimonials',
            ])
            ->assertJsonPath('hero_banners.0.title', 'Hero test')
            ->assertJsonPath('sidebar_banners.0.title', 'Sidebar test')
            ->assertJsonPath('best_sellers.pc_gaming.0.id', $product->id)
            ->assertJsonPath('best_sellers.pc_gaming.0.sold_count', 120)
            ->assertJsonMissingPath('best_sellers.pc_gaming.0.cost_price');

        $this->assertTrue($pcGaming->exists);
        $this->assertTrue($laptop->exists);
        $this->assertTrue($components->exists);
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

    public function test_best_sellers_are_ordered_and_never_expose_internal_fields(): void
    {
        foreach (['laptop' => 'laptop', 'pc_gaming' => 'pc-gaming', 'components' => 'linh-kien-pc'] as $tab => $slug) {
            $parent = $this->category($slug, $slug);
            $child = $this->category($slug.'-child', 'Child', $parent->id);
            $low = $this->product($child, ['sold_count' => 1, 'is_featured' => true]);
            $high = $this->product($child, ['sold_count' => 100, 'cost_price' => 123456]);
            $this->product($child, ['sold_count' => 1000, 'show_on_pc_website' => false]);
            $this->product($child, ['sold_count' => 2000, 'is_active' => false]);
            $response = $this->getJson('/api/v1/homepage')->assertOk();
            $cards = $response->json('best_sellers.'.$tab);
            $this->assertSame([$high->id, $low->id], array_column($cards, 'id'));
            foreach ($cards as $card) {
                foreach (array_keys($card) as $key) {
                    $this->assertFalse(str_starts_with($key, 'kiot_'), $key);
                }
                $this->assertArrayNotHasKey('cost_price', $card);
                $this->assertArrayNotHasKey('inventory_source', $card);
            }
        }
    }

    public function test_homepage_appends_visible_synced_categories_and_fills_missing_category_aliases(): void
    {
        $accessories = $this->category('phu-kien', 'Phụ kiện');
        $actualCategory = $this->category('thiet-bi-mang', 'Thiết bị mạng', null);
        $product = $this->product($actualCategory, ['sold_count' => 77]);

        $response = $this->getJson('/api/v1/homepage')->assertOk();

        $sidebarSlugs = array_column($response->json('category_sidebar'), 'slug');
        $featuredSlugs = array_column($response->json('featured_categories'), 'slug');

        $this->assertContains($accessories->slug, $sidebarSlugs);
        $this->assertContains($actualCategory->slug, $sidebarSlugs);
        $this->assertContains($actualCategory->slug, $featuredSlugs);
        $this->assertSame($product->id, $response->json('best_sellers.laptop.0.id'));
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
