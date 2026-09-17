<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_normalized_logo_and_site_name_from_public_settings(): void
    {
        URL::forceRootUrl('https://admin.example.test');
        URL::forceScheme('https');

        Setting::updateOrCreate(['key' => 'site_name'], [
            'value' => 'Tên doanh nghiệp rất dài cần được giới hạn trong sidebar',
            'group' => 'general',
            'type' => 'text',
            'label' => 'Tên website',
            'is_public' => true,
        ]);
        Setting::updateOrCreate(['key' => 'site_logo'], [
            'value' => 'http://localhost:8000/storage/media/hpcom-logo.webp',
            'group' => 'general',
            'type' => 'image',
            'label' => 'Logo website',
            'is_public' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Index')
                ->where('siteName', 'Tên doanh nghiệp rất dài cần được giới hạn trong sidebar')
                ->where('siteLogo', 'https://admin.example.test/storage/media/hpcom-logo.webp'));
    }
}
