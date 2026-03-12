<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ComponentTypeSeeder::class,
            SpecificationKeySeeder::class,
            CompatibilityRuleSeeder::class,
            BrandSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            MenuSeeder::class,
            PostCategorySeeder::class,
            PostSeeder::class,
            SettingSeeder::class,
            ImageSeeder::class,
        ]);
    }
}
