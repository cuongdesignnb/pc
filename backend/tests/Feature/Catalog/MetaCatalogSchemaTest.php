<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\CatalogChannelException;
use App\Models\Brand;
use App\Models\CatalogChannelConnection;
use App\Models\CatalogChannelSyncRunItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\SpecificationKey;
use App\Services\Catalog\CatalogChannelManager;
use App\Services\Catalog\CatalogProductProjectionService;
use App\Services\Catalog\Meta\MetaCatalogCsvRenderer;
use App\Services\Catalog\Meta\MetaCatalogFeedBuilder;
use App\Services\Catalog\Meta\MetaCatalogFeedValidator;
use App\Services\Catalog\Meta\MetaCatalogItem;
use App\Services\Catalog\Meta\MetaCatalogItemStrategy;
use App\Services\Catalog\Meta\MetaCatalogItemValidator;
use App\Services\Catalog\Meta\MetaCatalogSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaCatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('m', 32)),
            'seo.site_origin' => 'https://laptopplus.test',
            'catalog.storefront_url' => 'https://laptopplus.test',
            'catalog.feed_disk' => 'local',
            'catalog.feed_directory' => 'meta-feed-tests',
            'catalog.meta_catalog.artifact' => 'meta-products.csv',
            'catalog.meta_catalog.google_product_category_map' => [],
            'catalog.meta_catalog.fb_product_category_map' => [],
            'catalog.meta_catalog.unbranded_skus' => [],
            'catalog.meta_catalog.unbranded_label' => 'Unbranded',
            'catalog.meta_catalog.derive_brand_from_title' => true,
            'catalog.meta_catalog.brand_aliases' => [
                'ASUS' => ['asus', 'expertbook'],
                'Dell' => ['dell', 'latitude'],
                'HP' => ['hp'],
            ],
            'catalog.meta_catalog.derive_description_from_catalog_facts' => true,
            'catalog.meta_catalog.use_confirmed_barcodes_as_gtin' => false,
            'catalog.meta_catalog.shipping' => '',
        ]);

        Storage::fake('local');
    }

    public function test_generated_feed_has_exact_meta_schema_and_every_row_has_31_columns(): void
    {
        $category = $this->category();
        $product = $this->product($category, 101, [
            'name' => 'Laptop Đồ họa, bản "Creator"',
            'description' => "Hiệu năng cao cho thiết kế, dựng phim và người dùng Việt Nam.\nBảo hành chính hãng.",
        ]);
        $this->image($product);

        $summary = app(MetaCatalogFeedBuilder::class)->build();
        $rows = $this->csvRows('meta-feed-tests/meta-products.csv');

        $this->assertSame(MetaCatalogSchema::HEADERS, $rows[0]);
        $this->assertCount(31, $rows[0]);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertCount(31, $row);
        }
        foreach (['inventory', 'product_type', 'custom_label_0', 'custom_label_1', 'status', 'additional_image_link'] as $alias) {
            $this->assertNotContains($alias, $rows[0]);
        }

        $item = array_combine($rows[0], $rows[1]);
        $this->assertSame('kiot:101', $item['id']);
        $this->assertSame('Laptop Đồ họa, bản "Creator"', $item['title']);
        $this->assertSame('Thương hiệu Việt', $item['brand']);
        $this->assertSame('10000000 VND', $item['price']);
        $this->assertSame('2', $item['quantity_to_sell_on_facebook']);
        $this->assertSame('', $item['google_product_category']);
        $this->assertSame('', $item['fb_product_category']);
        $this->assertTrue(mb_check_encoding(Storage::disk('local')->get('meta-feed-tests/meta-products.csv'), 'UTF-8'));

        $this->assertSame(1, $summary['TOTAL_PRODUCTS']);
        $this->assertSame(1, $summary['TOTAL_ROWS']);
        $this->assertSame(1, $summary['VALID_ROWS']);
        $this->assertSame(0, $summary['INVALID_ROWS']);
        $this->assertDatabaseHas('catalog_channel_sync_run_items', [
            'sync_run_id' => $summary['run_id'],
            'product_id' => $product->id,
            'eligibility_status' => 'eligible',
            'result_status' => 'built',
        ]);
    }

    public function test_renderer_round_trips_commas_quotes_newlines_and_vietnamese_with_fgetcsv(): void
    {
        $values = $this->validValues([
            'title' => 'Laptop Việt Nam, bản "Đặc biệt"',
            'description' => "Dòng thứ nhất, có dấu phẩy.\nDòng thứ hai có \"ngoặc kép\".",
        ]);
        $path = Storage::disk('local')->path('escaped-meta.csv');

        app(MetaCatalogCsvRenderer::class)->render([
            new MetaCatalogItem(1, $values),
        ], $path);

        $rows = $this->csvRows('escaped-meta.csv');
        $this->assertSame(MetaCatalogSchema::HEADERS, $rows[0]);
        $this->assertCount(31, $rows[1]);
        $this->assertSame($values['title'], $rows[1][1]);
        $this->assertSame($values['description'], $rows[1][2]);
        $this->assertSame([], app(MetaCatalogItemValidator::class)->errorsForValues(array_combine($rows[0], $rows[1])));
    }

    public function test_row_validator_reports_each_required_and_structured_field_error_code(): void
    {
        $validator = app(MetaCatalogItemValidator::class);
        $this->assertSame([], $validator->errorsForValues($this->validValues()));

        $cases = [
            ['id', ' ', 'ID_MISSING'],
            ['title', "\t", 'TITLE_MISSING'],
            ['description', '<p> </p>', 'DESCRIPTION_MISSING'],
            ['availability', 'available', 'AVAILABILITY_INVALID'],
            ['condition', 'old', 'CONDITION_INVALID'],
            ['link', 'http://example.com/item', 'PRODUCT_URL_MISSING'],
            ['image_link', 'javascript:alert(1)', 'IMAGE_MISSING'],
            ['brand', '', 'BRAND_MISSING'],
            ['price', '', 'PRICE_MISSING'],
            ['price', '0 VND', 'PRICE_INVALID'],
            ['price', '10000000', 'PRICE_INVALID'],
            ['quantity_to_sell_on_facebook', '-1', 'QUANTITY_INVALID'],
            ['google_product_category', 'Laptop nội bộ', 'CATEGORY_INVALID'],
            ['fb_product_category', '0', 'CATEGORY_INVALID'],
        ];

        foreach ($cases as [$field, $invalidValue, $expectedCode]) {
            $errors = $validator->errorsForValues($this->validValues([$field => $invalidValue]));
            $this->assertContains($expectedCode, $errors, "{$field} must report {$expectedCode}");
        }
    }

    public function test_validator_rejects_duplicate_id_between_product_and_variant_rows(): void
    {
        $path = Storage::disk('local')->path('duplicate-meta.csv');
        $handle = fopen($path, 'wb');
        fputcsv($handle, MetaCatalogSchema::HEADERS, ',', '"', '');
        fputcsv($handle, $this->orderedRow($this->validValues(['id' => 'kiot:55'])), ',', '"', '');
        fputcsv($handle, $this->orderedRow($this->validValues([
            'id' => 'kiot:55',
            'item_group_id' => 'kiot:55',
            'title' => 'Biến thể màu xanh',
        ])), ',', '"', '');
        fclose($handle);

        try {
            app(MetaCatalogFeedValidator::class)->validate($path);
            $this->fail('Duplicate product/variant IDs must fail validation.');
        } catch (CatalogChannelException $exception) {
            $this->assertSame('FEED_INVALID_CSV', $exception->errorCode);
            $this->assertStringContainsString('DUPLICATE_ID', $exception->getMessage());
            $this->assertStringNotContainsString('kiot:55', $exception->getMessage());
        }
    }

    public function test_variant_rows_map_inventory_group_attributes_taxonomy_and_sale_period(): void
    {
        $category = $this->category();
        config([
            'catalog.meta_catalog.google_product_category_map' => [$category->id => '328'],
            'catalog.meta_catalog.fb_product_category_map' => [$category->id => '1234'],
        ]);
        $product = $this->product($category, 202, ['weight' => 1750]);
        $this->image($product);
        $salePeriod = SpecificationKey::create([
            'key' => 'sale_price_effective_date',
            'label' => 'Thời gian khuyến mại',
            'data_type' => 'string',
        ]);
        $product->specifications()->create([
            'specification_key_id' => $salePeriod->id,
            'value_string' => '2026-09-01/2026-09-30',
        ]);
        $blue = $product->variants()->create([
            'name' => 'Xanh / 16GB',
            'attributes' => ['Màu sắc' => 'Xanh', 'size' => '16GB'],
            'sku' => 'SKU-202-BLUE',
            'price' => 12000000,
            'sale_price' => 11000000,
            'stock_quantity' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $black = $product->variants()->create([
            'name' => 'Đen / 32GB',
            'attributes' => ['color' => 'Đen', 'Kích thước' => '32GB'],
            'sku' => 'SKU-202-BLACK',
            'price' => 15000000,
            'sale_price' => null,
            'stock_quantity' => 0,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $summary = app(MetaCatalogFeedBuilder::class)->build();
        $items = $this->keyedItems('meta-feed-tests/meta-products.csv');

        $this->assertCount(2, $items);
        $this->assertSame('kiot:202:variant:'.$blue->id, $items[0]['id']);
        $this->assertSame('kiot:202:variant:'.$black->id, $items[1]['id']);
        $this->assertSame('kiot:202', $items[0]['item_group_id']);
        $this->assertSame('kiot:202', $items[1]['item_group_id']);
        $this->assertSame('7', $items[0]['quantity_to_sell_on_facebook']);
        $this->assertSame('in stock', $items[0]['availability']);
        $this->assertSame('0', $items[1]['quantity_to_sell_on_facebook']);
        $this->assertSame('out of stock', $items[1]['availability']);
        $this->assertSame('12000000 VND', $items[0]['price']);
        $this->assertSame('11000000 VND', $items[0]['sale_price']);
        $this->assertSame('2026-09-01/2026-09-30', $items[0]['sale_price_effective_date']);
        $this->assertSame('', $items[1]['sale_price']);
        $this->assertSame('', $items[1]['sale_price_effective_date']);
        $this->assertSame('Xanh', $items[0]['color']);
        $this->assertSame('16GB', $items[0]['size']);
        $this->assertSame('328', $items[0]['google_product_category']);
        $this->assertSame('1234', $items[0]['fb_product_category']);
        $this->assertSame('1750 g', $items[0]['shipping_weight']);
        $this->assertSame(2, $summary['TOTAL_ROWS']);
        $this->assertSame(2, $summary['VALID_ROWS']);
        $this->assertSame(1, $summary['PRODUCTS_WITH_VARIANTS']);
        $this->assertSame(1, $summary['PRODUCTS_WITH_ITEM_GROUP_ID']);
        $this->assertSame(0, $summary['PRODUCTS_WITHOUT_SELLABLE_INVENTORY']);
    }

    public function test_internal_category_is_not_emitted_as_meta_taxonomy_without_reviewed_mapping(): void
    {
        $category = $this->category();
        $product = $this->product($category, 303);
        $this->image($product);
        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);

        $unmapped = $strategy->items($projection)[0];
        $this->assertSame('', $unmapped->value('google_product_category'));
        $this->assertSame('', $unmapped->value('fb_product_category'));

        config(['catalog.meta_catalog.google_product_category_map' => [
            $category->id => $projection->categoryPath,
        ]]);
        $invalid = $strategy->items($projection)[0];
        $this->assertContains('CATEGORY_INVALID', $strategy->validate($invalid, $projection)->errors);

        config(['catalog.meta_catalog.google_product_category_map' => [$category->id => '328']]);
        $mapped = $strategy->items($projection)[0];
        $this->assertSame('328', $mapped->value('google_product_category'));
        $this->assertNotContains('CATEGORY_INVALID', $strategy->validate($mapped, $projection)->errors);
    }

    public function test_unbranded_label_requires_explicit_sku_approval(): void
    {
        $category = $this->category();
        $product = $this->product($category, 404, ['brand_id' => null]);
        $this->image($product);
        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);

        $unapproved = $strategy->items($projection)[0];
        $this->assertSame('', $unapproved->value('brand'));
        $this->assertContains('BRAND_MISSING', $strategy->validate($unapproved, $projection)->errors);

        config(['catalog.meta_catalog.unbranded_skus' => [$product->sku]]);
        $approved = $strategy->items($projection)[0];
        $this->assertSame('Unbranded', $approved->value('brand'));
        $this->assertNotContains('BRAND_MISSING', $strategy->validate($approved, $projection)->errors);
    }

    public function test_meta_derives_reviewed_brand_and_factual_description_without_mutating_catalog_data(): void
    {
        $category = $this->category();
        $product = $this->product($category, 405, [
            'brand_id' => null,
            'name' => 'ASUS ExpertBook B1 Intel Core i5',
            'description' => null,
            'short_description' => null,
        ]);
        $this->image($product);

        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);
        $item = $strategy->items($projection)[0];
        $validation = $strategy->validate($item, $projection);

        $this->assertSame('ASUS', $item->value('brand'));
        $this->assertSame(
            'ASUS ExpertBook B1 Intel Core i5. Danh mục: Laptop nội bộ. Mã sản phẩm: SKU-405.',
            $item->value('description'),
        );
        $this->assertNotContains('BRAND_MISSING', $validation->errors);
        $this->assertNotContains('DESCRIPTION_MISSING', $validation->errors);
        $this->assertContains('BRAND_DERIVED_FROM_TITLE', $validation->warnings);
        $this->assertContains('DESCRIPTION_DERIVED_FROM_CATALOG_FACTS', $validation->warnings);
        $this->assertNull($product->fresh()->brand_id);
        $this->assertNull($product->fresh()->description);

        $summary = app(MetaCatalogFeedBuilder::class)->build();
        $this->assertSame(1, $summary['WARNINGS_BY_CODE']['BRAND_DERIVED_FROM_TITLE']);
        $this->assertSame(1, $summary['WARNINGS_BY_CODE']['DESCRIPTION_DERIVED_FROM_CATALOG_FACTS']);
    }

    public function test_meta_brand_derivation_uses_whole_words_and_keeps_unknown_brand_invalid(): void
    {
        $category = $this->category();
        $product = $this->product($category, 406, [
            'brand_id' => null,
            'name' => 'GraphPro Notebook 14',
            'description' => null,
            'short_description' => null,
        ]);
        $this->image($product);

        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);
        $item = $strategy->items($projection)[0];
        $validation = $strategy->validate($item, $projection);

        $this->assertSame('', $item->value('brand'));
        $this->assertContains('BRAND_MISSING', $validation->errors);
        $this->assertNotContains('DESCRIPTION_MISSING', $validation->errors);
    }

    public function test_meta_catalog_source_derivation_can_be_disabled(): void
    {
        config([
            'catalog.meta_catalog.derive_brand_from_title' => false,
            'catalog.meta_catalog.derive_description_from_catalog_facts' => false,
        ]);
        $category = $this->category();
        $product = $this->product($category, 407, [
            'brand_id' => null,
            'name' => 'Dell Latitude 5450',
            'description' => null,
            'short_description' => null,
        ]);
        $this->image($product);

        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);
        $item = $strategy->items($projection)[0];
        $validation = $strategy->validate($item, $projection);

        $this->assertSame('', $item->value('brand'));
        $this->assertSame('', $item->value('description'));
        $this->assertContains('BRAND_MISSING', $validation->errors);
        $this->assertContains('DESCRIPTION_MISSING', $validation->errors);
    }

    public function test_existing_brand_and_description_take_precedence_over_derived_values(): void
    {
        $category = $this->category();
        $product = $this->product($category, 408, [
            'name' => 'Dell Latitude custom build',
            'description' => '<p>Mô tả biên tập đã được duyệt.</p>',
        ]);
        $this->image($product);

        $projection = app(CatalogProductProjectionService::class)->project($product);
        $strategy = app(MetaCatalogItemStrategy::class);
        $item = $strategy->items($projection)[0];
        $validation = $strategy->validate($item, $projection);

        $this->assertSame('Thương hiệu Việt', $item->value('brand'));
        $this->assertSame('Mô tả biên tập đã được duyệt.', $item->value('description'));
        $this->assertNotContains('BRAND_DERIVED_FROM_TITLE', $validation->warnings);
        $this->assertNotContains('DESCRIPTION_DERIVED_FROM_CATALOG_FACTS', $validation->warnings);
    }

    public function test_legacy_null_stock_quantity_is_normalized_to_zero(): void
    {
        $product = new Product;
        $product->setRawAttributes(['stock_quantity' => null]);

        $this->assertSame(0, $product->quantity);
    }

    public function test_build_summary_and_run_items_explain_invalid_products_without_double_counting_products(): void
    {
        config(['catalog.meta_catalog.derive_description_from_catalog_facts' => false]);
        $category = $this->category();
        $valid = $this->product($category, 501);
        $this->image($valid);

        $incomplete = $this->product($category, 502, [
            'remote_product_id' => null,
            'sku' => ' ',
            'brand_id' => null,
            'description' => null,
            'short_description' => null,
            'price' => 0,
            'kiot_retail_price' => 0,
            'kiot_sellable' => false,
            'kiot_available_quantity' => 9,
        ]);

        $reservedCategory = $this->category(2, ['name' => 'API', 'slug' => 'api']);
        $missingUrl = $this->product($reservedCategory, 503);
        $this->image($missingUrl);

        $summary = app(MetaCatalogFeedBuilder::class)->build();

        $this->assertSame(3, $summary['TOTAL_PRODUCTS']);
        $this->assertSame(3, $summary['TOTAL_ROWS']);
        $this->assertSame(1, $summary['VALID_PRODUCTS']);
        $this->assertSame(2, $summary['INVALID_PRODUCTS']);
        $this->assertSame(1, $summary['VALID_ROWS']);
        $this->assertSame(2, $summary['INVALID_ROWS']);
        $this->assertSame(1, $summary['PRODUCTS_MISSING_BRAND']);
        $this->assertSame(1, $summary['PRODUCTS_MISSING_DESCRIPTION']);
        $this->assertSame(1, $summary['PRODUCTS_MISSING_PRICE']);
        $this->assertSame(1, $summary['PRODUCTS_MISSING_URL']);
        $this->assertSame(1, $summary['PRODUCTS_MISSING_IMAGE']);
        $this->assertSame(1, $summary['PRODUCTS_WITHOUT_SELLABLE_INVENTORY']);
        $this->assertDatabaseHas('catalog_channel_sync_runs', [
            'id' => $summary['run_id'],
            'items_total' => 3,
            'items_valid' => 1,
            'items_invalid' => 2,
        ]);

        $runItem = CatalogChannelSyncRunItem::query()
            ->where('sync_run_id', $summary['run_id'])
            ->where('product_id', $incomplete->id)
            ->firstOrFail();
        $this->assertSame('invalid', $runItem->eligibility_status);
        $this->assertContains('BRAND_MISSING', $runItem->validation_errors_json);
        $this->assertContains('ID_MISSING', $runItem->validation_errors_json);
        $this->assertContains('DESCRIPTION_MISSING', $runItem->validation_errors_json);
        $this->assertContains('PRICE_MISSING', $runItem->validation_errors_json);
        $this->assertContains('IMAGE_MISSING', $runItem->validation_errors_json);
        $this->assertSame('evaluated', $runItem->result_status);
    }

    public function test_meta_endpoint_requires_token_returns_csv_and_never_exposes_raw_token(): void
    {
        $category = $this->category();
        $product = $this->product($category, 601);
        $this->image($product);
        $summary = app(MetaCatalogFeedBuilder::class)->build();

        $connection = CatalogChannelConnection::query()
            ->where('channel', CatalogChannelConnection::META_CATALOG)
            ->firstOrFail();
        $connection->update(['is_enabled' => true]);
        $token = app(CatalogChannelManager::class)->rotateFeedToken($connection);
        $logs = [];
        Log::listen(function ($event) use (&$logs): void {
            $logs[] = $event->message.' '.json_encode($event->context, JSON_UNESCAPED_SLASHES);
        });

        $this->get('/feeds/meta/products.csv')->assertNotFound();
        $wrong = $this->get('/feeds/meta/products.csv?token=wrong-'.$token)->assertNotFound();
        $response = $this->get('/feeds/meta/products.csv?token='.$token)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $body = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertIsString($body);
        $this->assertStringContainsString(implode(',', MetaCatalogSchema::HEADERS), $body);
        $this->assertStringNotContainsString($token, $body);
        $this->assertStringNotContainsString($token, $wrong->getContent());
        $this->assertStringNotContainsString($token, implode("\n", $logs));
        $this->assertNotSame($token, data_get($connection->fresh()->configuration_encrypted, 'feed_token_hash'));
        $this->assertStringNotContainsString($token, json_encode($summary, JSON_THROW_ON_ERROR));
    }

    public function test_feed_validator_rejects_invalid_utf8(): void
    {
        $path = Storage::disk('local')->path('invalid-utf8.csv');
        file_put_contents($path, implode(',', MetaCatalogSchema::HEADERS)."\n\xC3\x28");

        $this->expectException(CatalogChannelException::class);
        $this->expectExceptionMessage('UTF-8');
        app(MetaCatalogFeedValidator::class)->validate($path);
    }

    private function category(int $remoteId = 1, array $overrides = []): Category
    {
        return Category::create($overrides + [
            'provider' => 'kiot',
            'remote_category_id' => $remoteId,
            'name' => 'Laptop nội bộ',
            'slug' => 'laptop-noi-bo-'.$remoteId,
            'is_active' => true,
            'show_on_pc_website' => true,
            'provider_sync_status' => 'active',
        ]);
    }

    private function product(Category $category, int $remoteId, array $overrides = []): Product
    {
        return Product::create($overrides + [
            'category_id' => $category->id,
            'brand_id' => $this->brand()->id,
            'provider' => 'kiot',
            'remote_product_id' => $remoteId,
            'name' => 'Laptop '.$remoteId,
            'slug' => 'laptop-'.$remoteId,
            'sku' => 'SKU-'.$remoteId,
            'description' => 'Mô tả sản phẩm chính hãng dành cho khách hàng Việt Nam.',
            'price' => 10000000,
            'stock_quantity' => 2,
            'inventory_source' => 'kiot',
            'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available',
            'kiot_sellable' => true,
            'kiot_available_quantity' => 2,
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);
    }

    private function brand(): Brand
    {
        return Brand::firstOrCreate(
            ['slug' => 'thuong-hieu-viet'],
            ['name' => 'Thương hiệu Việt', 'is_active' => true],
        );
    }

    private function image(Product $product, string $url = 'https://cdn.laptopplus.test/product.jpg'): void
    {
        $product->images()->create(['url' => $url, 'is_primary' => true]);
    }

    /** @return array<string, string> */
    private function validValues(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'kiot:1',
            'title' => 'Laptop chính hãng',
            'description' => 'Mô tả sản phẩm đầy đủ cho người mua.',
            'availability' => 'in stock',
            'condition' => 'new',
            'link' => 'https://laptopplus.test/laptop/laptop-1',
            'image_link' => 'https://cdn.laptopplus.test/laptop-1.jpg',
            'brand' => 'Thương hiệu Việt',
            'price' => '10000000 VND',
            'quantity_to_sell_on_facebook' => '2',
        ];
    }

    /** @return list<string> */
    private function orderedRow(array $values): array
    {
        return array_map(
            fn (string $header): string => (string) ($values[$header] ?? ''),
            MetaCatalogSchema::HEADERS,
        );
    }

    /** @return list<list<string>> */
    private function csvRows(string $artifact): array
    {
        $handle = fopen(Storage::disk('local')->path($artifact), 'rb');
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<array<string, string>> */
    private function keyedItems(string $artifact): array
    {
        $rows = $this->csvRows($artifact);
        $headers = array_shift($rows);

        return array_map(fn (array $row): array => array_combine($headers, $row), $rows);
    }
}
