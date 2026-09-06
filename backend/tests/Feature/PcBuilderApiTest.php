<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BuildPreset;
use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\SpecificationKey;
use App\Services\PcBuilder\PcBuilderService;
use Database\Seeders\BuildPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PcBuilderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_returns_server_totals_completion_and_safe_products(): void
    {
        $catalog = $this->catalog();

        $response = $this->postJson('/api/v1/builder/check', [
            'build' => $catalog['build'],
        ])->assertOk()
            ->assertJsonPath('compatible', true)
            ->assertJsonPath('completion.selected', 7)
            ->assertJsonPath('completion.required_selected', 6)
            ->assertJsonPath('completion.required_total', 6)
            ->assertJsonPath('completion.complete', true)
            ->assertJsonPath('totals.price', 870)
            ->assertJsonPath('totals.tdp', 285)
            ->assertJsonPath('totals.recommended_psu_wattage', 550)
            ->assertJsonStructure([
                'compatible',
                'completion' => ['selected', 'required_selected', 'required_total', 'complete'],
                'issues',
                'totals' => ['price', 'tdp', 'recommended_psu_wattage'],
                'products',
            ]);

        $product = collect($response->json('products'))->firstWhere('id', $catalog['products']['vga']->id);
        $this->assertNotNull($product);
        $this->assertSame(300, $product['pricing']['display_price']);
        $this->assertArrayNotHasKey('cost_price', $product);
        $this->assertArrayNotHasKey('provider', $product);
        $this->assertArrayNotHasKey('stock_quantity', $product);
    }

    public function test_compatibility_is_evaluated_on_the_server_before_pagination(): void
    {
        $catalog = $this->catalog();
        $matching = $catalog['products']['mainboard'];
        $wrong = $this->product($catalog['types']['mainboard'], 'mainboard-wrong', 210, [
            'socket' => 'AM5',
            'memory_type' => 'DDR5',
        ]);
        $build = [
            (string) $catalog['types']['cpu']->id => $catalog['products']['cpu']->id,
        ];

        $this->postJson('/api/v1/builder/compatible/mainboard', [
            'build' => $build,
            'filters' => ['only_compatible' => true, 'per_page' => 1],
        ])->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('products.0.product.id', $matching->id)
            ->assertJsonPath('products.0.is_compatible', true);

        $this->postJson('/api/v1/builder/compatible/mainboard', [
            'build' => $build,
            'filters' => ['only_compatible' => false, 'sort' => 'price_asc'],
        ])->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('products.0.product.id', $matching->id)
            ->assertJsonPath('products.1.product.id', $wrong->id)
            ->assertJsonPath('products.1.is_compatible', false)
            ->assertJsonPath('products.1.issues.0.source_type_id', $catalog['types']['cpu']->id)
            ->assertJsonPath('products.1.issues.0.target_type_id', $catalog['types']['mainboard']->id);
    }

    public function test_save_recalculates_totals_and_rejects_incomplete_builds(): void
    {
        $catalog = $this->catalog();
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/builder/save', [
                'name' => 'Incomplete build',
                'build' => [
                    (string) $catalog['types']['cpu']->id => $catalog['products']['cpu']->id,
                ],
            ])->assertStatus(422)
            ->assertJsonPath('issues.0.code', 'incomplete_build');

        $savedResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/builder/save', [
                'name' => 'Valid build',
                'build' => $catalog['build'],
                'total_price' => 1,
                'total_tdp' => 1,
            ]);
        $savedResponse->assertCreated()
            ->assertJsonPath('build.name', 'Valid build')
            ->assertJsonPath('build.total_price', 870)
            ->assertJsonPath('build.total_tdp', 285)
            ->assertJsonPath('build.build.'.(string) $catalog['types']['cpu']->id, $catalog['products']['cpu']->id);
    }

    public function test_presets_are_returned_from_the_preset_table(): void
    {
        $preset = \App\Models\BuildPreset::create([
            'name' => 'PC Gaming',
            'slug' => 'pc-gaming',
            'description' => 'Cấu hình gaming.',
            'products' => ['cpu' => 10, 'vga' => 20],
            'starting_price' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/v1/builder/presets')
            ->assertOk()
            ->assertJsonPath('presets.0.id', $preset->id)
            ->assertJsonPath('presets.0.products.cpu', 10)
            ->assertJsonPath('presets.0.products.vga', 20);
    }

    public function test_preset_seeder_uses_sellable_catalog_products_and_valid_builds(): void
    {
        $catalog = $this->catalog();

        (new BuildPresetSeeder)->run();

        $this->assertCount(4, BuildPreset::query()->get());
        $builder = app(PcBuilderService::class);

        foreach (BuildPreset::query()->get() as $preset) {
            $build = collect($preset->products)->mapWithKeys(function ($productId, $typeSlug) use ($catalog): array {
                $type = $catalog['types'][$typeSlug] ?? null;

                return $type ? [(string) $type->id => (int) $productId] : [];
            })->all();
            $check = $builder->checkBuild($build);

            $this->assertTrue($check['completion']['complete'], $preset->slug);
            $this->assertFalse(collect($check['issues'])->contains(fn (array $issue) => $issue['type'] === 'error'), $preset->slug);
            $this->assertSame($check['totals']['price'], (int) $preset->starting_price, $preset->slug);
        }
    }

    /** @return array{types: array<string, ComponentType>, products: array<string, Product>, build: array<string, int>} */
    private function catalog(): array
    {
        $typeData = [
            'cpu' => ['name' => 'CPU', 'required' => true],
            'mainboard' => ['name' => 'Mainboard', 'required' => true],
            'ram' => ['name' => 'RAM', 'required' => true],
            'vga' => ['name' => 'VGA', 'required' => false],
            'ssd' => ['name' => 'SSD', 'required' => true],
            'psu' => ['name' => 'Nguồn', 'required' => true],
            'case' => ['name' => 'Case', 'required' => true],
        ];
        $types = [];
        foreach ($typeData as $slug => $data) {
            $types[$slug] = ComponentType::create([
                'name' => $data['name'],
                'slug' => $slug,
                'display_order' => count($types) + 1,
                'is_required' => $data['required'],
            ]);
        }

        $specKeys = [
            'cpu' => ['socket', 'memory_type', 'tdp'],
            'mainboard' => ['socket', 'memory_type'],
            'ram' => ['memory_type'],
            'vga' => ['tdp'],
            'psu' => ['wattage'],
        ];
        foreach ($specKeys as $slug => $keys) {
            foreach ($keys as $index => $key) {
                SpecificationKey::create([
                    'component_type_id' => $types[$slug]->id,
                    'key' => $key,
                    'label' => ucfirst(str_replace('_', ' ', $key)),
                    'data_type' => 'string',
                    'is_filterable' => true,
                    'display_order' => $index + 1,
                ]);
            }
        }

        CompatibilityRule::create([
            'source_type_id' => $types['cpu']->id,
            'target_type_id' => $types['mainboard']->id,
            'source_spec_key' => 'socket',
            'target_spec_key' => 'socket',
            'rule_type' => 'must_match',
            'message' => 'Socket CPU phải khớp với mainboard.',
            'is_active' => true,
        ]);
        CompatibilityRule::create([
            'source_type_id' => $types['cpu']->id,
            'target_type_id' => $types['mainboard']->id,
            'source_spec_key' => 'memory_type',
            'target_spec_key' => 'memory_type',
            'rule_type' => 'must_match',
            'message' => 'Loại RAM phải khớp.',
            'is_active' => true,
        ]);
        CompatibilityRule::create([
            'source_type_id' => $types['mainboard']->id,
            'target_type_id' => $types['ram']->id,
            'source_spec_key' => 'memory_type',
            'target_spec_key' => 'memory_type',
            'rule_type' => 'must_match',
            'message' => 'RAM phải khớp.',
            'is_active' => true,
        ]);
        CompatibilityRule::create([
            'source_type_id' => $types['vga']->id,
            'target_type_id' => $types['psu']->id,
            'source_spec_key' => 'tdp',
            'target_spec_key' => 'wattage',
            'rule_type' => 'power_check',
            'power_headroom' => 150,
            'message' => 'Nguồn phải đủ công suất.',
            'is_active' => true,
        ]);

        $products = [
            'cpu' => $this->product($types['cpu'], 'cpu', 100, ['socket' => 'LGA1700', 'memory_type' => 'DDR5', 'tdp' => '65'], 90),
            'mainboard' => $this->product($types['mainboard'], 'mainboard', 200, ['socket' => 'LGA1700', 'memory_type' => 'DDR5']),
            'ram' => $this->product($types['ram'], 'ram', 50, ['memory_type' => 'DDR5']),
            'vga' => $this->product($types['vga'], 'vga', 300, ['tdp' => '220']),
            'ssd' => $this->product($types['ssd'], 'ssd', 50),
            'psu' => $this->product($types['psu'], 'psu', 100, ['wattage' => '650']),
            'case' => $this->product($types['case'], 'case', 80),
        ];

        $build = [];
        foreach ($products as $slug => $product) {
            $build[(string) $types[$slug]->id] = $product->id;
        }

        return compact('types', 'products', 'build');
    }

    /** @param array<string, string> $specifications */
    private function product(ComponentType $type, string $slug, int $price, array $specifications = [], ?int $salePrice = null): Product
    {
        $brand = Brand::firstOrCreate(
            ['slug' => 'brand-'.$type->slug],
            ['name' => 'Brand '.$type->name, 'is_active' => true],
        );
        $product = Product::create([
            'brand_id' => $brand->id,
            'component_type_id' => $type->id,
            'name' => 'Product '.$slug,
            'slug' => $slug.'-'.Str::lower(Str::random(8)),
            'sku' => strtoupper($slug).'-'.Str::upper(Str::random(8)),
            'price' => $price,
            'sale_price' => $salePrice,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ]);

        foreach ($specifications as $key => $value) {
            $specificationKey = SpecificationKey::where('component_type_id', $type->id)->where('key', $key)->first();
            if ($specificationKey) {
                ProductSpecification::create([
                    'product_id' => $product->id,
                    'specification_key_id' => $specificationKey->id,
                    'value_string' => $value,
                ]);
            }
        }

        return $product;
    }
}
