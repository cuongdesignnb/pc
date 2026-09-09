<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NewsArticleDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_returns_a_safe_resource_and_does_not_increment_on_get(): void
    {
        $author = User::factory()->create([
            'name' => 'Nguyễn Hoàng Nam',
            'email' => 'author@example.test',
            'phone' => '0900000000',
            'role' => 'staff',
        ]);
        $category = PostCategory::create([
            'name' => 'Tin công nghệ',
            'slug' => 'tin-cong-nghe',
        ]);
        $post = $this->createPost($author, $category, [
            'title' => 'RTX 5080 chính thức ra mắt',
            'slug' => 'rtx-5080-chinh-thuc-ra-mat',
            'body' => '<h2>RTX 5080 có gì mới?</h2><h2>RTX 5080 có gì mới?</h2><p><a href="javascript:alert(1)">Liên kết xấu</a><img src="x.jpg" onerror="alert(1)"></p><blockquote>Điểm nhấn công nghệ.</blockquote><iframe src="https://evil.example/embed/video"></iframe><script>alert(1)</script>',
            'view_count' => 37,
        ]);

        $response = $this->getJson('/api/v1/blog/'.$post->slug)->assertOk();

        $response
            ->assertJsonPath('post.id', $post->id)
            ->assertJsonPath('post.category.slug', 'tin-cong-nghe')
            ->assertJsonPath('post.author.id', $author->id)
            ->assertJsonPath('post.author.name', 'Nguyễn Hoàng Nam')
            ->assertJsonPath('post.view_count', 37)
            ->assertJsonPath('post.toc.0.title', 'RTX 5080 có gì mới?')
            ->assertJsonPath('post.toc.0.id', 'rtx-5080-co-gi-moi')
            ->assertJsonPath('post.toc.1.id', 'rtx-5080-co-gi-moi-2')
            ->assertJsonPath('post.seo.title', 'RTX 5080 chính thức ra mắt')
            ->assertJsonMissingPath('post.author.email')
            ->assertJsonMissingPath('post.author.phone')
            ->assertJsonMissingPath('post.author.role');
        $this->assertStringNotContainsString('<script', (string) $response->json('post.body'));
        $this->assertStringNotContainsString('javascript:', (string) $response->json('post.body'));
        $this->assertStringNotContainsString('onerror', (string) $response->json('post.body'));
        $this->assertStringNotContainsString('<iframe', (string) $response->json('post.body'));
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'view_count' => 37]);
    }

    public function test_detail_hides_draft_and_future_posts_and_only_returns_published_sidebar_content(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create(['name' => 'Tin công nghệ', 'slug' => 'tin-cong-nghe']);
        $reviewCategory = PostCategory::create(['name' => 'Review', 'slug' => 'review-san-pham']);
        $post = $this->createPost($author, $category, ['slug' => 'published-current']);
        $related = $this->createPost($author, $category, ['slug' => 'published-related', 'view_count' => 20]);
        $review = $this->createPost($author, $reviewCategory, ['slug' => 'published-review', 'view_count' => 90]);
        $draft = $this->createPost($author, $category, ['slug' => 'draft-post', 'status' => 'draft', 'view_count' => 900]);
        $future = $this->createPost($author, $category, ['slug' => 'future-post', 'published_at' => now()->addDay(), 'view_count' => 800]);

        $response = $this->getJson('/api/v1/blog/'.$post->slug)->assertOk();

        $response->assertJsonPath('sidebar.reviews.0.slug', $review->slug);
        $relatedSlugs = collect($response->json('related'))->pluck('slug')->all();
        $this->assertContains($related->slug, $relatedSlugs);
        $this->assertNotContains($draft->slug, $relatedSlugs);
        $this->assertNotContains($future->slug, $relatedSlugs);
        $trendingSlugs = collect($response->json('sidebar.trending'))->pluck('slug')->all();
        $this->assertContains($review->slug, $trendingSlugs);
        $this->assertNotContains($draft->slug, $trendingSlugs);
        $this->assertNotContains($future->slug, $trendingSlugs);

        $this->getJson('/api/v1/blog/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/v1/blog/'.$future->slug)->assertNotFound();
    }

    public function test_view_tracking_is_deduplicated_without_mutating_get(): void
    {
        Cache::flush();
        $author = User::factory()->create();
        $category = PostCategory::create(['name' => 'Tin công nghệ', 'slug' => 'tin-cong-nghe']);
        $post = $this->createPost($author, $category, ['slug' => 'view-tracking', 'view_count' => 10]);
        $headers = ['User-Agent' => 'NewsArticleDetailTest/1.0'];

        $first = $this->withHeaders($headers)->postJson('/api/v1/blog/'.$post->slug.'/view')->assertOk();
        $second = $this->withHeaders($headers)->postJson('/api/v1/blog/'.$post->slug.'/view')->assertOk();

        $first->assertJsonPath('tracked', true)->assertJsonPath('view_count', 11);
        $second->assertJsonPath('tracked', false)->assertJsonPath('view_count', 11);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'view_count' => 11]);
    }

    /** @param array<string, mixed> $overrides */
    private function createPost(User $author, PostCategory $category, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $author->id,
            'post_category_id' => $category->id,
            'title' => isset($overrides['slug']) ? 'Bài viết '.$overrides['slug'] : 'Bài viết',
            'slug' => 'post-'.fake()->unique()->slug(),
            'excerpt' => 'Tóm tắt bài viết.',
            'body' => '<p>Nội dung bài viết.</p>',
            'featured_image' => null,
            'status' => 'published',
            'view_count' => 0,
            'is_featured' => false,
            'published_at' => now()->subDay(),
            'meta_title' => null,
            'meta_description' => null,
        ], $overrides));
    }
}
