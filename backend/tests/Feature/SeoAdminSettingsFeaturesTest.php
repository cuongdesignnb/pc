<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ProductContactFooterService;
use App\Support\FaqExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SeoAdminSettingsFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_seo_and_business_settings_exist_after_migration(): void
    {
        foreach (['homepage_h1', 'google_site_verification', 'business_schema_type', 'business_opening_hours', 'contact_maps_url', 'llms_txt'] as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }
        $this->assertSame('ComputerStore', Setting::get('business_schema_type'));
        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('business_schema_type', 'ComputerStore')
            ->assertJsonMissingPath('llms_txt');
    }

    public function test_llms_txt_is_served_from_settings_and_404_when_empty(): void
    {
        $this->get('/llms.txt')->assertNotFound();

        Setting::set('llms_txt', "# Laptop Plus\r\n> Laptop cũ uy tín");
        Setting::clearCache();

        $response = $this->get('/llms.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame("# Laptop Plus\n> Laptop cũ uy tín\n", $response->getContent());
    }

    public function test_faq_extractor_reads_questions_under_a_faq_heading(): void
    {
        $html = '<h2>Giới thiệu</h2><p>Nội dung.</p>'
            .'<h2>Câu hỏi thường gặp</h2>'
            .'<h3>Laptop cũ có bảo hành không?</h3><p>Có, bảo hành 6 tháng.</p><p>Đổi mới 15 ngày.</p>'
            .'<p><strong>Có giao hàng toàn quốc không?</strong></p><p>Có &amp; miễn phí nội thành.</p>'
            .'<h2>Kết luận</h2><h3>Không phải câu hỏi?</h3><p>Bỏ qua.</p>';

        $this->assertSame([
            ['question' => 'Laptop cũ có bảo hành không?', 'answer' => 'Có, bảo hành 6 tháng. Đổi mới 15 ngày.'],
            ['question' => 'Có giao hàng toàn quốc không?', 'answer' => 'Có & miễn phí nội thành.'],
        ], FaqExtractor::extract($html));
        $this->assertSame([], FaqExtractor::extract('<h2>Bài viết</h2><h3>Một câu?</h3><p>Không có mục hỏi đáp.</p>'));
    }

    public function test_admin_post_time_is_read_and_shown_in_vietnam_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $post = Post::create([
            'title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>', 'status' => 'draft', 'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/admin/posts/{$post->id}", [
            'title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>', 'status' => 'published',
            'published_at' => '2026-10-27T23:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-27 16:00:00', $post->fresh()->published_at->utc()->format('Y-m-d H:i:s'));
        $this->actingAs($admin)->get("/admin/posts/{$post->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('publishedAtLocal', '2026-10-27T23:00'));
    }

    public function test_contact_footer_lists_the_settings_still_missing(): void
    {
        Setting::updateOrCreate(['key' => 'storefront_product_contact_footer_enabled'], ['value' => '1', 'group' => 'storefront', 'type' => 'boolean', 'label' => 'x']);
        Setting::clearCache();

        $missing = app(ProductContactFooterService::class)->missing();

        $this->assertContains('Địa chỉ cửa hàng', $missing);
        $this->assertContains('Hotline hoặc số điện thoại', $missing);
        $this->assertNotContains('Bật "Footer liên hệ sản phẩm"', $missing);
    }
}
