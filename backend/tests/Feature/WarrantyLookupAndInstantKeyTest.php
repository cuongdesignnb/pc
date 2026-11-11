<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminApi\AdminApiTokens;
use App\Services\Integrations\Kiot\KiotClient;
use App\Services\Integrations\Kiot\KiotResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class WarrantyLookupAndInstantKeyTest extends TestCase
{
    use RefreshDatabase;

    private const ROW = [
        'product_name' => 'Dell Latitude 7420', 'serial' => 'SVTAG-9XK2', 'purchase_date' => '2026-09-01',
        'warranty_months' => 12, 'warranty_end_date' => '2027-09-01', 'days_left' => 294, 'status' => 'active',
        'customer_phone' => '098****992', 'customer_name' => 'must not leak',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('warranty-lookup');
    }

    private function kiotReturns(array $rows, int $status = 200, ?array &$queries = null): void
    {
        $queries = [];
        $mock = Mockery::mock(KiotClient::class);
        $mock->shouldReceive('warranties')->andReturnUsing(function (array $query) use ($rows, $status, &$queries) {
            $queries[] = $query;

            return new KiotResponse($status, $status === 200 ? ['success' => true, 'data' => $rows] : ['success' => false]);
        });
        $this->app->instance(KiotClient::class, $mock);
    }

    public function test_lookup_returns_only_safe_fields_from_kiot(): void
    {
        $this->kiotReturns([self::ROW], queries: $queries);

        $this->getJson('/api/v1/warranty-lookup?serial=SVTAG-9XK2')->assertOk()
            ->assertJsonPath('data.0.product_name', 'Dell Latitude 7420')
            ->assertJsonPath('data.0.customer_phone', '098****992')
            ->assertJsonMissingPath('data.0.customer_name')
            ->assertJsonPath('captcha_required', false);

        $this->getJson('/api/v1/warranty-lookup?phone=0987654992&serial_last4=9XK2')->assertOk();
        $this->assertSame([['serial' => 'SVTAG-9XK2'], ['phone' => '0987654992', 'serial_last4' => '9XK2']], $queries);

        // A phone number alone is not enough.
        $this->getJson('/api/v1/warranty-lookup?phone=0987654992')->assertUnprocessable()->assertJsonValidationErrors('serial_last4');
    }

    public function test_five_lookups_a_minute_per_ip(): void
    {
        $this->kiotReturns([self::ROW]);
        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/warranty-lookup?serial=SVTAG-9XK2')->assertOk();
        }
        $this->getJson('/api/v1/warranty-lookup?serial=SVTAG-9XK2')->assertStatus(429);
    }

    public function test_captcha_is_asked_after_repeated_misses_when_turnstile_is_set_up(): void
    {
        config(['services.turnstile.secret_key' => 'turnstile-test-secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->kiotReturns([]);

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/warranty-lookup?serial=NOPE'.$i)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->travel(61)->seconds();
        $this->getJson('/api/v1/warranty-lookup?serial=NOPE9')->assertUnprocessable()->assertJsonPath('captcha_required', true);
        $this->getJson('/api/v1/warranty-lookup?serial=NOPE9&captcha_token=ok')->assertOk();
    }

    public function test_kiot_errors_become_a_friendly_503(): void
    {
        $this->kiotReturns([], 500);
        $this->getJson('/api/v1/warranty-lookup?serial=SVTAG-9XK2')->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_zalo_mini_app_may_call_the_lookup(): void
    {
        $this->kiotReturns([self::ROW]);
        $this->withHeader('Origin', 'https://h5.zdn.vn')->getJson('/api/v1/warranty-lookup?serial=SVTAG-9XK2')
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', 'https://h5.zdn.vn');
    }

    public function test_instant_key_url_is_set_by_admin_api_and_shown_on_the_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Phần mềm', 'slug' => 'phan-mem', 'is_active' => true, 'show_on_pc_website' => true]);
        $product = Product::create([
            'name' => 'Windows 11 Pro', 'slug' => 'windows-11-pro-'.Str::lower(Str::random(4)), 'sku' => 'WIN11PRO',
            'category_id' => $category->id, 'price' => 300_000, 'stock_quantity' => 0,
            'is_active' => true, 'show_on_pc_website' => true, 'inventory_source' => 'local',
        ]);
        $token = app(AdminApiTokens::class)->issue($admin, 'n8n', 'content')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['instant_key_url' => 'http://partner.example/key'])
            ->assertUnprocessable()->assertJsonValidationErrors('instant_key_url');
        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['instant_key_url' => 'https://partner.example/key?ref=lp'])
            ->assertOk()->assertJsonPath('data.instant_key_url', 'https://partner.example/key?ref=lp');

        $this->getJson("/api/v1/products/{$product->slug}")->assertOk()
            ->assertJsonPath('product.instant_key_url', 'https://partner.example/key?ref=lp');

        $this->withToken($token)->patchJson("/api/v1/admin-api/products/{$product->id}", ['instant_key_url' => null])->assertOk();
        $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('product.instant_key_url', null);
    }
}
