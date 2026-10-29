<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_title_can_be_left_empty_when_the_image_has_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/banners', [
            'title' => '',
            'image' => '/storage/banner.webp',
            'position' => 'hero',
            'sort_order' => 0,
            'is_active' => true,
            'metadata' => ['text_in_image' => 'true'],
        ])->assertRedirect();

        $banner = Banner::query()->sole();
        $this->assertSame('', $banner->title);

        $this->actingAs($admin)->put("/admin/banners/{$banner->id}", [
            'title' => null,
            'image' => '/storage/banner.webp',
            'position' => 'sidebar',
            'sort_order' => 0,
            'is_active' => true,
        ])->assertRedirect();
        $this->assertSame('', $banner->fresh()->title);
        $this->assertSame('sidebar', $banner->fresh()->position);
    }
}
