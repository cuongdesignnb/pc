<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hộp thư liên hệ (admin inbox for the /lien-he form). The permissions go to
 * super-admin, admin and every role that can already see orders or edit posts,
 * so the shop staff find the inbox right after deploy.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['contact-messages.view', 'contact-messages.manage'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        Role::query()->where('guard_name', 'web')->with('permissions')->get()
            ->filter(fn (Role $role): bool => in_array($role->name, ['super-admin', 'admin'], true)
                || $role->permissions->whereIn('name', ['orders.view', 'posts.edit'])->isNotEmpty())
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->all()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get()
            ->each(fn (Permission $permission) => $permission->delete());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
