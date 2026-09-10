<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permission = Permission::firstOrCreate([
            'name' => 'seo.slugs.manage',
            'guard_name' => 'web',
        ]);

        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();
            $role?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permission = Permission::query()
            ->where('name', 'seo.slugs.manage')
            ->where('guard_name', 'web')
            ->first();
        if (! $permission) {
            return;
        }

        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();
            $role?->revokePermissionTo($permission);
        }

        $permission->delete();
    }
};
