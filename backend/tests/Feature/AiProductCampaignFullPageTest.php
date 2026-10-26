<?php

namespace Tests\Feature;

use App\Jobs\Ai\ProcessAiProductContentCampaignItem;
use App\Models\AiProductContentCampaign;
use App\Models\Category;
use App\Models\Product;
use App\Services\Ai\ProductContentCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProductCampaignFullPageTest extends TestCase
{
    use RefreshDatabase;

    private const SECTIONS = ['description', 'specifications', 'highlights', 'blocks'];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.content.api_key', 'content-key');
        config()->set('ai.content.base_url', 'https://modelapi.vn/v1');
        config()->set('ai.content.wire_api', 'chat_completions');
        config()->set('integrations.kiot.enabled', false);
        Http::fake(['https://modelapi.vn/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'short_description' => 'Laptop doanh nhân bền bỉ.',
                'content' => '<h2>Tổng quan</h2><p>Bài viết đầy đủ.</p>',
                'meta_title' => 'Dell Latitude 5480',
                'meta_desc' => 'Laptop Dell Latitude 5480.',
                'specifications' => [['label' => 'CPU', 'value' => 'Intel Core i5-6440HQ'], ['label' => 'RAM', 'value' => '8GB DDR4']],
                'highlights' => ['Core i5 4 nhân', 'SSD 256GB'],
                'detail_blocks' => [
                    ['type' => 'benchmark_cards', 'title' => 'Cấu hình nổi bật', 'cards' => [['title' => 'CPU', 'value' => 'i5-6440HQ']]],
                    ['type' => 'feature_cards', 'title' => 'Điểm mạnh', 'cards' => [['title' => 'Bền bỉ']]],
                    ['type' => 'use_case_cards', 'title' => 'Phù hợp cho', 'cards' => [['icon' => '💼', 'title' => 'Văn phòng']]],
                ],
            ], JSON_UNESCAPED_UNICODE)]]],
        ])]);
    }

    public function test_publish_campaign_holds_ai_specifications_for_review_then_applies_full_page(): void
    {
        $product = $this->product();
        $item = $this->campaign('publish', self::SECTIONS, $product)->items()->first();

        (new ProcessAiProductContentCampaignItem($item->id))->handle(app(ProductContentCampaignService::class));

        $item->refresh();
        $this->assertSame('needs_review', $item->status);
        $this->assertSame([['label' => 'CPU', 'value' => 'Intel Core i5-6440HQ'], ['label' => 'RAM', 'value' => '8GB DDR4']], $item->generated_payload['ai_specifications']);
        $this->assertNull($product->fresh()->description, 'Nothing is published before review.');

        app(ProductContentCampaignService::class)->applyItem($item);

        $product->refresh()->load(['highlights', 'detailBlocks']);
        $this->assertSame('applied', $item->fresh()->status);
        $this->assertStringContainsString('Bài viết đầy đủ', $product->description);
        $this->assertSame("CPU: Intel Core i5-6440HQ\nRAM: 8GB DDR4", $product->specifications_text);
        $this->assertSame(['Core i5 4 nhân', 'SSD 256GB'], $product->highlights->pluck('title')->all());
        $this->assertSame(['benchmark_cards', 'feature_cards', 'use_case_cards'], $product->detailBlocks->sortBy('sort_order')->pluck('type')->values()->all());
        $this->assertTrue($product->detailBlocks->every(fn ($block) => $block->payload['managed_by'] === 'ai-product-content-campaign'));
    }

    public function test_publish_campaign_auto_applies_without_touching_existing_specs_highlights_or_hand_blocks(): void
    {
        $product = $this->product(['specifications_text' => 'CPU: Core i7 (nhập tay)']);
        $product->highlights()->create(['title' => 'Điểm nổi bật tự viết', 'sort_order' => 0, 'is_active' => true]);
        $product->detailBlocks()->create(['type' => 'feature_cards', 'title' => 'Tự làm', 'payload' => ['cards' => [['title' => 'Thủ công']]], 'sort_order' => 0, 'is_active' => true]);
        $item = $this->campaign('publish', self::SECTIONS, $product)->items()->first();

        (new ProcessAiProductContentCampaignItem($item->id))->handle(app(ProductContentCampaignService::class));

        $product->refresh()->load(['highlights', 'detailBlocks']);
        $this->assertSame('applied', $item->fresh()->status);
        $this->assertSame([], $item->fresh()->generated_payload['ai_specifications']);
        $this->assertSame('CPU: Core i7 (nhập tay)', $product->specifications_text);
        $this->assertSame(['Điểm nổi bật tự viết'], $product->highlights->pluck('title')->all());
        $this->assertSame(1, $product->detailBlocks->where('type', 'feature_cards')->count());
        $this->assertSame('Tự làm', $product->detailBlocks->firstWhere('type', 'feature_cards')->title);
        $this->assertSame(1, $product->detailBlocks->where('type', 'benchmark_cards')->count());
        // The product already had specifications, so they were not requested.
        Http::assertSent(fn ($request) => ! str_contains($request['messages'][1]['content'], '"specifications"'));
    }

    public function test_rerunning_a_campaign_replaces_its_own_blocks_without_duplicates(): void
    {
        $product = $this->product(['specifications_text' => 'CPU: Core i5']);
        $service = app(ProductContentCampaignService::class);

        foreach ([1, 2] as $run) {
            $item = $this->campaign('publish', ['blocks'], $product)->items()->first();
            (new ProcessAiProductContentCampaignItem($item->id))->handle($service);
            $this->assertSame('applied', $item->fresh()->status, "run {$run}");
        }

        $this->assertSame(3, $product->detailBlocks()->count());
        $this->assertNull($product->fresh()->description, 'Description was not a selected section.');
    }

    public function test_campaigns_without_sections_keep_description_only_behaviour(): void
    {
        $product = $this->product();
        $campaign = $this->campaign('publish', null, $product);
        $this->assertSame(['description'], $campaign->writtenSections());
        $item = $campaign->items()->first();

        (new ProcessAiProductContentCampaignItem($item->id))->handle(app(ProductContentCampaignService::class));

        $product->refresh();
        $this->assertSame('applied', $item->fresh()->status);
        $this->assertStringContainsString('Bài viết đầy đủ', $product->description);
        $this->assertNull($product->specifications_text);
        $this->assertSame(0, $product->highlights()->count());
        $this->assertSame(0, $product->detailBlocks()->count());
    }

    public function test_store_defaults_to_all_sections_and_rejects_unknown_ones(): void
    {
        $product = $this->product();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'ai-product-content.create', 'guard_name' => 'web']);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $admin->givePermissionTo($permission);
        $payload = [
            'name' => 'Campaign', 'selected_product_ids' => [$product->id], 'mode' => 'draft',
            'technical_heading' => 'auto', 'scheduled_at' => now()->addMinute()->toISOString(),
        ];

        $this->actingAs($admin)->postJson('/admin/ai-product-campaigns', $payload + ['sections' => ['prices']])
            ->assertStatus(422);
        $this->actingAs($admin)->postJson('/admin/ai-product-campaigns', $payload)->assertCreated();

        $this->assertSame(self::SECTIONS, AiProductContentCampaign::query()->firstOrFail()->sections);
    }

    private function campaign(string $mode, ?array $sections, Product $product): AiProductContentCampaign
    {
        $service = app(ProductContentCampaignService::class);
        $campaign = AiProductContentCampaign::create([
            'name' => 'Viết hàng loạt',
            'mode' => $mode,
            'technical_heading' => 'specifications',
            'include_product_images' => false,
            'sections' => $sections,
            'max_items' => 1,
            'status' => 'pending',
            'scheduled_at' => now(),
        ]);
        $campaign->items()->create([
            'product_id' => $product->id,
            'source_snapshot' => $service->snapshot($product->fresh()->load('specifications.specificationKey')),
            'status' => 'pending',
        ]);

        return $campaign;
    }

    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'laptop'], ['name' => 'Laptop', 'is_active' => true, 'show_on_pc_website' => true]);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Dell Latitude 5480 i5-6440HQ/Ram 8GB/SSD 256GB/14" FHD',
            'slug' => 'dell-latitude-5480-'.uniqid(),
            'sku' => 'DL-'.uniqid(),
            'price' => 5_000_000,
            'stock_quantity' => 1,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
