<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\SavedBuild;
use App\Models\User;
use App\Services\Locations\LocationDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_scoped_to_the_authenticated_user_and_is_not_cached(): void
    {
        $owner = User::factory()->create([
            'name' => 'Nguyễn Văn A',
            'email' => 'owner@example.test',
            'phone' => '0901234567',
        ]);
        $other = User::factory()->create(['email' => 'other@example.test']);

        $this->order($owner, 'DH-OWNER-1', 250000, 'delivered');
        $this->order($other, 'DH-OTHER-1', 999000, 'delivered');

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/account/dashboard')
            ->assertOk();

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response
            ->assertJsonPath('user.email', 'owner@example.test')
            ->assertJsonPath('stats.orders', 1)
            ->assertJsonPath('stats.total_spent', 250000)
            ->assertJsonCount(1, 'recent_orders')
            ->assertJsonPath('recent_orders.0.order_number', 'DH-OWNER-1')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('recent_orders.1');
    }

    public function test_account_addresses_store_location_codes_and_cannot_be_accessed_by_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $locations = app(LocationDirectory::class);
        $province = $locations->provinces()[0];
        $ward = $locations->wards($province['code'])[0];

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/account/addresses', [
            'label' => 'Nhà riêng',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'province_code' => $province['code'],
            'ward_code' => $ward['code'],
            'street' => '123 Đường Công Nghệ',
            'is_default' => true,
        ])->assertCreated()
            ->assertJsonPath('address.province_code', $province['code'])
            ->assertJsonPath('address.ward_code', $ward['code'])
            ->assertJsonPath('address.full_address', '123 Đường Công Nghệ, '.$ward['fullname'].', '.$province['fullname']);

        $address = Address::query()->findOrFail($response->json('address.id'));
        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $owner->id,
            'province_code' => $province['code'],
            'ward_code' => $ward['code'],
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/account/addresses')
            ->assertOk()
            ->assertJsonCount(0, 'addresses');
        $this->patchJson('/api/v1/account/addresses/'.$address->id, [
            'full_name' => 'Không được sửa',
            'phone' => '0900000000',
            'province_code' => $province['code'],
            'ward_code' => $ward['code'],
            'street' => 'Địa chỉ khác',
        ])->assertNotFound();
        $this->deleteJson('/api/v1/account/addresses/'.$address->id)->assertNotFound();
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'full_name' => 'Nguyễn Văn A']);
    }

    public function test_saved_build_detail_is_private_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $build = SavedBuild::create([
            'user_id' => $owner->id,
            'name' => 'PC Gaming đã lưu',
            'products' => ['1' => 10],
            'total_price' => 100,
            'total_tdp' => 65,
        ]);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/builder/saved/'.$build->id)->assertNotFound();
        $this->deleteJson('/api/v1/builder/saved/'.$build->id)->assertNotFound();
        $this->assertDatabaseHas('saved_builds', ['id' => $build->id]);
    }

    public function test_profile_update_returns_safe_profile_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/profile', [
            'name' => 'Nguyễn Văn B',
            'phone' => '0901234567',
            'date_of_birth' => '1998-12-28',
            'gender' => 'prefer_not_to_say',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Nguyễn Văn B')
            ->assertJsonPath('user.date_of_birth', '1998-12-28')
            ->assertJsonPath('user.gender', 'prefer_not_to_say')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['user' => ['id', 'email', 'created_at']]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nguyễn Văn B',
            'gender' => 'prefer_not_to_say',
        ]);
    }

    public function test_warranty_uses_order_item_snapshot_and_does_not_invent_expiry(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Warranty product',
            'slug' => 'warranty-product-'.Str::lower(Str::random(8)),
            'sku' => 'WARRANTY-'.Str::upper(Str::random(8)),
            'price' => 1000,
            'stock_quantity' => 2,
            'is_active' => true,
            'inventory_source' => 'local',
            'warranty_months' => 36,
        ]);
        $knownOrder = $this->order($user, 'DH-WARRANTY-KNOWN', 1000, 'delivered');
        $knownOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
            'warranty_months' => 6,
        ]);
        $unknownOrder = $this->order($user, 'DH-WARRANTY-UNKNOWN', 1000, 'delivered');
        $unknownOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name.' without snapshot',
            'sku' => $product->sku,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000,
            'warranty_months' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/account/warranties')->assertOk();
        $warranties = collect($response->json('warranties'));
        $known = $warranties->firstWhere('order_number', 'DH-WARRANTY-KNOWN');
        $unknown = $warranties->firstWhere('order_number', 'DH-WARRANTY-UNKNOWN');

        $this->assertSame(6, $known['warranty_months']);
        $this->assertSame('active', $known['status']);
        $this->assertNotNull($known['expires_at']);
        $this->assertNull($unknown['warranty_months']);
        $this->assertSame('unknown', $unknown['status']);
        $this->assertNull($unknown['expires_at']);
    }

    private function order(User $user, string $number, int $total, string $status): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'subtotal' => $total,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => $total,
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'order_status' => $status,
            'shipping_name' => $user->name,
            'shipping_phone' => $user->phone ?: '0900000000',
            'customer_email' => $user->email,
            'shipping_address' => '123 Đường Công Nghệ',
            'delivered_at' => $status === 'delivered' ? now()->subDay() : null,
        ]);
    }
}
