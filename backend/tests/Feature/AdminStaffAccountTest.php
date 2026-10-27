<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_staff_account_that_can_sign_in(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole(Role::findByName('admin', 'web'));
        $staffRole = Role::where('name', '!=', 'admin')->first() ?? Role::findByName('admin', 'web');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Nhân viên bán hàng',
            'email' => 'nhanvien@laptopplus.vn',
            'phone' => '0901234567',
            'role' => 'staff',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
            'roles' => [$staffRole->id],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
        $staff = User::where('email', 'nhanvien@laptopplus.vn')->firstOrFail();
        $this->assertSame('staff', $staff->role);
        $this->assertTrue($staff->hasRole($staffRole));

        auth()->logout();
        $this->post('/admin/login', ['email' => 'nhanvien@laptopplus.vn', 'password' => 'matkhau123'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($staff->fresh());
    }
}
