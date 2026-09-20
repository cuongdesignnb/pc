<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISSIONS = [
        'ai-product-content.view',
        'ai-product-content.create',
        'ai-product-content.apply',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $record = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            foreach (['super-admin', 'admin'] as $roleName) {
                $role = Role::where(['name' => $roleName, 'guard_name' => 'web'])->first();
                if ($role && ! $role->hasPermissionTo($record)) {
                    $role->givePermissionTo($record);
                }
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
