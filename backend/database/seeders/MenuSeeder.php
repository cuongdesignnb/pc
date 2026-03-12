<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // ===== HEADER (Main Menu) =====
        $header = Menu::create([
            'name' => 'Menu Chính',
            'slug' => 'main-menu',
            'location' => 'header',
            'description' => 'Menu hiển thị trên header trang web',
            'is_active' => true,
        ]);

        // 1. Trang chủ
        MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Trang chủ', 'url' => '/',
            'type' => 'custom', 'sort_order' => 0, 'is_active' => true,
        ]);

        // 2. PC - Mega Menu
        $pc = MenuItem::create([
            'menu_id' => $header->id, 'title' => 'PC', 'url' => '/categories/pc-may-tinh-de-ban',
            'type' => 'custom', 'sort_order' => 1, 'is_active' => true,
            'is_mega' => true, 'mega_columns' => 3,
        ]);

        $pcGaming = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $pc->id,
            'title' => 'PC Gaming', 'url' => '/categories/pc-gaming',
            'type' => 'custom', 'sort_order' => 0, 'is_active' => true,
            'badge_text' => 'HOT', 'badge_color' => 'red',
        ]);
        foreach ([
            ['PC Gaming Starter', '/products?category=pc-gaming&max_price=20000000', 'Từ 16 triệu'],
            ['PC Gaming Pro', '/products?category=pc-gaming&min_price=20000000&max_price=50000000', 'Từ 20 triệu'],
            ['PC Gaming Ultimate', '/products?category=pc-gaming&min_price=50000000', 'Từ 50 triệu'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $pcGaming->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true, 'description' => $item[2],
            ]);
        }

        $pcOffice = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $pc->id,
            'title' => 'PC Văn phòng', 'url' => '/categories/pc-van-phong',
            'type' => 'custom', 'sort_order' => 1, 'is_active' => true,
        ]);
        foreach ([
            ['PC Văn phòng Basic', '/products?category=pc-van-phong&max_price=10000000'],
            ['PC Văn phòng Pro', '/products?category=pc-van-phong&min_price=10000000'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $pcOffice->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $pc->id,
            'title' => 'PC Đồ họa - Render', 'url' => '/categories/pc-do-hoa-render',
            'type' => 'custom', 'sort_order' => 2, 'is_active' => true,
        ]);

        // 3. Laptop - Mega Menu
        $laptop = MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Laptop', 'url' => '/categories/laptop',
            'type' => 'custom', 'sort_order' => 2, 'is_active' => true,
            'is_mega' => true, 'mega_columns' => 3,
        ]);

        $laptopGaming = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $laptop->id,
            'title' => 'Laptop Gaming', 'url' => '/categories/laptop-gaming',
            'type' => 'custom', 'sort_order' => 0, 'is_active' => true,
            'badge_text' => 'HOT', 'badge_color' => 'red',
        ]);
        foreach ([
            ['ASUS ROG', '/products?category=laptop-gaming&brand=asus'],
            ['MSI Gaming', '/products?category=laptop-gaming&brand=msi'],
            ['Lenovo Legion', '/products?category=laptop-gaming&brand=lenovo'],
            ['Acer Predator', '/products?category=laptop-gaming&brand=acer'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $laptopGaming->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $laptopOffice = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $laptop->id,
            'title' => 'Laptop Văn phòng', 'url' => '/categories/laptop-van-phong',
            'type' => 'custom', 'sort_order' => 1, 'is_active' => true,
        ]);
        foreach ([
            ['Lenovo ThinkPad', '/products?category=laptop-van-phong&brand=lenovo'],
            ['HP EliteBook', '/products?category=laptop-van-phong&brand=hp'],
            ['Dell Latitude', '/products?category=laptop-van-phong&brand=dell'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $laptopOffice->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $laptop->id,
            'title' => 'Laptop Đồ họa', 'url' => '/categories/laptop-do-hoa',
            'type' => 'custom', 'sort_order' => 2, 'is_active' => true,
        ]);

        // 4. Linh kiện - Mega Menu
        $components = MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Linh kiện', 'url' => '/categories/linh-kien-pc',
            'type' => 'custom', 'sort_order' => 3, 'is_active' => true,
            'is_mega' => true, 'mega_columns' => 5,
        ]);

        $cpuGroup = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $components->id,
            'title' => 'CPU - Bộ xử lý', 'url' => '/categories/cpu',
            'type' => 'custom', 'sort_order' => 0, 'is_active' => true,
        ]);
        foreach ([
            ['Intel Core i9', '/products?category=cpu&brand=intel&search=i9'],
            ['Intel Core i7', '/products?category=cpu&brand=intel&search=i7'],
            ['Intel Core i5', '/products?category=cpu&brand=intel&search=i5'],
            ['AMD Ryzen 9', '/products?category=cpu&brand=amd&search=ryzen+9'],
            ['AMD Ryzen 7', '/products?category=cpu&brand=amd&search=ryzen+7'],
            ['AMD Ryzen 5', '/products?category=cpu&brand=amd&search=ryzen+5'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $cpuGroup->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $vgaGroup = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $components->id,
            'title' => 'VGA - Card đồ họa', 'url' => '/categories/vga',
            'type' => 'custom', 'sort_order' => 1, 'is_active' => true,
        ]);
        foreach ([
            ['NVIDIA RTX 4090', '/products?category=vga&search=4090'],
            ['NVIDIA RTX 4080', '/products?category=vga&search=4080'],
            ['NVIDIA RTX 4070', '/products?category=vga&search=4070'],
            ['NVIDIA RTX 4060', '/products?category=vga&search=4060'],
            ['AMD Radeon RX 7900', '/products?category=vga&search=7900'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $vgaGroup->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $mbGroup = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $components->id,
            'title' => 'Mainboard', 'url' => '/categories/mainboard',
            'type' => 'custom', 'sort_order' => 2, 'is_active' => true,
        ]);
        foreach ([
            ['Intel LGA 1700', '/products?category=mainboard&search=LGA+1700'],
            ['AMD AM5', '/products?category=mainboard&search=AM5'],
            ['ASUS ROG / TUF', '/products?category=mainboard&brand=asus'],
            ['MSI MAG / MEG', '/products?category=mainboard&brand=msi'],
            ['Gigabyte AORUS', '/products?category=mainboard&brand=gigabyte'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $mbGroup->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $storageGroup = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $components->id,
            'title' => 'Lưu trữ', 'url' => '/categories/ram',
            'type' => 'custom', 'sort_order' => 3, 'is_active' => true,
        ]);
        foreach ([
            ['RAM DDR5', '/categories/ram'],
            ['SSD NVMe M.2', '/categories/ssd'],
            ['HDD', '/categories/hdd'],
            ['Samsung', '/products?category=ssd&brand=samsung'],
            ['Kingston', '/products?category=ram&brand=kingston'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $storageGroup->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $psuGroup = MenuItem::create([
            'menu_id' => $header->id, 'parent_id' => $components->id,
            'title' => 'Nguồn & Tản nhiệt', 'url' => '/categories/psu',
            'type' => 'custom', 'sort_order' => 4, 'is_active' => true,
        ]);
        foreach ([
            ['Nguồn (PSU)', '/categories/psu'],
            ['Vỏ case', '/categories/case'],
            ['Tản nhiệt CPU', '/categories/cooler'],
            ['Quạt case', '/categories/fan'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $psuGroup->id,
                'title' => $item[0], 'url' => $item[1], 'type' => 'custom',
                'sort_order' => $i, 'is_active' => true,
            ]);
        }

        // 5. Phụ kiện - Mega Menu
        $accessories = MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Phụ kiện', 'url' => '/categories/phu-kien',
            'type' => 'custom', 'sort_order' => 4, 'is_active' => true,
            'is_mega' => true, 'mega_columns' => 4,
        ]);

        foreach ([
            ['Màn hình', '/categories/man-hinh', [
                ['Màn hình Gaming', '/products?category=man-hinh&search=gaming'],
                ['Màn hình 4K', '/products?category=man-hinh&search=4k'],
                ['LG', '/products?category=man-hinh&brand=lg'],
                ['Samsung', '/products?category=man-hinh&brand=samsung'],
                ['Dell', '/products?category=man-hinh&brand=dell'],
            ]],
            ['Bàn phím', '/categories/ban-phim', [
                ['Cơ / Mechanical', '/products?category=ban-phim&search=mechanical'],
                ['Logitech', '/products?category=ban-phim&brand=logitech'],
                ['Razer', '/products?category=ban-phim&brand=razer'],
            ]],
            ['Chuột', '/categories/chuot', [
                ['Chuột Wireless', '/products?category=chuot&search=wireless'],
                ['Logitech', '/products?category=chuot&brand=logitech'],
                ['Razer', '/products?category=chuot&brand=razer'],
            ]],
            ['Tai nghe & Loa', '/categories/tai-nghe', [
                ['Tai nghe Gaming', '/products?category=tai-nghe&search=gaming'],
                ['Loa', '/categories/loa'],
                ['SteelSeries', '/products?category=tai-nghe&brand=steelseries'],
                ['HyperX', '/products?category=tai-nghe&brand=hyperx'],
            ]],
        ] as $gi => $group) {
            $g = MenuItem::create([
                'menu_id' => $header->id, 'parent_id' => $accessories->id,
                'title' => $group[0], 'url' => $group[1], 'type' => 'custom',
                'sort_order' => $gi, 'is_active' => true,
            ]);
            foreach ($group[2] as $ci => $child) {
                MenuItem::create([
                    'menu_id' => $header->id, 'parent_id' => $g->id,
                    'title' => $child[0], 'url' => $child[1], 'type' => 'custom',
                    'sort_order' => $ci, 'is_active' => true,
                ]);
            }
        }

        // 6. Build PC
        MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Build PC', 'url' => '/configurator',
            'type' => 'custom', 'sort_order' => 5, 'is_active' => true,
            'badge_text' => 'New', 'badge_color' => 'blue',
        ]);

        // 7. Tin tức
        MenuItem::create([
            'menu_id' => $header->id, 'title' => 'Tin tức', 'url' => '/blog',
            'type' => 'custom', 'sort_order' => 6, 'is_active' => true,
        ]);

        // ===== FOOTER MENU =====
        $footer = Menu::create([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'location' => 'footer',
            'description' => 'Menu hiển thị ở chân trang',
            'is_active' => true,
        ]);

        foreach ([
            ['Giới thiệu', '/about'],
            ['Liên hệ', '/contact'],
            ['Bảo hành', '/warranty'],
            ['Vận chuyển', '/shipping'],
            ['Chính sách đổi trả', '/returns'],
        ] as $i => $item) {
            MenuItem::create([
                'menu_id' => $footer->id, 'title' => $item[0], 'url' => $item[1],
                'type' => 'custom', 'sort_order' => $i, 'is_active' => true,
            ]);
        }
    }
}
