<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Define all permissions ─────────────────────────────────────
        $permissions = [
            // Dashboard
            'dashboard.view',

            // Products
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'products.import',
            'products.export',

            // Categories
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            // Brands
            'brands.view',
            'brands.create',
            'brands.edit',
            'brands.delete',

            // Filters
            'filters.view',
            'filters.create',
            'filters.edit',
            'filters.delete',

            // Component Types
            'component-types.view',
            'component-types.create',
            'component-types.edit',
            'component-types.delete',

            // Compatibility
            'compatibility.view',
            'compatibility.create',
            'compatibility.edit',
            'compatibility.delete',

            // Orders
            'orders.view',
            'orders.edit',
            'orders.delete',

            // Coupons
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',

            // Posts
            'posts.view',
            'posts.create',
            'posts.edit',
            'posts.delete',
            'posts.import',
            'posts.export',

            // Post Categories
            'post-categories.view',
            'post-categories.create',
            'post-categories.edit',
            'post-categories.delete',

            // AI Articles
            'ai-articles.view',
            'ai-articles.create',
            'ai-articles.delete',

            // Pages
            'pages.view',
            'pages.create',
            'pages.edit',
            'pages.delete',

            // Banners
            'banners.view',
            'banners.create',
            'banners.edit',
            'banners.delete',

            // Media
            'media.view',
            'media.upload',
            'media.delete',

            // Customers
            'customers.view',

            // Reviews
            'reviews.view',
            'reviews.edit',
            'reviews.delete',

            // Menus
            'menus.view',
            'menus.create',
            'menus.edit',
            'menus.delete',

            // Settings
            'settings.view',
            'settings.edit',

            // Users (admin management)
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // ─── Create roles ───────────────────────────────────────────────

        // Super-admin: full access (bypasses permission checks)
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web']
        );
        $superAdmin->syncPermissions(Permission::all());

        // Admin: full access to content + orders, no user/role management
        $admin = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );
        $adminPermissions = Permission::whereNotIn('name', [
            'users.create', 'users.edit', 'users.delete',
            'roles.create', 'roles.edit', 'roles.delete',
        ])->get();
        $admin->syncPermissions($adminPermissions);

        // Staff: limited access - view & edit products, orders, posts
        $staff = Role::firstOrCreate(
            ['name' => 'staff', 'guard_name' => 'web']
        );
        $staffPermissions = [
            'dashboard.view',
            'products.view', 'products.create', 'products.edit',
            'categories.view',
            'brands.view',
            'filters.view',
            'orders.view', 'orders.edit',
            'posts.view', 'posts.create', 'posts.edit',
            'post-categories.view',
            'pages.view',
            'banners.view',
            'media.view', 'media.upload',
            'customers.view',
            'reviews.view', 'reviews.edit',
            'menus.view',
        ];
        $staff->syncPermissions(
            Permission::whereIn('name', $staffPermissions)->get()
        );

        // ─── Assign super-admin to existing admin user ──────────────────
        $adminUser = \App\Models\User::where('email', 'admin@pcshop.vn')->first();
        if ($adminUser) {
            $adminUser->syncRoles($superAdmin);
        }

        $staffUser = \App\Models\User::where('email', 'staff@pcshop.vn')->first();
        if ($staffUser) {
            $staffUser->syncRoles($staff);
        }
    }
}
