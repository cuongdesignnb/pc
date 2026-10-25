<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductVideoAndContactMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('integrations.kiot.enabled', false);
        config()->set('integrations.kiot.order_sync_enabled', false);
    }

    public function test_admin_product_video_is_stored_as_canonical_youtube_url(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = $this->category();

        $this->actingAs($admin)
            ->post('/admin/products', $this->payload($category, ['video_url' => 'https://youtu.be/dQw4w9WgXcQ?si=share']))
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'VIDEO-SKU-1')->firstOrFail();
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $product->video_url);

        $this->actingAs($admin)
            ->put('/admin/products/'.$product->id, $this->payload($category, ['video_url' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->video_url);
    }

    public function test_admin_product_rejects_non_youtube_video(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/admin/products', $this->payload($this->category(), ['video_url' => 'https://evil.example/video.mp4']))
            ->assertSessionHasErrors('video_url');

        $this->assertFalse(Product::where('sku', 'VIDEO-SKU-1')->exists());
    }

    public function test_product_detail_api_exposes_embeddable_video(): void
    {
        $product = $this->product(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $withoutVideo = $this->product();

        $this->getJson('/api/v1/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('product.video.id', 'dQw4w9WgXcQ')
            ->assertJsonPath('product.video.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');

        $this->getJson('/api/v1/products/'.$withoutVideo->slug)
            ->assertOk()
            ->assertJsonPath('product.video', null);
    }

    public function test_contact_map_setting_keeps_only_google_embed_src_and_is_public(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $src = 'https://www.google.com/maps/embed?pb=!1m18!1m12!3d21.0';

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => [[
                'key' => 'contact_map_embed_url',
                'value' => '<iframe src="'.$src.'" width="600" height="450" onload="alert(1)"></iframe>',
            ]]])
            ->assertSessionHasNoErrors();

        $this->assertSame($src, Setting::get('contact_map_embed_url'));
        $this->getJson('/api/v1/settings')->assertOk()->assertJsonPath('contact_map_embed_url', $src);
    }

    public function test_contact_map_setting_rejects_foreign_iframe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::set('contact_map_embed_url', 'https://www.google.com/maps/embed?pb=old');

        $this->actingAs($admin)
            ->put('/admin/settings', ['settings' => [[
                'key' => 'contact_map_embed_url',
                'value' => '<iframe src="https://evil.example/phish"></iframe>',
            ]]])
            ->assertSessionHasErrors('contact_map_embed_url');

        $this->assertSame('https://www.google.com/maps/embed?pb=old', Setting::get('contact_map_embed_url'));
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop-'.Str::lower(Str::random(6)),
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Video Test '.Str::random(8),
            'slug' => 'video-'.Str::lower(Str::random(12)),
            'sku' => 'VID-'.Str::upper(Str::random(10)),
            'price' => 1000,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Laptop có video',
            'slug' => 'laptop-co-video',
            'sku' => 'VIDEO-SKU-1',
            'category_id' => $category->id,
            'price' => 1000,
            'stock_quantity' => 1,
            'is_active' => true,
            'is_featured' => false,
            'show_on_pc_website' => true,
            'gallery' => [],
            'variants' => [],
            'highlights' => [],
            'detail_blocks' => [],
            'relations' => [],
            'compatibility_specs' => [],
        ], $overrides);
    }
}
