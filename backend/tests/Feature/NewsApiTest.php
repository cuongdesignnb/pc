<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_home_returns_curated_sections_and_card_contract(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create([
            'name' => 'Tin tức công nghệ',
            'slug' => 'tin-tuc-cong-nghe',
            'sort_order' => 1,
        ]);

        $hero = collect(range(1, 3))->map(fn (int $index): Post => $this->makePost($author, $category, [
            'title' => "Hero {$index}",
            'is_featured' => true,
            'view_count' => 1000 + $index,
            'published_at' => now()->subDays($index),
        ]));
        $featured = $this->makePost($author, $category, [
            'title' => 'Featured after hero',
            'is_featured' => true,
            'view_count' => 9000,
            'published_at' => now()->subDays(5),
        ]);
        foreach (range(1, 10) as $index) {
            $this->makePost($author, $category, [
                'title' => "Latest {$index}",
                'view_count' => 200 + $index,
                'published_at' => now()->subDays(10 + $index),
            ]);
        }

        $response = $this->getJson('/api/v1/blog/home')->assertOk();

        $response->assertJsonStructure([
            'hero',
            'featured',
            'latest',
            'trending',
            'categories',
            'topics',
            'pc_builder',
        ]);

        $payload = $response->json();
        $this->assertCount(3, $payload['hero']);
        $this->assertSame($featured->id, $payload['featured'][0]['id']);
        $this->assertCount(3, $payload['featured']);
        $this->assertCount(8, $payload['latest']);
        $this->assertCount(5, $payload['trending']);
        $this->assertSame($hero->pluck('id')->all(), array_column($payload['hero'], 'id'));

        $card = $payload['hero'][0];
        $this->assertArrayNotHasKey('body', $card);
        $this->assertArrayNotHasKey('status', $card);
        $this->assertArrayNotHasKey('user_id', $card);
        $this->assertArrayNotHasKey('created_at', $card);
        $this->assertSame($category->slug, $card['category']['slug']);
        $this->assertSame($author->name, $card['author']['name']);
    }

    public function test_news_home_orders_trending_and_excludes_draft_or_future_posts(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create([
            'name' => 'Review sản phẩm',
            'slug' => 'review-san-pham',
            'sort_order' => 1,
        ]);
        $highest = $this->makePost($author, $category, ['title' => 'Most read', 'view_count' => 500]);
        $middle = $this->makePost($author, $category, ['title' => 'Second most read', 'view_count' => 300]);
        $lowest = $this->makePost($author, $category, ['title' => 'Least read', 'view_count' => 100]);
        $draft = $this->makePost($author, $category, [
            'title' => 'Draft must stay private',
            'status' => 'draft',
            'is_featured' => true,
        ]);
        $future = $this->makePost($author, $category, [
            'title' => 'Future must stay private',
            'published_at' => now()->addDay(),
            'is_featured' => true,
        ]);

        $payload = $this->getJson('/api/v1/blog/home')->assertOk()->json();
        $trendingIds = array_column($payload['trending'], 'id');

        $this->assertSame([$highest->id, $middle->id, $lowest->id], array_slice($trendingIds, 0, 3));
        $allIds = collect([
            ...$payload['hero'],
            ...$payload['featured'],
            ...$payload['latest'],
            ...$payload['trending'],
        ])->pluck('id')->all();
        $this->assertNotContains($draft->id, $allIds);
        $this->assertNotContains($future->id, $allIds);
        $this->getJson('/api/v1/blog/'.$future->slug)->assertNotFound();
    }

    public function test_category_count_only_includes_published_posts(): void
    {
        $author = User::factory()->create();
        $category = PostCategory::create([
            'name' => 'Hướng dẫn',
            'slug' => 'huong-dan',
            'sort_order' => 1,
        ]);
        $this->makePost($author, $category, ['title' => 'Published post']);
        $this->makePost($author, $category, ['title' => 'Draft post', 'status' => 'draft']);
        $this->makePost($author, $category, ['title' => 'Scheduled post', 'published_at' => now()->addDay()]);

        $categories = $this->getJson('/api/v1/blog/categories')->assertOk()->json();
        $categoryPayload = collect($categories)->firstWhere('slug', $category->slug);

        $this->assertNotNull($categoryPayload);
        $this->assertSame(1, $categoryPayload['posts_count']);
    }

    /** @param array<string, mixed> $overrides */
    private function makePost(User $author, PostCategory $category, array $overrides = []): Post
    {
        $title = (string) ($overrides['title'] ?? 'News '.Str::random(8));

        return Post::create(array_merge([
            'user_id' => $author->id,
            'post_category_id' => $category->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'excerpt' => 'News excerpt',
            'body' => '<p>News body</p>',
            'featured_image' => 'https://example.test/news.webp',
            'status' => 'published',
            'view_count' => 50,
            'is_featured' => false,
            'published_at' => now()->subHour(),
        ], $overrides));
    }
}
