<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ComponentType;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Main categories
        $pc = Category::create([
            'name' => 'PC - Máy tính để bàn',
            'slug' => 'pc-may-tinh-de-ban',
            'description' => 'Máy tính để bàn các loại',
            'is_active' => true,
        ]);

        $laptop = Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop',
            'description' => 'Laptop các loại',
            'is_active' => true,
        ]);

        $components = Category::create([
            'name' => 'Linh kiện PC',
            'slug' => 'linh-kien-pc',
            'description' => 'Linh kiện máy tính để bàn',
            'is_active' => true,
        ]);

        $peripherals = Category::create([
            'name' => 'Phụ kiện',
            'slug' => 'phu-kien',
            'description' => 'Phụ kiện máy tính',
            'is_active' => true,
        ]);

        // PC subcategories
        Category::create([
            'name' => 'PC Gaming',
            'slug' => 'pc-gaming',
            'parent_id' => $pc->id,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'PC Văn phòng',
            'slug' => 'pc-van-phong',
            'parent_id' => $pc->id,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'PC Đồ họa - Render',
            'slug' => 'pc-do-hoa-render',
            'parent_id' => $pc->id,
            'is_active' => true,
        ]);

        // Laptop subcategories
        Category::create([
            'name' => 'Laptop Gaming',
            'slug' => 'laptop-gaming',
            'parent_id' => $laptop->id,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Laptop Văn phòng',
            'slug' => 'laptop-van-phong',
            'parent_id' => $laptop->id,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Laptop Đồ họa',
            'slug' => 'laptop-do-hoa',
            'parent_id' => $laptop->id,
            'is_active' => true,
        ]);

        // Component subcategories - linked to component types
        $componentTypes = ComponentType::all();
        foreach ($componentTypes as $type) {
            Category::create([
                'name' => $type->name,
                'slug' => $type->slug,
                'parent_id' => $components->id,
                'component_type_id' => $type->id,
                'is_active' => true,
            ]);
        }

        // Peripherals subcategories
        $peripheralItems = [
            ['name' => 'Màn hình', 'slug' => 'man-hinh'],
            ['name' => 'Bàn phím', 'slug' => 'ban-phim'],
            ['name' => 'Chuột', 'slug' => 'chuot'],
            ['name' => 'Tai nghe', 'slug' => 'tai-nghe'],
            ['name' => 'Loa', 'slug' => 'loa'],
            ['name' => 'Webcam', 'slug' => 'webcam'],
            ['name' => 'Bàn Gaming', 'slug' => 'ban-gaming'],
            ['name' => 'Ghế Gaming', 'slug' => 'ghe-gaming'],
        ];

        foreach ($peripheralItems as $item) {
            Category::create([
                'name' => $item['name'],
                'slug' => $item['slug'],
                'parent_id' => $peripherals->id,
                'is_active' => true,
            ]);
        }
    }
}
