<?php

namespace Tests\Feature;

use App\Jobs\Seo\SubmitIndexNowUrl;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Seo\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.vn');
        $this->setting('indexnow_key', 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'text');
        $this->setting('indexnow_enabled', '0', 'boolean');
    }

    public function test_key_file_is_served_and_nothing_is_sent_while_disabled(): void
    {
        $this->get('/indexnow-key.txt')->assertOk()->assertSee('a1b2c3d4e5f60718293a4b5c6d7e8f90', false);

        Queue::fake();
        $this->product();
        Queue::assertNothingPushed();
    }

    public function test_edits_queue_the_public_url_but_price_and_stock_updates_do_not(): void
    {
        Setting::set('indexnow_enabled', true);
        Queue::fake();

        $product = $this->product();
        Queue::assertPushed(SubmitIndexNowUrl::class, fn ($job) => $job->url === 'https://laptopplus.vn/laptop/dell-latitude-7420');

        Queue::fake();
        $product->update(['price' => 8_000_000, 'stock_quantity' => 0]);
        Queue::assertNothingPushed();
        $product->update(['meta_description' => 'Dell Latitude 7420 cũ giá tốt']);
        Queue::assertPushed(SubmitIndexNowUrl::class, 1);

        Queue::fake();
        $post = Post::create(['user_id' => User::factory()->create()->id, 'title' => 'Nháp', 'slug' => 'nhap', 'body' => 'x', 'status' => 'draft']);
        Queue::assertNothingPushed();
        $post->update(['status' => 'published', 'published_at' => now()]);
        Queue::assertPushed(SubmitIndexNowUrl::class, fn ($job) => str_ends_with($job->url, '/nhap'));
    }

    public function test_submission_posts_host_key_and_key_location(): void
    {
        Setting::set('indexnow_enabled', true);
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);

        $this->assertTrue(app(IndexNow::class)->submit(['https://laptopplus.vn/laptop/dell-latitude-7420', 'https://evil.test/x']));

        Http::assertSent(fn (Request $request) => $request->url() === IndexNow::ENDPOINT
            && $request['host'] === 'laptopplus.vn'
            && $request['key'] === 'a1b2c3d4e5f60718293a4b5c6d7e8f90'
            && $request['keyLocation'] === 'https://laptopplus.vn/indexnow-key.txt'
            && $request['urlList'] === ['https://laptopplus.vn/laptop/dell-latitude-7420']);
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop', 'is_active' => true]);

        return Product::create([
            'name' => 'Dell Latitude 7420', 'slug' => 'dell-latitude-7420', 'sku' => 'DELL-7420',
            'category_id' => $category->id, 'price' => 9_000_000, 'stock_quantity' => 1,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    private function setting(string $key, string $value, string $type): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], [
            'value' => $value, 'group' => 'seo', 'type' => $type, 'label' => $key, 'is_public' => false,
        ]);
        Setting::clearCache();
    }
}
