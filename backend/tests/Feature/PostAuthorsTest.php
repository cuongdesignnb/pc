<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PostAuthorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.test');
    }

    private function makePost(User $user, array $attributes = []): Post
    {
        return Post::create($attributes + [
            'user_id' => $user->id, 'title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>',
            'status' => 'published', 'published_at' => now()->subHour(),
        ]);
    }

    public function test_admin_manages_authors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/authors', [
            'name' => 'Nguyễn Văn Minh', 'job_title' => 'Kỹ thuật viên', 'bio' => 'Sửa laptop 10 năm.',
            'avatar' => '/storage/media/minh.webp', 'social_links' => ['facebook' => 'http://fb.com/minh'], 'is_active' => true,
        ])->assertSessionHasErrors('social_links.facebook');

        $this->actingAs($admin)->post('/admin/authors', [
            'name' => 'Nguyễn Văn Minh', 'job_title' => 'Kỹ thuật viên', 'bio' => 'Sửa laptop 10 năm.',
            'avatar' => '/storage/media/minh.webp', 'social_links' => ['facebook' => 'https://facebook.com/minh', 'youtube' => null], 'is_active' => true,
        ])->assertRedirect('/admin/authors');

        $author = Author::query()->sole();
        $this->assertSame('nguyen-van-minh', $author->slug);
        $this->assertSame(['facebook' => 'https://facebook.com/minh'], $author->social_links);

        // The same slug cannot be taken twice.
        $this->actingAs($admin)->post('/admin/authors', ['name' => 'Nguyễn Văn Minh', 'is_active' => true])
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin)->get('/admin/authors')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Authors/Index')->where('authors.data.0.slug', 'nguyen-van-minh'));

        $this->actingAs($admin)->put("/admin/authors/{$author->id}", [
            'name' => 'Minh Nguyễn', 'slug' => 'minh-nguyen', 'is_active' => true,
        ])->assertRedirect('/admin/authors');
        $this->assertSame('minh-nguyen', $author->fresh()->slug);

        $post = $this->makePost($admin, ['author_id' => $author->id]);
        $this->actingAs($admin)->delete("/admin/authors/{$author->id}")->assertRedirect('/admin/authors');
        $this->assertNull($post->fresh()->author_id);
        $this->assertDatabaseCount('authors', 0);
    }

    public function test_post_form_picks_an_author(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = Author::create(['name' => 'Trần Thu Hà', 'slug' => 'tran-thu-ha', 'is_active' => true]);
        $post = $this->makePost($admin);

        $this->actingAs($admin)->get("/admin/posts/{$post->id}/edit")->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('authors.0.name', 'Trần Thu Hà'));

        $payload = ['title' => 'Bài viết', 'slug' => 'bai-viet', 'body' => '<p>Nội dung</p>', 'status' => 'published'];
        $this->actingAs($admin)->put("/admin/posts/{$post->id}", $payload + ['author_id' => 999])->assertSessionHasErrors('author_id');
        $this->actingAs($admin)->put("/admin/posts/{$post->id}", $payload + ['author_id' => $author->id, 'published_at' => '2026-11-01T10:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame($author->id, $post->fresh()->author_id);
        // The admin who wrote it stays the owner.
        $this->assertSame($admin->id, $post->fresh()->user_id);

        $this->actingAs($admin)->post('/admin/posts', ['slug' => 'bai-moi', 'author_id' => $author->id] + $payload)->assertSessionHasNoErrors();
        $this->assertSame($author->id, Post::query()->where('slug', 'bai-moi')->value('author_id'));
    }

    public function test_public_post_shows_the_author_or_falls_back_to_the_admin_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Laptop Plus']);
        $author = Author::create([
            'name' => 'Trần Thu Hà', 'slug' => 'tran-thu-ha', 'avatar' => '/storage/media/ha.webp', 'bio' => 'Biên tập viên.',
            'job_title' => 'Biên tập viên', 'social_links' => ['website' => 'https://ha.vn'], 'is_active' => true,
        ]);
        $this->makePost($admin, ['author_id' => $author->id]);
        $this->makePost($admin, ['title' => 'Bài cũ', 'slug' => 'bai-cu']);

        $this->getJson('/api/v1/blog/bai-viet')->assertOk()
            ->assertJsonPath('post.author.name', 'Trần Thu Hà')
            ->assertJsonPath('post.author.slug', 'tran-thu-ha')
            ->assertJsonPath('post.author.bio', 'Biên tập viên.')
            ->assertJsonPath('post.author.same_as.0', 'https://ha.vn')
            ->assertJsonPath('post.author.canonical_path', '/tin-tuc/tac-gia/tran-thu-ha')
            ->assertJsonPath('post.author.url', 'https://laptopplus.test/tin-tuc/tac-gia/tran-thu-ha');

        $this->getJson('/api/v1/blog/bai-cu')->assertOk()
            ->assertJsonPath('post.author.name', 'Admin Laptop Plus')
            ->assertJsonPath('post.author.slug', null);

        $this->getJson('/api/v1/blog')->assertOk()
            ->assertJsonFragment(['name' => 'Trần Thu Hà', 'slug' => 'tran-thu-ha', 'canonical_path' => '/tin-tuc/tac-gia/tran-thu-ha']);

        // A hidden author falls back to the admin user too.
        $author->update(['is_active' => false]);
        $this->getJson('/api/v1/blog/bai-viet')->assertJsonPath('post.author.name', 'Admin Laptop Plus');
    }

    public function test_author_page_lists_published_posts_and_404s_for_hidden_authors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = Author::create(['name' => 'Trần Thu Hà', 'slug' => 'tran-thu-ha', 'bio' => '<p>Biên tập viên công nghệ.</p>', 'is_active' => true]);
        $this->makePost($admin, ['author_id' => $author->id]);
        $this->makePost($admin, ['title' => 'Nháp', 'slug' => 'nhap', 'status' => 'draft', 'author_id' => $author->id]);
        $this->makePost($admin, ['title' => 'Khác', 'slug' => 'khac']);

        $this->getJson('/api/v1/blog/authors/tran-thu-ha')->assertOk()
            ->assertJsonPath('author.name', 'Trần Thu Hà')
            ->assertJsonPath('author.posts_count', 1)
            ->assertJsonPath('author.seo.robots', 'index,follow')
            ->assertJsonPath('author.seo.description', 'Biên tập viên công nghệ.')
            ->assertJsonPath('author.seo.canonical_url', 'https://laptopplus.test/tin-tuc/tac-gia/tran-thu-ha')
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.slug', 'bai-viet')
            ->assertJsonPath('meta.total', 1);

        $this->get('/sitemaps/post-categories.xml')->assertOk()->assertSee('/tin-tuc/tac-gia/tran-thu-ha', false);

        $author->update(['is_active' => false]);
        $this->getJson('/api/v1/blog/authors/tran-thu-ha')->assertNotFound();
        $this->getJson('/api/v1/blog/authors/khong-co')->assertNotFound();
    }
}
