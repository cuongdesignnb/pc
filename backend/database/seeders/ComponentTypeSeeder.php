<?php

namespace Database\Seeders;

use App\Models\ComponentType;
use Illuminate\Database\Seeder;

class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'CPU',
                'slug' => 'cpu',
                'display_order' => 1,
                'is_required' => true,
            ],
            [
                'name' => 'Mainboard',
                'slug' => 'mainboard',
                'display_order' => 2,
                'is_required' => true,
            ],
            [
                'name' => 'RAM',
                'slug' => 'ram',
                'display_order' => 3,
                'is_required' => true,
            ],
            [
                'name' => 'VGA (Card đồ họa)',
                'slug' => 'vga',
                'display_order' => 4,
                'is_required' => false,
            ],
            [
                'name' => 'SSD',
                'slug' => 'ssd',
                'display_order' => 5,
                'is_required' => true,
            ],
            [
                'name' => 'HDD',
                'slug' => 'hdd',
                'display_order' => 6,
                'is_required' => false,
            ],
            [
                'name' => 'Nguồn (PSU)',
                'slug' => 'psu',
                'display_order' => 7,
                'is_required' => true,
            ],
            [
                'name' => 'Vỏ case',
                'slug' => 'case',
                'display_order' => 8,
                'is_required' => true,
            ],
            [
                'name' => 'Tản nhiệt CPU',
                'slug' => 'cooler',
                'display_order' => 9,
                'is_required' => false,
            ],
            [
                'name' => 'Quạt case',
                'slug' => 'fan',
                'display_order' => 10,
                'is_required' => false,
            ],
        ];

        foreach ($types as $type) {
            ComponentType::create($type);
        }
    }
}
