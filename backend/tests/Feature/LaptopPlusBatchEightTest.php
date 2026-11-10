<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaptopPlusBatchEightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seo.site_origin' => 'https://storefront.example.test']);
    }

    public function test_static_sitemap_skips_gioi_thieu_and_redirected_paths(): void
    {
        Page::create(['title' => 'Giới thiệu', 'slug' => 'gioi-thieu-laptop-plus', 'body' => '<p>x</p>', 'is_active' => true]);
        Redirect::create(['source_path' => '/van-chuyen', 'target' => '/chinh-sach-van-chuyen', 'status_code' => 301, 'is_active' => true]);
        Redirect::create(['source_path' => '/bao-hanh', 'target' => '/x', 'status_code' => 301, 'is_active' => false]);

        $this->get('/sitemaps/static.xml')->assertOk()
            ->assertDontSee('https://storefront.example.test/gioi-thieu</loc>', false)
            ->assertSee('https://storefront.example.test/gioi-thieu-laptop-plus</loc>', false)
            ->assertDontSee('https://storefront.example.test/van-chuyen</loc>', false)
            ->assertSee('https://storefront.example.test/bao-hanh</loc>', false);
    }

    public function test_bct_badge_settings_are_public(): void
    {
        $this->getJson('/api/v1/settings')->assertOk()
            ->assertJsonPath('legal_bct_url', '')
            ->assertJsonPath('legal_bct_image', 'https://online.gov.vn/Content/EndUser/LogoCCDVSaleNoti/logoSaleNoti.png');

        Setting::set('legal_bct_url', 'http://online.gov.vn/Home/WebDetails/123');
        $this->getJson('/api/v1/settings')->assertJsonPath('legal_bct_url', 'http://online.gov.vn/Home/WebDetails/123');
    }
}
