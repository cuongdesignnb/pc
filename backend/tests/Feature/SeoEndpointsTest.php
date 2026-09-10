<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.test');
    }

    public function test_sitemap_index_and_news_category_shard_expose_only_published_categories(): void
    {
        $author = User::factory()->create();
        $publishedCategory = PostCategory::create([
            'name' => 'Tin công nghệ',
            'slug' => 'tin-cong-nghe',
        ]);
        $emptyCategory = PostCategory::create([
            'name' => 'Chưa xuất bản',
            'slug' => 'chua-xuat-ban',
        ]);

        Post::create([
            'user_id' => $author->id,
            'post_category_id' => $publishedCategory->id,
            'title' => 'Bài viết đã xuất bản',
            'slug' => 'bai-viet-da-xuat-ban',
            'excerpt' => 'Tóm tắt.',
            'body' => '<p>Nội dung.</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        Post::create([
            'user_id' => $author->id,
            'post_category_id' => $emptyCategory->id,
            'title' => 'Bản nháp',
            'slug' => 'ban-nhap',
            'excerpt' => 'Tóm tắt.',
            'body' => '<p>Nội dung.</p>',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $index = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://laptopplus.test/sitemaps/post-categories.xml', false);
        $this->assertNotFalse(simplexml_load_string($index->getContent(), \SimpleXMLElement::class, LIBXML_NONET));

        $categories = $this->get('/sitemaps/post-categories.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('https://laptopplus.test/tin-tuc/chuyen-muc/tin-cong-nghe', false)
            ->assertDontSee('chua-xuat-ban', false);
        $this->assertNotFalse(simplexml_load_string($categories->getContent(), \SimpleXMLElement::class, LIBXML_NONET));
    }
}
