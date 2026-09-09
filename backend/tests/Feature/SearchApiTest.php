<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_effective_prices_and_contact_state(): void
    {
        $category = Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop-'.Str::lower(Str::random(8)),
            'is_active' => true,
            'show_on_pc_website' => true,
        ]);

        $zeroSale = $this->product($category, [
            'name' => 'Latitude zero sale marker',
            'price' => 12000000,
            'sale_price' => 0,
        ]);
        $contact = $this->product($category, [
            'name' => 'Latitude contact price',
            'price' => 0,
            'sale_price' => null,
        ]);
        $kiot = $this->product($category, [
            'name' => 'Dell Latitude 5450 Ultra 7',
            'provider' => 'kiot',
            'inventory_source' => 'kiot',
            'price' => 0,
            'sale_price' => null,
            'kiot_retail_price' => 14990000,
            'kiot_selected_price' => 0,
            'kiot_sync_status' => 'active',
            'kiot_availability_status' => 'available',
            'kiot_sellable' => true,
            'kiot_available_quantity' => 1,
            'stock_quantity' => 1,
            'remote_product_id' => 5450,
            'kiot_product_id' => 5450,
        ]);

        $items = collect($this->getJson('/api/v1/search?q=Latitude')->assertOk()->json('products'))
            ->keyBy('id');

        $this->assertSame(12000000, $items[$zeroSale->id]['price']);
        $this->assertNull($items[$zeroSale->id]['sale_price']);
        $this->assertSame(12000000, $items[$zeroSale->id]['display_price']);
        $this->assertFalse($items[$zeroSale->id]['is_contact_price']);

        $this->assertSame(0, $items[$contact->id]['display_price']);
        $this->assertTrue($items[$contact->id]['is_contact_price']);

        $this->assertSame(14990000, $items[$kiot->id]['price']);
        $this->assertSame(14990000, $items[$kiot->id]['display_price']);
        $this->assertFalse($items[$kiot->id]['is_contact_price']);
    }

    /** @param array<string, mixed> $overrides */
    private function product(Category $category, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Search product '.Str::random(8),
            'slug' => 'search-product-'.Str::lower(Str::random(12)),
            'sku' => 'SEARCH-'.Str::upper(Str::random(10)),
            'price' => 1000000,
            'sale_price' => null,
            'stock_quantity' => 10,
            'is_active' => true,
            'show_on_pc_website' => true,
            'inventory_source' => 'local',
        ], $overrides));
    }
}
