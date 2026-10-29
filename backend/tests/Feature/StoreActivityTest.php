<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\StoreActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_photos_and_videos(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/store-activities', ['title' => 'Ảnh', 'type' => 'image', 'image' => '', 'is_active' => true])
            ->assertSessionHasErrors('image');
        $this->actingAs($admin)->post('/admin/store-activities', ['title' => 'Video', 'type' => 'video', 'video_url' => 'https://example.com/clip', 'is_active' => true])
            ->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->post('/admin/store-activities', [
            'title' => 'Video lớn', 'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'image' => 'https://example.test/old.webp', 'sort_order' => 0, 'is_active' => true,
        ])->assertRedirect('/admin/store-activities');
        $this->actingAs($admin)->post('/admin/store-activities', [
            'title' => 'Kỹ thuật test máy', 'type' => 'image', 'image' => '/storage/media/test.webp', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'sort_order' => 1, 'is_active' => true,
        ])->assertRedirect('/admin/store-activities');
        $this->assertNull(StoreActivity::where('type', 'image')->value('video_url'));

        $video = StoreActivity::where('type', 'video')->firstOrFail();
        $this->actingAs($admin)->put("/admin/store-activities/{$video->id}", [
            'title' => 'Video lớn', 'type' => 'video', 'video_url' => 'https://www.facebook.com/laptopplus247/videos/123', 'image' => '', 'sort_order' => 0, 'is_active' => true,
        ])->assertRedirect('/admin/store-activities');

        $this->actingAs($admin)->get('/admin/store-activities')->assertOk();
    }

    public function test_homepage_returns_playable_active_items_in_order(): void
    {
        StoreActivity::create(['title' => 'Ẩn', 'type' => 'image', 'image' => '/storage/media/a.webp', 'sort_order' => 0, 'is_active' => false]);
        StoreActivity::create(['title' => 'Link hỏng', 'type' => 'video', 'video_url' => 'https://example.com/x', 'sort_order' => 0, 'is_active' => true]);
        StoreActivity::create(['title' => 'Đóng gói', 'type' => 'image', 'image' => '/storage/media/b.webp', 'sort_order' => 2, 'is_active' => true]);
        StoreActivity::create(['title' => 'Tư vấn', 'type' => 'video', 'video_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'sort_order' => 1, 'is_active' => true]);
        StoreActivity::create(['title' => 'Thay pin', 'type' => 'video', 'video_url' => '/storage/media/thay-pin.mp4', 'sort_order' => 3, 'is_active' => true]);

        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonCount(3, 'store_activities')
            ->assertJsonPath('store_activities.0.title', 'Tư vấn')
            ->assertJsonPath('store_activities.0.video.provider', 'youtube')
            ->assertJsonPath('store_activities.0.video.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('store_activities.0.image', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg')
            ->assertJsonPath('store_activities.1.type', 'image')
            ->assertJsonPath('store_activities.1.image', url('/storage/media/b.webp'))
            ->assertJsonPath('store_activities.1.video', null)
            ->assertJsonPath('store_activities.2.video.provider', 'file')
            ->assertJsonPath('store_activities.2.video.file_url', url('/storage/media/thay-pin.mp4'));
    }

    public function test_settings_validate_theme_colours_and_product_tabs(): void
    {
        Category::create(['name' => 'Laptop cũ', 'slug' => 'laptop-cu', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put('/admin/settings', ['settings' => [['key' => 'theme_primary_color', 'value' => 'red']]])
            ->assertSessionHasErrors('theme_primary_color');
        $this->actingAs($admin)->put('/admin/settings', ['settings' => [['key' => 'homepage_product_tabs', 'value' => '[{"label":"X","category_slug":"khong-co"}]']]])
            ->assertSessionHasErrors('homepage_product_tabs');

        $this->actingAs($admin)->put('/admin/settings', ['settings' => [
            ['key' => 'theme_primary_color', 'value' => '#e30613'],
            ['key' => 'homepage_product_tabs', 'value' => '[{"label":" Laptop cũ ","category_slug":"laptop-cu"},{"label":"Trống","category_slug":""}]'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('#E30613', Setting::get('theme_primary_color'));
        $this->assertSame([['label' => 'Laptop cũ', 'category_slug' => 'laptop-cu']], Setting::get('homepage_product_tabs'));
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('theme_primary_color', '#E30613')
            ->assertJsonPath('theme_secondary_color', '#0B1F3A')
            ->assertJsonPath('theme_accent_color', '#FFC72C');
    }
}
