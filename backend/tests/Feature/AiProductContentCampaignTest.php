<?php

namespace Tests\Feature;

use App\Models\AiProductContentCampaign;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ProductContentCampaignService;
use App\Services\Ai\ProductContentResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiProductContentCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_campaign_snapshots_selected_products_without_changing_product_content(): void
    {
        $admin = $this->admin();
        $product = $this->product(['description' => 'Mô tả cũ', 'meta_title' => 'Title cũ']);

        $this->actingAs($admin)
            ->postJson('/admin/ai-product-campaigns', [
                'name' => 'Campaign nháp',
                'selected_product_ids' => [$product->id],
                'filters' => ['missing_description' => false],
                'mode' => 'draft',
                'technical_heading' => 'specifications',
                'use_web_research' => false,
                'append_contact_footer' => false,
                'scheduled_at' => now()->addMinute()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $campaign = AiProductContentCampaign::query()->with('items')->firstOrFail();

        $this->assertSame('draft', $campaign->mode);
        $this->assertTrue($campaign->include_product_images);
        $this->assertSame('pending', $campaign->status);
        $this->assertSame($product->id, $campaign->items->first()->product_id);
        $this->assertSame('Mô tả cũ', $product->fresh()->description);
        $this->assertSame('Title cũ', $product->fresh()->meta_title);
    }

    public function test_apply_updates_only_allowed_content_and_writes_proposed_text_specs_when_safe(): void
    {
        $product = $this->product([
            'description' => 'Cũ',
            'short_description' => 'Tóm tắt cũ',
            'meta_title' => 'Title cũ',
            'meta_description' => 'Meta cũ',
            'price' => 123456,
            'stock_quantity' => 7,
            'specifications_text' => null,
        ]);
        $service = app(ProductContentCampaignService::class);
        $campaign = AiProductContentCampaign::create([
            'name' => 'Publish an toàn',
            'mode' => 'publish',
            'technical_heading' => 'specifications',
            'max_items' => 1,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);
        $item = $campaign->items()->create([
            'product_id' => $product->id,
            'source_snapshot' => $service->snapshot($product->fresh()->load('specifications.specificationKey')),
            'generated_payload' => [
                'content' => '<p>Mới</p>',
                'short_description' => 'Tóm tắt mới',
                'meta_title' => 'Title mới',
                'meta_description' => 'Meta mới',
                'proposed_specifications' => [['label' => 'CPU', 'value' => 'Core i5', 'unit' => null]],
            ],
            'status' => 'draft',
        ]);

        $service->applyItem($item);
        $updated = $product->fresh();

        $this->assertSame('<p>Mới</p>', $updated->description);
        $this->assertSame('Tóm tắt mới', $updated->short_description);
        $this->assertSame('Title mới', $updated->meta_title);
        $this->assertSame('Meta mới', $updated->meta_description);
        $this->assertSame('123456', (string) $updated->price);
        $this->assertSame(7, $updated->stock_quantity);
        $this->assertSame('CPU: Core i5', $updated->specifications_text);
        $this->assertSame('applied', $item->fresh()->status);
    }

    public function test_apply_persists_only_public_product_images_with_generated_alt_blocks(): void
    {
        $product = $this->product(['name' => 'Card màn hình test AI']);
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'local',
            'url' => 'https://cdn.example.test/products/card.webp',
            'alt_text' => null,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'local',
            'url' => 'https://cdn.example.test/products/card.webp',
            'alt_text' => 'Ảnh trùng không được lặp',
            'sort_order' => 1,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'local',
            'url' => 'javascript:alert(1)',
            'sort_order' => 2,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'local',
            'url' => '//unsafe.example.test/card.webp',
            'sort_order' => 3,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'provider' => 'kiot',
            'url' => 'https://kiot.example.test/card.webp',
            'storage_path' => null,
            'sort_order' => 4,
        ]);

        $service = app(ProductContentCampaignService::class);
        $product = $product->fresh()->load(['images', 'specifications.specificationKey']);
        $campaign = AiProductContentCampaign::create([
            'name' => 'Ảnh sản phẩm',
            'mode' => 'draft',
            'technical_heading' => 'specifications',
            'include_product_images' => true,
            'max_items' => 1,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);
        $articleImages = $service->articleImages($product);
        $item = $campaign->items()->create([
            'product_id' => $product->id,
            'source_snapshot' => $service->snapshot($product, true),
            'generated_payload' => [
                'content' => '<p>Nội dung có ảnh sản phẩm.</p>',
                'short_description' => 'Tóm tắt',
                'meta_title' => 'Title',
                'meta_description' => 'Meta',
                'article_images' => $articleImages,
            ],
            'status' => 'draft',
        ]);

        $this->assertCount(1, $articleImages);
        $this->assertSame('https://cdn.example.test/products/card.webp', $articleImages[0]['url']);
        $this->assertSame('Card màn hình test AI - hình ảnh sản phẩm 1', $articleImages[0]['alt']);

        $service->applyItem($item);
        $blocks = $product->fresh()->detailBlocks()->where('type', 'image_text')->get();

        $this->assertCount(1, $blocks);
        $this->assertSame('ai-product-content-campaign', $blocks[0]->payload['managed_by']);
        $this->assertSame('https://cdn.example.test/products/card.webp', $blocks[0]->payload['image_url']);
        $this->assertSame('Card màn hình test AI - hình ảnh sản phẩm 1', $blocks[0]->payload['alt']);
        $this->assertSame('applied', $item->fresh()->status);
    }

    public function test_apply_marks_snapshot_conflict_for_review(): void
    {
        $product = $this->product(['description' => 'Cũ']);
        $service = app(ProductContentCampaignService::class);
        $campaign = AiProductContentCampaign::create([
            'name' => 'Conflict',
            'mode' => 'draft',
            'technical_heading' => 'auto',
            'max_items' => 1,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);
        $item = $campaign->items()->create([
            'product_id' => $product->id,
            'source_snapshot' => $service->snapshot($product->fresh()->load('specifications.specificationKey')),
            'generated_payload' => ['content' => 'Mới', 'short_description' => '', 'meta_title' => '', 'meta_description' => ''],
            'status' => 'draft',
        ]);
        $product->update(['description' => 'Đã sửa thủ công']);

        $this->expectExceptionMessage('Sản phẩm đã thay đổi sau khi tạo bản nháp.');
        try {
            $service->applyItem($item);
        } finally {
            $this->assertSame('needs_review', $item->fresh()->status);
        }
    }

    public function test_web_research_requires_allowlisted_official_source_and_exact_model_match(): void
    {
        config()->set('ai.content.api_key', 'test-key');
        Setting::set('ai_product_research_enabled', true);
        Setting::set('ai_product_research_official_domains', "NVIDIA=nvidia.com\nIntel=intel.com");
        $brand = Brand::create(['name' => 'NVIDIA', 'slug' => 'nvidia', 'is_active' => true]);
        $product = $this->product(['name' => 'NVIDIA GeForce RTX 5080', 'sku' => 'RTX5080-TEST'], ['brand' => $brand]);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'model_match' => true,
                    'specifications' => [['label' => 'CUDA Cores', 'value' => '10752', 'unit' => null]],
                ]),
                'output' => [[
                    'type' => 'web_search_call',
                    'action' => ['sources' => [['url' => 'https://www.nvidia.com/en-us/geforce/graphics-cards/50-series/rtx-5080/', 'title' => 'NVIDIA RTX 5080']]],
                ]],
            ], 200),
        ]);

        $result = app(ProductContentResearchService::class)->research($product);

        $this->assertTrue($result['verified']);
        $this->assertSame('www.nvidia.com', $result['sources'][0]['domain']);
        Http::assertSent(fn ($request) => $request->data()['tools'][0]['filters']['allowed_domains'] === ['nvidia.com']);
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'ai-product-content.create', 'guard_name' => 'web']);
        $view = Permission::firstOrCreate(['name' => 'ai-product-content.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo([$permission, $view]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole($role);

        return $admin;
    }

    private function product(array $overrides = [], array $relations = []): Product
    {
        $category = $relations['category'] ?? Category::create([
            'name' => 'PC Gaming',
            'slug' => 'pc-gaming',
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'brand_id' => ($relations['brand'] ?? null)?->id,
            'name' => 'Sản phẩm test AI',
            'slug' => 'san-pham-test-ai-'.uniqid(),
            'sku' => 'AI-'.uniqid(),
            'description' => null,
            'short_description' => null,
            'price' => 0,
            'sale_price' => null,
            'stock_quantity' => 1,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
