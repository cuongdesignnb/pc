<?php

namespace Tests\Feature;

use App\Exceptions\KiotIntegrationException;
use App\Models\Category;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncState;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Integrations\Kiot\KiotProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KiotProductConsumerV2Test extends TestCase
{
    use RefreshDatabase;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=');
        config()->set('integrations.kiot.image_allowed_hosts', ['kiot.example.test']);
        config()->set('integrations.kiot.image_max_bytes', 1024 * 1024);
        config()->set('integrations.kiot.sync_lock_seconds', 60);
        $this->connection();
    }

    public function test_full_sync_creates_categories_products_selected_price_and_mirrored_images_idempotently(): void
    {
        Storage::fake('public');
        $product = $this->canonicalProduct();
        $this->fakeProvider([$this->category()], [$product]);

        $first = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(1, $first['category_create']);
        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $first['images_downloaded']);
        $category = Category::where(['provider' => 'kiot', 'remote_category_id' => 12])->firstOrFail();
        $local = Product::where(['provider' => 'kiot', 'remote_product_id' => 123])->firstOrFail();
        $this->assertSame($category->id, $local->category_id);
        $this->assertSame('9900000', $local->price);
        $this->assertSame('9900000', $local->kiot_selected_price);
        $this->assertTrue($local->isSellableOnline());
        $image = ProductImage::where(['provider' => 'kiot', 'remote_image_id' => 90])->firstOrFail();
        $this->assertStringNotContainsString('kiot.example.test', $image->url);
        Storage::disk('public')->assertExists($image->storage_path);

        $second = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, $second['image_skips']);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_images', 1);
    }

    public function test_dry_run_persists_only_the_report_and_never_downloads_or_advances_cursor(): void
    {
        Storage::fake('public');
        $imageRequests = 0;
        $this->fakeProvider([$this->category()], [$this->canonicalProduct()], $imageRequests);

        $report = app(KiotProductSyncService::class)->sync(dryRun: true, full: true);

        $this->assertSame(1, $report['total_remote']);
        $this->assertSame(1, $report['category_create']);
        $this->assertSame(1, $report['create_candidates']);
        $this->assertSame(0, $report['update_candidates']);
        $this->assertSame(0, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertCount(1, $report['preview']);
        $this->assertSame('create', $report['preview'][0]['action']);
        $this->assertSame(0, $report['errors']);
        $this->assertSame(1, $report['image_downloads']);
        $this->assertSame(0, $imageRequests);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('integration_sync_states', 0);
        $this->assertDatabaseHas('integration_sync_runs', ['id' => $report['run_id'], 'status' => 'completed']);
    }

    public function test_empty_local_v2_dry_run_classifies_all_eligible_products_as_create_candidates(): void
    {
        $products = [
            $this->canonicalProduct(['id' => 201, 'sku' => 'CREATE-1'], []),
            $this->canonicalProduct(['id' => 202, 'sku' => 'CREATE-2'], []),
        ];
        $this->fakeProvider([$this->category()], $products);

        $report = app(KiotProductSyncService::class)->sync(dryRun: true, full: true);

        $this->assertSame(2, $report['total_remote']);
        $this->assertSame($report['total_remote'], $report['create_candidates']);
        $this->assertSame(0, $report['update_candidates']);
        $this->assertSame(2, $report['remote_unmatched_count']);
        $this->assertCount(2, $report['preview']);
        $this->assertSame(['create'], collect($report['preview'])->pluck('action')->unique()->values()->all());
        $this->assertSame(0, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(0, $report['errors']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_unmapped_exact_sku_records_a_conflict_without_overwriting_or_creating(): void
    {
        $local = $this->localProduct(['sku' => 'SP26070143809', 'name' => 'Website-owned name', 'price' => 1234]);
        $this->fakeProvider([$this->category()], [$this->canonicalProduct(images: [])]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(1, $report['conflicts']);
        $this->assertSame('Website-owned name', $local->fresh()->name);
        $this->assertSame('1234', $local->fresh()->price);
        $this->assertNull($local->fresh()->remote_product_id);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('integration_sync_conflicts', [
            'remote_id' => 123,
            'sku' => 'SP26070143809',
            'local_product_id' => $local->id,
            'status' => 'open',
        ]);
    }

    public function test_hidden_repairing_deleted_and_zero_price_products_are_fail_closed_in_public_cart_and_checkout(): void
    {
        $visible = $this->category();
        $hidden = $this->category(['id' => 13, 'code' => 'INTERNAL', 'name' => 'Internal', 'slug' => 'internal', 'show_on_pc_website' => false]);
        $repairing = $this->canonicalProduct([
            'id' => 124,
            'sku' => 'REPAIRING-1',
            'name' => 'Repairing product',
            'inventory' => ['stock_quantity' => 1, 'reserved_quantity' => 0, 'available_quantity' => 0, 'status' => 'repairing'],
            'availability' => ['is_available' => false, 'is_under_repair' => true, 'sell_directly' => true],
        ], []);
        $hiddenProduct = $this->canonicalProduct([
            'id' => 125,
            'sku' => 'HIDDEN-1',
            'name' => 'Hidden product',
            'category' => ['id' => 13, 'code' => 'INTERNAL', 'name' => 'Internal', 'slug' => 'internal', 'parent_id' => null, 'show_on_pc_website' => false],
            'publishing' => ['show_on_pc_website' => false, 'blocked_reason' => 'CATEGORY_NOT_PUBLISHED'],
        ], []);
        $deleted = $this->canonicalProduct([
            'id' => 126,
            'sku' => 'DELETED-1',
            'name' => 'Deleted product',
            'is_active' => false,
            'sync_status' => 'deleted',
            'publishing' => ['show_on_pc_website' => false, 'blocked_reason' => 'PRODUCT_DELETED'],
            'inventory' => ['stock_quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'status' => 'deleted'],
            'availability' => ['is_available' => false, 'is_under_repair' => false, 'sell_directly' => false],
        ], []);
        $zeroPrice = $this->canonicalProduct([
            'id' => 127,
            'sku' => 'CONTACT-1',
            'name' => 'Contact product',
            'pricing' => ['base_price' => 0, 'retail_price' => 0, 'selected_price' => 0, 'selected_price_book_id' => 3, 'selected_price_book_code' => 'WEBSITE', 'selected_price_book_name' => 'Giá Website', 'fallback_used' => true],
        ], []);
        $this->fakeProvider([$visible, $hidden], [$repairing, $hiddenProduct, $deleted, $zeroPrice]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $repairingLocal = Product::where('remote_product_id', 124)->firstOrFail();
        $this->assertFalse($repairingLocal->isSellableOnline());
        $this->assertSame('Đang sửa chữa', $repairingLocal->availability_label);
        $this->getJson('/api/v1/products/'.$repairingLocal->slug)
            ->assertOk()
            ->assertJsonPath('product.is_purchasable', false)
            ->assertJsonPath('product.availability_label', 'Đang sửa chữa');
        $this->withHeader('X-Cart-Session', 'repairing-cart')->postJson('/api/v1/cart/items', [
            'product_id' => $repairingLocal->id,
            'quantity' => 1,
        ])->assertUnprocessable();
        $this->postJson('/api/v1/orders', $this->checkoutPayload($repairingLocal->id))->assertUnprocessable();

        $listingIds = collect($this->getJson('/api/v1/products?per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($listingIds->contains($repairingLocal->id));
        $this->assertFalse($listingIds->contains(Product::where('remote_product_id', 125)->value('id')));
        $this->assertFalse($listingIds->contains(Product::where('remote_product_id', 126)->value('id')));
        $contact = Product::where('remote_product_id', 127)->firstOrFail();
        $this->assertFalse($contact->isSellableOnline());
        $this->assertSame('Liên hệ', $contact->availability_label);
        $this->assertSame(1, $report['price_fallback']);
    }

    public function test_invalid_image_mime_is_a_warning_and_does_not_abort_product_sync(): void
    {
        Storage::fake('public');
        $body = 'not-an-image';
        $product = $this->canonicalProduct(images: [[
            'id' => 90,
            'url' => 'https://kiot.example.test/storage/products/invalid.webp',
            'checksum' => hash('sha256', $body),
            'width' => 1,
            'height' => 1,
            'sort_order' => 0,
            'is_primary' => true,
        ]]);
        $this->fakeProvider([$this->category()], [$product], imageBody: $body, imageMime: 'application/octet-stream');

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertDatabaseHas('products', ['remote_product_id' => 123]);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame('completed_with_warnings', $report['status']);
        $this->assertContains('IMAGE_MIME_INVALID', collect($report['warning_details'])->pluck('code'));
    }

    public function test_incremental_sync_uses_overlap_and_full_sync_lock_fails_closed(): void
    {
        IntegrationSyncState::create([
            'integration' => 'kiot',
            'resource' => 'products',
            'status' => 'completed',
            'last_successful_watermark' => '2026-07-25 05:00:00',
        ]);
        $this->fakeProvider([], []);

        app(KiotProductSyncService::class)->sync(dryRun: false, full: false);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/products')
            && ($request->data()['updated_since'] ?? null) === '2026-07-25T04:58:00+00:00');

        $lock = Cache::lock('integrations:kiot:product-sync', 60);
        $this->assertTrue($lock->get());
        try {
            app(KiotProductSyncService::class)->sync(dryRun: true, full: true);
            $this->fail('Expected the distributed lock to reject the second run.');
        } catch (KiotIntegrationException $exception) {
            $this->assertSame('SYNC_ALREADY_RUNNING', $exception->errorCode);
        } finally {
            $lock->release();
        }
        $this->assertDatabaseHas('integration_sync_runs', ['status' => 'failed', 'error_code' => 'SYNC_ALREADY_RUNNING']);
    }

    public function test_category_remote_id_survives_rename_parent_change_and_tombstone(): void
    {
        Category::create([
            'provider' => 'kiot',
            'remote_category_id' => 20,
            'name' => 'Root',
            'slug' => 'root',
            'is_active' => true,
            'show_on_pc_website' => true,
            'provider_sync_status' => 'active',
        ]);
        Category::create([
            'provider' => 'kiot',
            'remote_category_id' => 21,
            'name' => 'Old name',
            'slug' => 'old-name',
            'is_active' => true,
            'show_on_pc_website' => true,
            'provider_sync_status' => 'active',
        ]);

        $this->fakeProvider([$this->category(['id' => 20, 'code' => 'ROOT', 'name' => 'Root', 'slug' => 'root']), $this->category([
            'id' => 21,
            'code' => 'CHILD',
            'name' => 'New name',
            'slug' => 'new-name',
            'parent_id' => 20,
            'is_active' => false,
            'show_on_pc_website' => false,
            'sync_status' => 'deleted',
        ])], []);
        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $parentLocal = Category::where(['provider' => 'kiot', 'remote_category_id' => 20])->firstOrFail();
        $childLocal = Category::where(['provider' => 'kiot', 'remote_category_id' => 21])->firstOrFail();
        $this->assertSame('New name', $childLocal->name);
        $this->assertSame($parentLocal->id, $childLocal->parent_id);
        $this->assertFalse($childLocal->is_active);
        $this->assertFalse($childLocal->show_on_pc_website);
        $this->assertSame('deleted', $childLocal->provider_sync_status);
        $this->assertSame(2, $report['category_update']);
        $this->assertSame(1, $report['category_hidden']);
        $this->assertDatabaseCount('categories', 2);
    }

    public function test_product_pagination_is_consumed_and_recorded_in_sync_history(): void
    {
        $first = $this->canonicalProduct(['id' => 201, 'sku' => 'PAGE-1'], []);
        $second = $this->canonicalProduct(['id' => 202, 'sku' => 'PAGE-2'], []);
        Http::fake(function (Request $request) use ($first, $second) {
            if (str_contains($request->url(), '/categories')) {
                return Http::response($this->page([$this->category()]), 200);
            }
            if (str_contains($request->url(), '/price-books')) {
                return Http::response($this->page([]), 200);
            }
            if (str_contains($request->url(), '/products')) {
                return ($request->data()['cursor'] ?? null) === 'page-two'
                    ? Http::response($this->page([$second]), 200)
                    : Http::response([
                        'success' => true,
                        'data' => [$first],
                        'meta' => [
                            'next_cursor' => 'page-two',
                            'has_more' => true,
                            'dataset_complete' => false,
                            'deletion_policy' => 'explicit_tombstone_only',
                            'missing_products_are_deleted' => false,
                        ],
                    ], 200);
            }

            return Http::response(['success' => false], 500);
        });

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(2, $report['created']);
        $this->assertSame(4, $report['pages_processed']);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('integration_sync_runs', [
            'id' => $report['run_id'],
            'status' => 'completed',
            'pages_processed' => 4,
            'remote_processed' => 2,
            'created' => 2,
        ]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/products')
            && ($request->data()['cursor'] ?? null) === 'page-two');
    }

    public function test_missing_mapped_and_local_only_products_are_preserved_without_zeroing_stock(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 998,
            'kiot_product_id' => 998,
            'inventory_source' => 'kiot',
            'stock_quantity' => 9,
            'show_on_pc_website' => true,
            'kiot_sellable' => true,
        ]);
        $localOnly = $this->localProduct(['stock_quantity' => 7, 'show_on_pc_website' => true]);
        $this->fakeProvider([$this->category()], [$this->canonicalProduct(images: [])]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(1, $report['missing_preserved']);
        $this->assertSame(1, $report['local_only_skipped']);
        $this->assertSame(9, $mapped->fresh()->stock_quantity);
        $this->assertTrue($mapped->fresh()->show_on_pc_website);
        $this->assertTrue($mapped->fresh()->kiot_sellable);
        $this->assertSame(7, $localOnly->fresh()->stock_quantity);
        $this->assertTrue($localOnly->fresh()->show_on_pc_website);
    }

    public function test_explicit_tombstone_archives_without_soft_delete_or_zeroing_business_stock(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 126,
            'kiot_product_id' => 126,
            'inventory_source' => 'kiot',
            'stock_quantity' => 7,
            'show_on_pc_website' => true,
            'kiot_sellable' => true,
        ]);
        $tombstone = $this->canonicalProduct([
            'id' => 126,
            'sku' => $mapped->sku,
            'is_active' => false,
            'sync_status' => 'deleted',
            'publishing' => ['show_on_pc_website' => false, 'blocked_reason' => 'PRODUCT_DELETED'],
            'inventory' => ['stock_quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'status' => 'deleted'],
            'availability' => ['is_available' => false, 'is_under_repair' => false, 'sell_directly' => false],
        ], []);
        $this->fakeProvider([$this->category()], [$tombstone]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $fresh = $mapped->fresh();
        $this->assertSame(1, $report['remote_tombstones']);
        $this->assertSame(1, $report['archived']);
        $this->assertSame(0, $report['deleted']);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame(7, $fresh->stock_quantity);
        $this->assertFalse($fresh->is_active);
        $this->assertFalse($fresh->show_on_pc_website);
        $this->assertFalse($fresh->kiot_sellable);
    }

    public function test_incomplete_dataset_skips_finalization_and_preserves_missing_products(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 998,
            'kiot_product_id' => 998,
            'inventory_source' => 'kiot',
            'stock_quantity' => 9,
            'show_on_pc_website' => true,
        ]);
        $this->fakeProvider([$this->category()], [], productMeta: ['dataset_complete' => false]);

        $report = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertFalse($report['dataset_complete']);
        $this->assertSame(1, $report['finalization_skipped']);
        $this->assertSame(9, $mapped->fresh()->stock_quantity);
        $this->assertTrue($mapped->fresh()->show_on_pc_website);
    }

    public function test_repeated_product_cursor_is_blocked_before_products_are_changed(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 123,
            'kiot_product_id' => 123,
            'inventory_source' => 'kiot',
            'name' => 'Before repeated cursor',
        ]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/categories') || str_contains($request->url(), '/price-books')) {
                return Http::response($this->page([]), 200);
            }
            if (str_contains($request->url(), '/products')) {
                return Http::response([
                    'success' => true,
                    'data' => [$this->canonicalProduct(['name' => 'Must not be applied'], [])],
                    'meta' => [
                        'next_cursor' => 'same-cursor',
                        'has_more' => true,
                        'dataset_complete' => false,
                        'deletion_policy' => 'explicit_tombstone_only',
                        'missing_products_are_deleted' => false,
                    ],
                ], 200);
            }

            return Http::response(['success' => false], 500);
        });

        try {
            app(KiotProductSyncService::class)->sync(dryRun: false, full: true);
            $this->fail('Expected repeated cursor protection to abort the sync.');
        } catch (KiotIntegrationException $exception) {
            $this->assertSame('PRODUCT_PAGINATION_CURSOR_REPEATED', $exception->errorCode);
        }

        $this->assertSame('Before repeated cursor', $mapped->fresh()->name);
    }

    public function test_abnormal_empty_dataset_triggers_safety_guard(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 126,
            'kiot_product_id' => 126,
            'inventory_source' => 'kiot',
            'stock_quantity' => 7,
            'show_on_pc_website' => true,
            'kiot_sellable' => true,
        ]);
        $this->fakeProvider([$this->category()], []);

        $emptyReport = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(1, $emptyReport['safety_blocked']);
        $this->assertSame(1, $emptyReport['finalization_skipped']);
        $this->assertSame(7, $mapped->fresh()->stock_quantity);
    }

    public function test_tombstone_threshold_triggers_safety_guard_before_archive(): void
    {
        $mapped = $this->localProduct([
            'provider' => 'kiot',
            'remote_product_id' => 126,
            'kiot_product_id' => 126,
            'inventory_source' => 'kiot',
            'stock_quantity' => 7,
            'show_on_pc_website' => true,
            'kiot_sellable' => true,
        ]);

        config()->set('integrations.kiot.product_sync_max_tombstones', 0);
        $tombstone = $this->canonicalProduct([
            'id' => 126,
            'sku' => $mapped->sku,
            'is_active' => false,
            'sync_status' => 'deleted',
        ], []);
        $this->fakeProvider([$this->category()], [$tombstone]);

        $tombstoneReport = app(KiotProductSyncService::class)->sync(dryRun: false, full: true);

        $this->assertSame(1, $tombstoneReport['remote_tombstones']);
        $this->assertSame(1, $tombstoneReport['safety_blocked']);
        $this->assertSame(0, $tombstoneReport['archived']);
        $this->assertTrue($mapped->fresh()->show_on_pc_website);
        $this->assertSame(7, $mapped->fresh()->stock_quantity);
    }

    private function connection(): IntegrationConnection
    {
        return IntegrationConnection::create([
            'provider' => 'kiot',
            'configuration_source' => 'manual',
            'base_url' => 'https://kiot.test',
            'client_id' => 'pc-website',
            'secret_encrypted' => 'test-secret',
            'secret_fingerprint' => substr(hash('sha256', 'test-secret'), 0, 16),
            'api_version' => 'v1',
            'connection_status' => 'connected',
            'is_enabled' => true,
            'product_sync_enabled' => true,
            'order_sync_enabled' => true,
            'capabilities' => [
                'products' => true,
                'orders' => true,
                'categories' => true,
                'product_images' => true,
                'price_books' => true,
                'repair_status' => true,
            ],
        ]);
    }

    private function canonicalProduct(array $overrides = [], ?array $images = null): array
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/Kiot/product-provider-v2.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['data'][0];
        $images ??= [[
            'id' => 90,
            'url' => 'https://kiot.example.test/storage/products/example.png',
            'checksum' => hash('sha256', $this->png),
            'width' => 1,
            'height' => 1,
            'sort_order' => 0,
            'is_primary' => true,
        ]];
        $fixture = array_replace_recursive($fixture, $overrides);
        $fixture['images'] = $images;
        $fixture['primary_image'] = $images[0] ?? null;

        return $fixture;
    }

    private function category(array $overrides = []): array
    {
        return array_replace([
            'id' => 12,
            'code' => 'LAPTOP-DELL',
            'name' => 'Laptop Dell',
            'slug' => 'laptop-dell',
            'parent_id' => null,
            'is_active' => true,
            'show_on_pc_website' => true,
            'sync_status' => 'active',
            'updated_at' => '2026-07-25T05:00:00Z',
        ], $overrides);
    }

    private function fakeProvider(
        array $categories,
        array $products,
        ?int &$imageRequests = null,
        ?string $imageBody = null,
        string $imageMime = 'image/png',
        array $productMeta = [],
    ): void {
        $imageBody ??= $this->png;
        Http::fake(function (Request $request) use ($categories, $products, &$imageRequests, $imageBody, $imageMime, $productMeta) {
            $url = $request->url();
            if (str_contains($url, '/categories')) {
                return Http::response($this->page($categories), 200);
            }
            if (str_contains($url, '/price-books')) {
                return Http::response($this->page([[
                    'id' => 3,
                    'code' => 'WEBSITE',
                    'name' => 'Giá Website',
                    'is_default' => false,
                    'is_active' => true,
                    'sync_status' => 'active',
                    'updated_at' => '2026-07-25T05:00:00Z',
                ]]), 200);
            }
            if (str_contains($url, 'kiot.example.test/storage/')) {
                if ($imageRequests !== null) {
                    $imageRequests++;
                }

                return Http::response($imageBody, 200, ['Content-Type' => $imageMime]);
            }
            if (str_contains($url, '/products')) {
                return Http::response($this->page($products, $productMeta), 200);
            }

            return Http::response(['success' => false, 'error' => ['code' => 'UNEXPECTED_REQUEST', 'message' => $url]], 500);
        });
    }

    private function page(array $items, array $meta = []): array
    {
        return [
            'success' => true,
            'data' => $items,
            'meta' => array_replace([
                'next_cursor' => null,
                'has_more' => false,
                'dataset_complete' => true,
                'deletion_policy' => 'explicit_tombstone_only',
                'missing_products_are_deleted' => false,
            ], $meta),
        ];
    }

    private function localProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Local product',
            'slug' => 'local-'.Str::lower(Str::random(8)),
            'sku' => 'LOCAL-'.Str::upper(Str::random(8)),
            'price' => 1000,
            'stock_quantity' => 5,
            'is_active' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }

    private function checkoutPayload(int $productId): array
    {
        return [
            'checkout_idempotency_key' => (string) Str::uuid(),
            'order_access_token' => (string) Str::uuid(),
            'customer_name' => 'Nguyễn Văn A',
            'customer_email' => 'customer@example.com',
            'customer_phone' => '0987654321',
            'shipping_address' => '123 Đường ABC',
            'shipping_city' => 'TP. Hồ Chí Minh',
            'payment_method' => 'cod',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ];
    }
}
