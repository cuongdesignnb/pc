<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        URL::forceRootUrl('https://admin.example.test');
        URL::forceScheme('https');
    }

    public function test_admin_prefers_the_white_logo_and_normalizes_legacy_local_urls(): void
    {
        Setting::set('site_name', 'HPCom Việt Nam');
        Setting::set('site_logo', '/storage/media/logo-default.svg');
        Setting::query()->updateOrCreate(['key' => 'site_logo_white'], [
            'value' => 'http://localhost:8000/storage/media/logo-white.svg',
            'group' => 'general',
            'type' => 'text',
            'label' => 'Logo trắng',
            'is_public' => true,
        ]);
        Cache::forget('setting.site_logo_white');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteName', 'HPCom Việt Nam')
                ->where('siteLogo', 'https://admin.example.test/storage/media/logo-white.svg'));
    }

    public function test_admin_falls_back_to_the_default_logo_when_white_logo_is_empty(): void
    {
        Setting::set('site_logo_white', '   ');
        Setting::set('site_logo', '/storage/media/logo-default.svg');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteLogo', 'https://admin.example.test/storage/media/logo-default.svg'));
    }

    public function test_admin_uses_a_null_logo_when_no_logo_is_configured(): void
    {
        Setting::set('site_logo_white', '');
        Setting::set('site_logo', '');

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('siteLogo', null));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
