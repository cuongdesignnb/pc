<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('seo.site_origin', 'https://laptopplus.vn');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop', 'is_active' => true]);
        $this->product = Product::create([
            'name' => 'Dell Latitude 7420', 'slug' => 'dell-latitude-7420', 'sku' => 'DELL-7420',
            'category_id' => $category->id, 'price' => 9_000_000, 'stock_quantity' => 2,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
    }

    public function test_admin_creates_lists_and_revokes_tokens(): void
    {
        $this->admin->givePermissionTo(Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']));
        $this->actingAs($this->admin)->post('/admin/api-tokens', ['name' => 'Viết bài AI', 'type' => 'content'])
            ->assertSessionHas('api_token');
        $this->actingAs($this->admin)->get('/admin/api-tokens')->assertInertia(fn ($page) => $page
            ->where('tokens.0.name', 'Viết bài AI')
            ->where('tokens.0.type', 'content'));

        $tokenId = $this->admin->tokens()->firstOrFail()->id;
        $this->actingAs($this->admin)->delete("/admin/api-tokens/{$tokenId}")->assertSessionHas('success');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_content_token_edits_seo_but_never_price_or_stock(): void
    {
        $token = app(AdminApiTokens::class)->issue($this->admin, 'AI', 'content')->plainTextToken;
        $image = $this->product->images()->create(['url' => 'https://cdn.test/a.webp', 'is_primary' => true, 'sort_order' => 0]);

        $this->withToken($token)->getJson('/api/v1/admin-api/products')
            ->assertOk()->assertJsonPath('data.0.url', 'https://laptopplus.vn/laptop/dell-latitude-7420');

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$this->product->id}", [
            'meta_title' => 'Dell Latitude 7420 cũ giá tốt',
            'images' => [['id' => $image->id, 'alt' => 'Dell Latitude 7420 mặt trước']],
        ])->assertOk()->assertJsonPath('data.meta_title', 'Dell Latitude 7420 cũ giá tốt')
            ->assertJsonPath('data.images.0.alt', 'Dell Latitude 7420 mặt trước');

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$this->product->id}", ['price' => 1, 'stock_quantity' => 99])
            ->assertUnprocessable()->assertJsonValidationErrors(['price', 'stock_quantity']);
        $this->assertSame(2, (int) $this->product->fresh()->stock_quantity);

        $this->withToken($token)->postJson('/api/v1/admin-api/posts', [
            'title' => 'Top laptop văn phòng 2026', 'body' => '<p>Nội dung</p><script>alert(1)</script>', 'status' => 'published',
        ])->assertCreated()->assertJsonPath('data.slug', 'top-laptop-van-phong-2026');
        $post = Post::where('slug', 'top-laptop-van-phong-2026')->firstOrFail();
        $this->assertStringNotContainsString('<script', $post->body);
        $this->assertNotNull($post->published_at);

        $this->withToken($token)->postJson('/api/v1/admin-api/categories', ['name' => 'Dell Latitude', 'parent_id' => $this->product->category_id])
            ->assertCreated()->assertJsonPath('data.slug', 'dell-latitude');
    }

    public function test_read_only_customer_and_revoked_tokens_are_refused(): void
    {
        $readonly = app(AdminApiTokens::class)->issue($this->admin, 'KIOT', 'readonly');
        $this->withToken($readonly->plainTextToken)->getJson("/api/v1/admin-api/products/{$this->product->id}")
            ->assertOk()->assertJsonPath('data.stock_quantity', 2);
        $this->withToken($readonly->plainTextToken)->patchJson("/api/v1/admin-api/products/{$this->product->id}", ['meta_title' => 'x'])
            ->assertForbidden();

        $customer = User::factory()->create(['role' => 'customer'])->createToken('auth_token', ['*'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($customer)->getJson('/api/v1/admin-api/products')->assertUnauthorized();

        $readonly->accessToken->delete();
        $this->app['auth']->forgetGuards();
        $this->withToken($readonly->plainTextToken)->getJson('/api/v1/admin-api/products')->assertUnauthorized();
    }
}
