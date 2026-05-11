<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Database\Seeder;

class FilterSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // 1. BO LOC GIA
        // ========================================
        $price = Filter::create([
            'name' => 'Khoang gia',
            'slug' => 'khoang-gia',
            'type' => 'price_range',
            'match_field' => 'price',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $priceRanges = [
            ['label' => 'Duoi 5 trieu', 'slug' => 'duoi-5-trieu', 'price_min' => 0, 'price_max' => 5000000],
            ['label' => '5 - 10 trieu', 'slug' => '5-10-trieu', 'price_min' => 5000000, 'price_max' => 10000000],
            ['label' => '10 - 15 trieu', 'slug' => '10-15-trieu', 'price_min' => 10000000, 'price_max' => 15000000],
            ['label' => '15 - 20 trieu', 'slug' => '15-20-trieu', 'price_min' => 15000000, 'price_max' => 20000000],
            ['label' => '20 - 30 trieu', 'slug' => '20-30-trieu', 'price_min' => 20000000, 'price_max' => 30000000],
            ['label' => '30 - 50 trieu', 'slug' => '30-50-trieu', 'price_min' => 30000000, 'price_max' => 50000000],
            ['label' => 'Tren 50 trieu', 'slug' => 'tren-50-trieu', 'price_min' => 50000000, 'price_max' => null],
        ];

        foreach ($priceRanges as $i => $range) {
            FilterValue::create([
                'filter_id' => $price->id,
                'label' => $range['label'],
                'slug' => $range['slug'],
                'match_value' => null,
                'price_min' => $range['price_min'],
                'price_max' => $range['price_max'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 2. BO LOC CPU
        // ========================================
        $cpu = Filter::create([
            'name' => 'CPU',
            'slug' => 'cpu',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $cpuValues = [
            ['label' => 'Intel Core i3', 'slug' => 'intel-core-i3', 'match_value' => 'Core i3'],
            ['label' => 'Intel Core i5', 'slug' => 'intel-core-i5', 'match_value' => 'Core i5'],
            ['label' => 'Intel Core i7', 'slug' => 'intel-core-i7', 'match_value' => 'Core i7'],
            ['label' => 'Intel Core i9', 'slug' => 'intel-core-i9', 'match_value' => 'Core i9'],
            ['label' => 'Intel Core Ultra 5', 'slug' => 'intel-core-ultra-5', 'match_value' => 'Core Ultra 5'],
            ['label' => 'Intel Core Ultra 7', 'slug' => 'intel-core-ultra-7', 'match_value' => 'Core Ultra 7'],
            ['label' => 'Intel Core Ultra 9', 'slug' => 'intel-core-ultra-9', 'match_value' => 'Core Ultra 9'],
            ['label' => 'AMD Ryzen 5', 'slug' => 'amd-ryzen-5', 'match_value' => 'Ryzen 5'],
            ['label' => 'AMD Ryzen 7', 'slug' => 'amd-ryzen-7', 'match_value' => 'Ryzen 7'],
            ['label' => 'AMD Ryzen 9', 'slug' => 'amd-ryzen-9', 'match_value' => 'Ryzen 9'],
            ['label' => 'AMD Ryzen Threadripper', 'slug' => 'amd-threadripper', 'match_value' => 'Threadripper'],
        ];

        foreach ($cpuValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $cpu->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 3. BO LOC VGA
        // ========================================
        $vga = Filter::create([
            'name' => 'VGA - Card do hoa',
            'slug' => 'vga',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $vgaValues = [
            ['label' => 'NVIDIA RTX 4060', 'slug' => 'rtx-4060', 'match_value' => 'RTX 4060'],
            ['label' => 'NVIDIA RTX 4060 Ti', 'slug' => 'rtx-4060-ti', 'match_value' => 'RTX 4060 Ti'],
            ['label' => 'NVIDIA RTX 4070', 'slug' => 'rtx-4070', 'match_value' => 'RTX 4070'],
            ['label' => 'NVIDIA RTX 4070 Ti Super', 'slug' => 'rtx-4070-ti-super', 'match_value' => 'RTX 4070 Ti Super'],
            ['label' => 'NVIDIA RTX 4080 Super', 'slug' => 'rtx-4080-super', 'match_value' => 'RTX 4080 Super'],
            ['label' => 'NVIDIA RTX 4090', 'slug' => 'rtx-4090', 'match_value' => 'RTX 4090'],
            ['label' => 'NVIDIA RTX 5070', 'slug' => 'rtx-5070', 'match_value' => 'RTX 5070'],
            ['label' => 'NVIDIA RTX 5070 Ti', 'slug' => 'rtx-5070-ti', 'match_value' => 'RTX 5070 Ti'],
            ['label' => 'NVIDIA RTX 5080', 'slug' => 'rtx-5080', 'match_value' => 'RTX 5080'],
            ['label' => 'NVIDIA RTX 5090', 'slug' => 'rtx-5090', 'match_value' => 'RTX 5090'],
            ['label' => 'AMD RX 7600', 'slug' => 'rx-7600', 'match_value' => 'RX 7600'],
            ['label' => 'AMD RX 7700 XT', 'slug' => 'rx-7700-xt', 'match_value' => 'RX 7700 XT'],
            ['label' => 'AMD RX 7800 XT', 'slug' => 'rx-7800-xt', 'match_value' => 'RX 7800 XT'],
            ['label' => 'AMD RX 7900 XTX', 'slug' => 'rx-7900-xtx', 'match_value' => 'RX 7900 XTX'],
            ['label' => 'AMD RX 9070 XT', 'slug' => 'rx-9070-xt', 'match_value' => 'RX 9070 XT'],
            ['label' => 'Intel Arc B580', 'slug' => 'arc-b580', 'match_value' => 'Arc B580'],
        ];

        foreach ($vgaValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $vga->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 4. BO LOC RAM
        // ========================================
        $ram = Filter::create([
            'name' => 'RAM',
            'slug' => 'ram',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $ramValues = [
            ['label' => '8GB', 'slug' => '8gb', 'match_value' => '8GB'],
            ['label' => '16GB', 'slug' => '16gb', 'match_value' => '16GB'],
            ['label' => '32GB', 'slug' => '32gb', 'match_value' => '32GB'],
            ['label' => '64GB', 'slug' => '64gb', 'match_value' => '64GB'],
            ['label' => '128GB', 'slug' => '128gb', 'match_value' => '128GB'],
            ['label' => 'DDR4', 'slug' => 'ddr4', 'match_value' => 'DDR4'],
            ['label' => 'DDR5', 'slug' => 'ddr5', 'match_value' => 'DDR5'],
        ];

        foreach ($ramValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $ram->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 5. BO LOC O CUNG
        // ========================================
        $storage = Filter::create([
            'name' => 'O cung',
            'slug' => 'o-cung',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $storageValues = [
            ['label' => 'SSD 256GB', 'slug' => 'ssd-256gb', 'match_value' => 'SSD 256GB'],
            ['label' => 'SSD 512GB', 'slug' => 'ssd-512gb', 'match_value' => 'SSD 512GB'],
            ['label' => 'SSD 1TB', 'slug' => 'ssd-1tb', 'match_value' => 'SSD 1TB'],
            ['label' => 'SSD 2TB', 'slug' => 'ssd-2tb', 'match_value' => 'SSD 2TB'],
            ['label' => 'NVMe', 'slug' => 'nvme', 'match_value' => 'NVMe'],
            ['label' => 'SATA', 'slug' => 'sata', 'match_value' => 'SATA'],
            ['label' => 'HDD 1TB', 'slug' => 'hdd-1tb', 'match_value' => 'HDD 1TB'],
            ['label' => 'HDD 2TB', 'slug' => 'hdd-2tb', 'match_value' => 'HDD 2TB'],
        ];

        foreach ($storageValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $storage->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 6. BO LOC MAINBOARD
        // ========================================
        $mainboard = Filter::create([
            'name' => 'Mainboard',
            'slug' => 'mainboard',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $mbValues = [
            ['label' => 'Intel B760', 'slug' => 'b760', 'match_value' => 'B760'],
            ['label' => 'Intel Z790', 'slug' => 'z790', 'match_value' => 'Z790'],
            ['label' => 'Intel Z890', 'slug' => 'z890', 'match_value' => 'Z890'],
            ['label' => 'AMD B650', 'slug' => 'b650', 'match_value' => 'B650'],
            ['label' => 'AMD X670E', 'slug' => 'x670e', 'match_value' => 'X670E'],
            ['label' => 'AMD X870E', 'slug' => 'x870e', 'match_value' => 'X870E'],
            ['label' => 'ATX', 'slug' => 'atx', 'match_value' => 'ATX'],
            ['label' => 'Micro ATX', 'slug' => 'micro-atx', 'match_value' => 'Micro ATX'],
            ['label' => 'Mini ITX', 'slug' => 'mini-itx', 'match_value' => 'Mini ITX'],
        ];

        foreach ($mbValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $mainboard->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 7. BO LOC NGUON (PSU)
        // ========================================
        $psu = Filter::create([
            'name' => 'Nguon may tinh',
            'slug' => 'nguon',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 6,
            'is_active' => true,
        ]);

        $psuValues = [
            ['label' => '550W', 'slug' => '550w', 'match_value' => '550W'],
            ['label' => '650W', 'slug' => '650w', 'match_value' => '650W'],
            ['label' => '750W', 'slug' => '750w', 'match_value' => '750W'],
            ['label' => '850W', 'slug' => '850w', 'match_value' => '850W'],
            ['label' => '1000W', 'slug' => '1000w', 'match_value' => '1000W'],
            ['label' => '1200W', 'slug' => '1200w', 'match_value' => '1200W'],
            ['label' => '80+ Bronze', 'slug' => '80-plus-bronze', 'match_value' => '80+ Bronze'],
            ['label' => '80+ Gold', 'slug' => '80-plus-gold', 'match_value' => '80+ Gold'],
            ['label' => '80+ Platinum', 'slug' => '80-plus-platinum', 'match_value' => '80+ Platinum'],
        ];

        foreach ($psuValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $psu->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 8. BO LOC THUONG HIEU
        // ========================================
        $brand = Filter::create([
            'name' => 'Thuong hieu',
            'slug' => 'thuong-hieu',
            'type' => 'checkbox',
            'match_field' => 'brand',
            'sort_order' => 7,
            'is_active' => true,
        ]);

        $brandValues = [
            'ASUS', 'MSI', 'Gigabyte', 'ASRock',
            'EVGA', 'Corsair', 'G.Skill', 'Kingston',
            'Samsung', 'Western Digital', 'Seagate',
            'NZXT', 'Cooler Master', 'be quiet!',
            'Seasonic', 'Thermaltake', 'Lian Li',
            'Dell', 'HP', 'Lenovo', 'Acer', 'Apple',
        ];

        foreach ($brandValues as $i => $name) {
            FilterValue::create([
                'filter_id' => $brand->id,
                'label' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'match_value' => \Illuminate\Support\Str::slug($name),
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 9. BO LOC MAN HINH
        // ========================================
        $monitor = Filter::create([
            'name' => 'Man hinh',
            'slug' => 'man-hinh',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 8,
            'is_active' => true,
        ]);

        $monitorValues = [
            ['label' => '24 inch', 'slug' => '24-inch', 'match_value' => '24"'],
            ['label' => '27 inch', 'slug' => '27-inch', 'match_value' => '27"'],
            ['label' => '32 inch', 'slug' => '32-inch', 'match_value' => '32"'],
            ['label' => '34 inch Ultrawide', 'slug' => '34-inch', 'match_value' => '34"'],
            ['label' => 'Full HD 1080p', 'slug' => 'full-hd', 'match_value' => '1920x1080'],
            ['label' => '2K QHD 1440p', 'slug' => '2k-qhd', 'match_value' => '2560x1440'],
            ['label' => '4K UHD', 'slug' => '4k-uhd', 'match_value' => '3840x2160'],
            ['label' => '144Hz', 'slug' => '144hz', 'match_value' => '144Hz'],
            ['label' => '165Hz', 'slug' => '165hz', 'match_value' => '165Hz'],
            ['label' => '240Hz', 'slug' => '240hz', 'match_value' => '240Hz'],
            ['label' => 'IPS', 'slug' => 'ips', 'match_value' => 'IPS'],
            ['label' => 'VA', 'slug' => 'va', 'match_value' => 'VA'],
            ['label' => 'OLED', 'slug' => 'oled', 'match_value' => 'OLED'],
        ];

        foreach ($monitorValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $monitor->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // 10. BO LOC TAN NHIET
        // ========================================
        $cooling = Filter::create([
            'name' => 'Tan nhiet',
            'slug' => 'tan-nhiet',
            'type' => 'checkbox',
            'match_field' => 'specifications_text',
            'sort_order' => 9,
            'is_active' => true,
        ]);

        $coolingValues = [
            ['label' => 'Tan nhiet khi (Air Cooler)', 'slug' => 'air-cooler', 'match_value' => 'Air Cooler'],
            ['label' => 'Tan nhiet nuoc AIO 240mm', 'slug' => 'aio-240mm', 'match_value' => '240mm'],
            ['label' => 'Tan nhiet nuoc AIO 280mm', 'slug' => 'aio-280mm', 'match_value' => '280mm'],
            ['label' => 'Tan nhiet nuoc AIO 360mm', 'slug' => 'aio-360mm', 'match_value' => '360mm'],
            ['label' => 'Custom Loop', 'slug' => 'custom-loop', 'match_value' => 'Custom Loop'],
        ];

        foreach ($coolingValues as $i => $v) {
            FilterValue::create([
                'filter_id' => $cooling->id,
                'label' => $v['label'],
                'slug' => $v['slug'],
                'match_value' => $v['match_value'],
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }

        // ========================================
        // GAN BO LOC VAO DANH MUC
        // ========================================
        $categoryFilters = [
            // PC Gaming, Van phong, Do hoa: gia, cpu, vga, ram, o cung, mainboard, nguon, tan nhiet
            'pc-gaming' => [$price, $cpu, $vga, $ram, $storage, $mainboard, $psu, $cooling, $brand],
            'pc-van-phong' => [$price, $cpu, $ram, $storage, $brand],
            'pc-do-hoa-render' => [$price, $cpu, $vga, $ram, $storage, $mainboard, $psu, $cooling, $brand],

            // Parent PC
            'pc-may-tinh-de-ban' => [$price, $cpu, $vga, $ram, $brand],

            // Laptop
            'laptop' => [$price, $cpu, $vga, $ram, $storage, $brand],
            'laptop-gaming' => [$price, $cpu, $vga, $ram, $storage, $brand],
            'laptop-van-phong' => [$price, $cpu, $ram, $storage, $brand],
            'laptop-do-hoa' => [$price, $cpu, $vga, $ram, $storage, $brand],

            // Linh kien
            'linh-kien-pc' => [$price, $brand],
            'cpu' => [$price, $cpu, $brand],
            'mainboard' => [$price, $mainboard, $brand],
            'ram' => [$price, $ram, $brand],
            'vga-card-do-hoa' => [$price, $vga, $brand],
            'ssd' => [$price, $storage, $brand],
            'hdd' => [$price, $storage, $brand],
            'nguon-may-tinh' => [$price, $psu, $brand],
            'case-vo-may-tinh' => [$price, $brand],
            'tan-nhiet' => [$price, $cooling, $brand],

            // Phu kien
            'man-hinh' => [$price, $monitor, $brand],
            'ban-phim' => [$price, $brand],
            'chuot' => [$price, $brand],
            'tai-nghe' => [$price, $brand],
        ];

        foreach ($categoryFilters as $slug => $filters) {
            $category = Category::where('slug', $slug)->first();
            if ($category) {
                foreach ($filters as $order => $filter) {
                    $category->filters()->syncWithoutDetaching([
                        $filter->id => ['sort_order' => $order],
                    ]);
                }
            }
        }

        $this->command->info('FilterSeeder: Da tao ' . Filter::count() . ' bo loc va ' . FilterValue::count() . ' gia tri');
    }
}
