<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            // CPU
            ['name' => 'Intel', 'slug' => 'intel'],
            ['name' => 'AMD', 'slug' => 'amd'],
            // GPU
            ['name' => 'NVIDIA', 'slug' => 'nvidia'],
            ['name' => 'ASUS', 'slug' => 'asus'],
            ['name' => 'MSI', 'slug' => 'msi'],
            ['name' => 'Gigabyte', 'slug' => 'gigabyte'],
            ['name' => 'EVGA', 'slug' => 'evga'],
            ['name' => 'Zotac', 'slug' => 'zotac'],
            ['name' => 'Colorful', 'slug' => 'colorful'],
            ['name' => 'Galax', 'slug' => 'galax'],
            // Mainboard
            ['name' => 'ASRock', 'slug' => 'asrock'],
            // RAM
            ['name' => 'G.Skill', 'slug' => 'gskill'],
            ['name' => 'Kingston', 'slug' => 'kingston'],
            ['name' => 'Corsair', 'slug' => 'corsair'],
            ['name' => 'TeamGroup', 'slug' => 'teamgroup'],
            ['name' => 'Crucial', 'slug' => 'crucial'],
            // Storage
            ['name' => 'Samsung', 'slug' => 'samsung'],
            ['name' => 'Western Digital', 'slug' => 'western-digital'],
            ['name' => 'Seagate', 'slug' => 'seagate'],
            ['name' => 'SK Hynix', 'slug' => 'sk-hynix'],
            ['name' => 'Lexar', 'slug' => 'lexar'],
            // PSU
            ['name' => 'Seasonic', 'slug' => 'seasonic'],
            ['name' => 'NZXT', 'slug' => 'nzxt'],
            ['name' => 'Cooler Master', 'slug' => 'cooler-master'],
            ['name' => 'Thermaltake', 'slug' => 'thermaltake'],
            ['name' => 'Be Quiet!', 'slug' => 'be-quiet'],
            ['name' => 'Super Flower', 'slug' => 'super-flower'],
            // Case
            ['name' => 'Lian Li', 'slug' => 'lian-li'],
            ['name' => 'Phanteks', 'slug' => 'phanteks'],
            ['name' => 'Fractal Design', 'slug' => 'fractal-design'],
            // Cooler
            ['name' => 'Noctua', 'slug' => 'noctua'],
            ['name' => 'Arctic', 'slug' => 'arctic'],
            ['name' => 'DeepCool', 'slug' => 'deepcool'],
            ['name' => 'ID-Cooling', 'slug' => 'id-cooling'],
            // Peripherals
            ['name' => 'Logitech', 'slug' => 'logitech'],
            ['name' => 'Razer', 'slug' => 'razer'],
            ['name' => 'SteelSeries', 'slug' => 'steelseries'],
            ['name' => 'HyperX', 'slug' => 'hyperx'],
            ['name' => 'Ducky', 'slug' => 'ducky'],
            ['name' => 'Keychron', 'slug' => 'keychron'],
            // Monitor
            ['name' => 'LG', 'slug' => 'lg'],
            ['name' => 'Dell', 'slug' => 'dell'],
            ['name' => 'BenQ', 'slug' => 'benq'],
            ['name' => 'AOC', 'slug' => 'aoc'],
            ['name' => 'ViewSonic', 'slug' => 'viewsonic'],
            // Laptop
            ['name' => 'Lenovo', 'slug' => 'lenovo'],
            ['name' => 'HP', 'slug' => 'hp'],
            ['name' => 'Acer', 'slug' => 'acer'],
            ['name' => 'Apple', 'slug' => 'apple'],
        ];

        foreach ($brands as $brand) {
            Brand::create([
                'name' => $brand['name'],
                'slug' => $brand['slug'],
                'is_active' => true,
            ]);
        }
    }
}
