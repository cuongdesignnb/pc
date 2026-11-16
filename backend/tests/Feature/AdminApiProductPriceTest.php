<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Why a WEB-NEW price edit is refused, and prices sent as formatted text. */
class AdminApiProductPriceTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->token = app(AdminApiTokens::class)->issue($admin, 'n8n', 'content')->plainTextToken;
    }

    private function product(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Dell 15', 'slug' => 'dell-'.Str::lower(Str::random(6)), 'sku' => 'WEB-NEW-'.Str::upper(Str::random(5)),
            'price' => 15_000_000, 'stock_quantity' => 2, 'is_active' => true, 'inventory_source' => 'local',
        ]);
    }

    private function patchPrice(Product $product, array $body)
    {
        return $this->withToken($this->token)->patchJson("/api/v1/admin-api/products/{$product->id}", $body);
    }

    public function test_each_lock_reason_is_reported_and_explained(): void
    {
        $cases = [
            'sku_not_web_new' => $this->product(['sku' => 'D7420']),
            'kiot_product' => $this->product(['inventory_source' => 'kiot']),
            'has_variants' => tap($this->product(), fn (Product $p) => $p->variants()->create(['name' => '16GB', 'sku' => $p->sku.'-16', 'price' => 16_000_000, 'stock_quantity' => 1])),
        ];
        $cases['kiot_provider'] = $this->product(['provider' => 'kiot']);

        foreach ($cases as $case => $product) {
            $reason = $case === 'kiot_provider' ? 'kiot_product' : $case;
            $this->withToken($this->token)->getJson("/api/v1/admin-api/products/{$product->id}")->assertOk()
                ->assertJsonPath('data.price_editable', false)
                ->assertJsonPath('data.price_locked_reason', $reason);
            $response = $this->patchPrice($product, ['price' => 12_000_000])->assertUnprocessable()->assertJsonValidationErrors('price');
            $this->assertStringContainsString("price_locked_reason = {$reason}", $response->json('errors.price.0'));
            $this->assertSame(15_000_000, (int) $product->fresh()->price);
        }

        $editable = $this->product(['sku' => 'web-new-lower']);
        $this->withToken($this->token)->getJson("/api/v1/admin-api/products/{$editable->id}")
            ->assertJsonPath('data.price_editable', true)->assertJsonPath('data.price_locked_reason', null);
    }

    public function test_formatted_prices_are_normalized(): void
    {
        $product = $this->product();

        foreach (['12.000.000' => 12_000_000, '12,500,000' => 12_500_000, '13000000đ' => 13_000_000, '13 500 000 VNĐ' => 13_500_000, '14000000' => 14_000_000] as $input => $expected) {
            $this->patchPrice($product, ['price' => $input])->assertOk()->assertJsonPath('data.price', $expected);
        }
        $this->patchPrice($product, ['price' => '15.000.000', 'sale_price' => '13.990.000đ'])->assertOk()
            ->assertJsonPath('data.price', 15_000_000)->assertJsonPath('data.sale_price', 13_990_000);

        $this->patchPrice($product, ['sale_price' => '16.000.000'])->assertUnprocessable()->assertJsonValidationErrors('sale_price');
        $this->patchPrice($product, ['price' => '12.5 triệu'])->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->patchPrice($product, ['price' => 0])->assertUnprocessable();
    }
}
