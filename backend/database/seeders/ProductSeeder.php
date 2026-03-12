<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Models\SpecificationKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // ===== CPU (cat:11, comp_type:1) =====
            ['category_id' => 11, 'brand_id' => 1, 'component_type_id' => 1, 'name' => 'Intel Core i9-14900K', 'price' => 15990000, 'sale_price' => 14490000, 'short_description' => '24 nhân 32 luồng, 6.0GHz Turbo, 36MB Cache, LGA 1700', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 15],
            ['category_id' => 11, 'brand_id' => 1, 'component_type_id' => 1, 'name' => 'Intel Core i7-14700K', 'price' => 10990000, 'sale_price' => 9990000, 'short_description' => '20 nhân 28 luồng, 5.6GHz Turbo, 33MB Cache, LGA 1700', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 20],
            ['category_id' => 11, 'brand_id' => 1, 'component_type_id' => 1, 'name' => 'Intel Core i5-14600K', 'price' => 7490000, 'sale_price' => 6890000, 'short_description' => '14 nhân 20 luồng, 5.3GHz Turbo, 24MB Cache, LGA 1700', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 30],
            ['category_id' => 11, 'brand_id' => 2, 'component_type_id' => 1, 'name' => 'AMD Ryzen 9 7950X3D', 'price' => 16990000, 'sale_price' => null, 'short_description' => '16 nhân 32 luồng, 5.7GHz Boost, 128MB 3D V-Cache, AM5', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 8],
            ['category_id' => 11, 'brand_id' => 2, 'component_type_id' => 1, 'name' => 'AMD Ryzen 7 7800X3D', 'price' => 10490000, 'sale_price' => 9490000, 'short_description' => '8 nhân 16 luồng, 5.0GHz Boost, 96MB 3D V-Cache, AM5', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 25],
            ['category_id' => 11, 'brand_id' => 2, 'component_type_id' => 1, 'name' => 'AMD Ryzen 5 7600X', 'price' => 5990000, 'sale_price' => 5290000, 'short_description' => '6 nhân 12 luồng, 5.3GHz Boost, 32MB Cache, AM5', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 35],

            // ===== VGA (cat:14, comp_type:4) =====
            ['category_id' => 14, 'brand_id' => 4, 'component_type_id' => 4, 'name' => 'ASUS ROG Strix RTX 4090 OC 24GB', 'price' => 52990000, 'sale_price' => 49990000, 'short_description' => 'GDDR6X 24GB, 384-bit, 2640MHz Boost, 3.5 slot', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 5],
            ['category_id' => 14, 'brand_id' => 5, 'component_type_id' => 4, 'name' => 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB', 'price' => 32990000, 'sale_price' => 30990000, 'short_description' => 'GDDR6X 16GB, 256-bit, 2610MHz Boost', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 10],
            ['category_id' => 14, 'brand_id' => 6, 'component_type_id' => 4, 'name' => 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB', 'price' => 22990000, 'sale_price' => 21490000, 'short_description' => 'GDDR6X 16GB, 256-bit, 2640MHz Boost', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 12],
            ['category_id' => 14, 'brand_id' => 5, 'component_type_id' => 4, 'name' => 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB', 'price' => 16990000, 'sale_price' => 15490000, 'short_description' => 'GDDR6X 12GB, 192-bit, 2510MHz Boost', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 18],
            ['category_id' => 14, 'brand_id' => 4, 'component_type_id' => 4, 'name' => 'ASUS Dual RTX 4060 Ti OC 8GB', 'price' => 11990000, 'sale_price' => 10990000, 'short_description' => 'GDDR6 8GB, 128-bit, 2580MHz Boost', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 22],
            ['category_id' => 14, 'brand_id' => 6, 'component_type_id' => 4, 'name' => 'Gigabyte RTX 4060 Eagle OC 8GB', 'price' => 8490000, 'sale_price' => 7990000, 'short_description' => 'GDDR6 8GB, 128-bit, 2475MHz Boost', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 30],
            ['category_id' => 14, 'brand_id' => 2, 'component_type_id' => 4, 'name' => 'AMD Radeon RX 7900 XTX 24GB', 'price' => 28990000, 'sale_price' => 26990000, 'short_description' => 'GDDR6 24GB, 384-bit, 2500MHz Boost', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 7],

            // ===== Mainboard (cat:12, comp_type:2) =====
            ['category_id' => 12, 'brand_id' => 4, 'component_type_id' => 2, 'name' => 'ASUS ROG Maximus Z790 Hero', 'price' => 16990000, 'sale_price' => null, 'short_description' => 'LGA 1700, DDR5, Wi-Fi 6E, Thunderbolt 4, E-ATX', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 8],
            ['category_id' => 12, 'brand_id' => 5, 'component_type_id' => 2, 'name' => 'MSI MAG B760 Tomahawk WiFi', 'price' => 5490000, 'sale_price' => 4990000, 'short_description' => 'LGA 1700, DDR5, Wi-Fi 6E, 2.5G LAN, ATX', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 25],
            ['category_id' => 12, 'brand_id' => 6, 'component_type_id' => 2, 'name' => 'Gigabyte B650 AORUS Elite AX V2', 'price' => 5990000, 'sale_price' => 5490000, 'short_description' => 'AM5, DDR5, Wi-Fi 6E, 2.5G LAN, ATX', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 20],
            ['category_id' => 12, 'brand_id' => 4, 'component_type_id' => 2, 'name' => 'ASUS ROG Strix X670E-E Gaming WiFi', 'price' => 12990000, 'sale_price' => 11990000, 'short_description' => 'AM5, DDR5, Wi-Fi 6E, PCIe 5.0, ATX', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 10],

            // ===== RAM (cat:13, comp_type:3) =====
            ['category_id' => 13, 'brand_id' => 12, 'component_type_id' => 3, 'name' => 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000', 'price' => 4590000, 'sale_price' => 3990000, 'short_description' => 'DDR5, 6000MHz, CL36, Dual Channel, RGB', 'warranty_months' => 60, 'is_featured' => true, 'stock_quantity' => 30],
            ['category_id' => 13, 'brand_id' => 14, 'component_type_id' => 3, 'name' => 'Corsair Vengeance 32GB (2x16GB) DDR5-5600', 'price' => 3290000, 'sale_price' => 2990000, 'short_description' => 'DDR5, 5600MHz, CL36, Dual Channel', 'warranty_months' => 60, 'is_featured' => false, 'stock_quantity' => 40],
            ['category_id' => 13, 'brand_id' => 13, 'component_type_id' => 3, 'name' => 'Kingston Fury Beast 32GB (2x16GB) DDR5-5200', 'price' => 2790000, 'sale_price' => null, 'short_description' => 'DDR5, 5200MHz, CL40, Dual Channel', 'warranty_months' => 60, 'is_featured' => false, 'stock_quantity' => 50],
            ['category_id' => 13, 'brand_id' => 12, 'component_type_id' => 3, 'name' => 'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000', 'price' => 7990000, 'sale_price' => 7290000, 'short_description' => 'DDR5, 6000MHz, CL30, Tối ưu AMD EXPO', 'warranty_months' => 60, 'is_featured' => true, 'stock_quantity' => 15],

            // ===== SSD (cat:15, comp_type:5) =====
            ['category_id' => 15, 'brand_id' => 17, 'component_type_id' => 5, 'name' => 'Samsung 990 Pro 2TB NVMe M.2', 'price' => 5490000, 'sale_price' => 4990000, 'short_description' => 'PCIe Gen 4, 7450/6900 MB/s, TLC NAND', 'warranty_months' => 60, 'is_featured' => true, 'stock_quantity' => 25],
            ['category_id' => 15, 'brand_id' => 17, 'component_type_id' => 5, 'name' => 'Samsung 990 Pro 1TB NVMe M.2', 'price' => 3290000, 'sale_price' => 2990000, 'short_description' => 'PCIe Gen 4, 7450/6900 MB/s, TLC NAND', 'warranty_months' => 60, 'is_featured' => false, 'stock_quantity' => 40],
            ['category_id' => 15, 'brand_id' => 18, 'component_type_id' => 5, 'name' => 'WD Black SN850X 2TB NVMe M.2', 'price' => 4990000, 'sale_price' => 4490000, 'short_description' => 'PCIe Gen 4, 7300/6600 MB/s, TLC NAND', 'warranty_months' => 60, 'is_featured' => false, 'stock_quantity' => 20],
            ['category_id' => 15, 'brand_id' => 16, 'component_type_id' => 5, 'name' => 'Crucial T700 2TB NVMe M.2', 'price' => 7990000, 'sale_price' => null, 'short_description' => 'PCIe Gen 5, 12400/11800 MB/s, TLC NAND', 'warranty_months' => 60, 'is_featured' => true, 'stock_quantity' => 10],

            // ===== PSU (cat:17, comp_type:7) =====
            ['category_id' => 17, 'brand_id' => 22, 'component_type_id' => 7, 'name' => 'Seasonic Focus GX-850 850W 80+ Gold', 'price' => 3290000, 'sale_price' => 2990000, 'short_description' => 'Full Modular, 80+ Gold, 10 năm bảo hành', 'warranty_months' => 120, 'is_featured' => false, 'stock_quantity' => 20],
            ['category_id' => 17, 'brand_id' => 14, 'component_type_id' => 7, 'name' => 'Corsair RM1000x 1000W 80+ Gold', 'price' => 4890000, 'sale_price' => 4490000, 'short_description' => 'Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0', 'warranty_months' => 120, 'is_featured' => true, 'stock_quantity' => 15],
            ['category_id' => 17, 'brand_id' => 22, 'component_type_id' => 7, 'name' => 'Seasonic Vertex GX-1200 1200W 80+ Gold', 'price' => 6490000, 'sale_price' => null, 'short_description' => 'Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0', 'warranty_months' => 120, 'is_featured' => true, 'stock_quantity' => 8],

            // ===== Case (cat:18, comp_type:8) =====
            ['category_id' => 18, 'brand_id' => 28, 'component_type_id' => 8, 'name' => 'Lian Li O11 Dynamic EVO', 'price' => 4290000, 'sale_price' => 3990000, 'short_description' => 'Mid Tower, Tempered Glass, hỗ trợ E-ATX, USB-C', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 12],
            ['category_id' => 18, 'brand_id' => 23, 'component_type_id' => 8, 'name' => 'NZXT H7 Flow', 'price' => 3290000, 'sale_price' => null, 'short_description' => 'Mid Tower, Airflow tối ưu, TG, USB-C', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 18],
            ['category_id' => 18, 'brand_id' => 29, 'component_type_id' => 8, 'name' => 'Phanteks NV7 TG', 'price' => 4990000, 'sale_price' => 4490000, 'short_description' => 'Full Tower, Dual Chamber, Tempered Glass', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 10],

            // ===== Cooler (cat:19, comp_type:9) =====
            ['category_id' => 19, 'brand_id' => 31, 'component_type_id' => 9, 'name' => 'Noctua NH-D15 chromax.black', 'price' => 2890000, 'sale_price' => null, 'short_description' => 'Dual Tower, 2x 140mm fan, 250W TDP', 'warranty_months' => 72, 'is_featured' => true, 'stock_quantity' => 15],
            ['category_id' => 19, 'brand_id' => 33, 'component_type_id' => 9, 'name' => 'DeepCool LT720 AIO 360mm', 'price' => 3490000, 'sale_price' => 2990000, 'short_description' => 'AIO 360mm, Infinity Mirror, ARGB', 'warranty_months' => 60, 'is_featured' => true, 'stock_quantity' => 20],
            ['category_id' => 19, 'brand_id' => 14, 'component_type_id' => 9, 'name' => 'Corsair iCUE H150i Elite LCD XT 360mm', 'price' => 7990000, 'sale_price' => 7490000, 'short_description' => 'AIO 360mm, LCD Display, iCUE RGB', 'warranty_months' => 60, 'is_featured' => false, 'stock_quantity' => 8],

            // ===== PC Gaming (cat:5) =====
            ['category_id' => 5, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Gaming Pro - RTX 4070 Super', 'price' => 32990000, 'sale_price' => 29990000, 'short_description' => 'i7-14700K / RTX 4070 Super / 32GB DDR5 / 1TB NVMe', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 5],
            ['category_id' => 5, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Gaming Elite - RTX 4080 Super', 'price' => 52990000, 'sale_price' => 48990000, 'short_description' => 'i9-14900K / RTX 4080 Super / 64GB DDR5 / 2TB NVMe', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 3],
            ['category_id' => 5, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Gaming Ultimate - RTX 4090', 'price' => 79990000, 'sale_price' => 74990000, 'short_description' => 'i9-14900K / RTX 4090 / 64GB DDR5 / 4TB NVMe / AIO 360', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 2],
            ['category_id' => 5, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Gaming Starter - RTX 4060', 'price' => 18990000, 'sale_price' => 16990000, 'short_description' => 'i5-14600K / RTX 4060 / 16GB DDR5 / 512GB NVMe', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 10],

            // ===== PC Văn phòng (cat:6) =====
            ['category_id' => 6, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Văn phòng Basic', 'price' => 7990000, 'sale_price' => 6990000, 'short_description' => 'i3-14100 / Intel UHD 730 / 8GB DDR5 / 256GB NVMe', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 20],
            ['category_id' => 6, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Văn phòng Pro', 'price' => 12990000, 'sale_price' => 11490000, 'short_description' => 'i5-14400 / Intel UHD 730 / 16GB DDR5 / 512GB NVMe', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 15],

            // ===== Laptop Gaming (cat:8) =====
            ['category_id' => 8, 'brand_id' => 4, 'component_type_id' => null, 'name' => 'ASUS ROG Strix G16 G614JV', 'price' => 39990000, 'sale_price' => 36990000, 'short_description' => 'i9-13980HX / RTX 4060 / 16GB DDR5 / 1TB / 16" QHD 240Hz', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 8],
            ['category_id' => 8, 'brand_id' => 5, 'component_type_id' => null, 'name' => 'MSI Raider GE78 HX 13VH', 'price' => 69990000, 'sale_price' => 64990000, 'short_description' => 'i9-13950HX / RTX 4080 / 32GB DDR5 / 2TB / 17" QHD 240Hz', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 3],
            ['category_id' => 8, 'brand_id' => 46, 'component_type_id' => null, 'name' => 'Lenovo Legion Pro 5 16IRX9', 'price' => 44990000, 'sale_price' => 41990000, 'short_description' => 'i7-14700HX / RTX 4070 / 32GB DDR5 / 1TB / 16" WQXGA 240Hz', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 6],
            ['category_id' => 8, 'brand_id' => 48, 'component_type_id' => null, 'name' => 'Acer Predator Helios Neo 16 PHN16-72', 'price' => 34990000, 'sale_price' => 31990000, 'short_description' => 'i7-14700HX / RTX 4060 / 16GB DDR5 / 1TB / 16" WQXGA 165Hz', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 10],

            // ===== Laptop Văn phòng (cat:9) =====
            ['category_id' => 9, 'brand_id' => 46, 'component_type_id' => null, 'name' => 'Lenovo ThinkPad X1 Carbon Gen 11', 'price' => 39990000, 'sale_price' => 36990000, 'short_description' => 'i7-1365U / 16GB / 512GB / 14" 2.8K OLED', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 5],
            ['category_id' => 9, 'brand_id' => 47, 'component_type_id' => null, 'name' => 'HP EliteBook 840 G10', 'price' => 29990000, 'sale_price' => 27990000, 'short_description' => 'i7-1365U / 16GB / 512GB / 14" FHD IPS', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 8],

            // ===== Màn hình (cat:21) =====
            ['category_id' => 21, 'brand_id' => 41, 'component_type_id' => null, 'name' => 'LG 27GP850-B UltraGear 27" QHD IPS', 'price' => 9990000, 'sale_price' => 8990000, 'short_description' => '27" QHD, IPS, 165Hz, 1ms, HDR400, NVIDIA G-Sync', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 12],
            ['category_id' => 21, 'brand_id' => 42, 'component_type_id' => null, 'name' => 'Dell S2722DGM 27" QHD Curved', 'price' => 7990000, 'sale_price' => 6990000, 'short_description' => '27" QHD, VA Curved, 165Hz, 1ms, AMD FreeSync', 'warranty_months' => 36, 'is_featured' => false, 'stock_quantity' => 15],
            ['category_id' => 21, 'brand_id' => 17, 'component_type_id' => null, 'name' => 'Samsung Odyssey G7 32" 4K', 'price' => 18990000, 'sale_price' => 16990000, 'short_description' => '32" 4K, IPS, 144Hz, 1ms, Smart TV, HDMI 2.1', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 7],

            // ===== Bàn phím (cat:22) =====
            ['category_id' => 22, 'brand_id' => 35, 'component_type_id' => null, 'name' => 'Logitech G Pro X TKL', 'price' => 2990000, 'sale_price' => null, 'short_description' => 'Mechanical, Hot-swap, GX Switch, LIGHTSYNC RGB', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 25],
            ['category_id' => 22, 'brand_id' => 36, 'component_type_id' => null, 'name' => 'Razer BlackWidow V4 Pro', 'price' => 5990000, 'sale_price' => 5490000, 'short_description' => 'Mechanical, Green Switch, Chroma RGB, Cmd Dial', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 10],

            // ===== Chuột (cat:23) =====
            ['category_id' => 23, 'brand_id' => 35, 'component_type_id' => null, 'name' => 'Logitech G Pro X Superlight 2', 'price' => 3590000, 'sale_price' => 3290000, 'short_description' => 'Wireless, 60g, HERO 2 Sensor 32K DPI, 95h Pin', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 20],
            ['category_id' => 23, 'brand_id' => 36, 'component_type_id' => null, 'name' => 'Razer DeathAdder V3 Pro', 'price' => 3790000, 'sale_price' => null, 'short_description' => 'Wireless, 63g, Focus Pro 30K DPI, 90h Pin', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 15],

            // ===== Tai nghe (cat:24) =====
            ['category_id' => 24, 'brand_id' => 37, 'component_type_id' => null, 'name' => 'SteelSeries Arctis Nova Pro Wireless', 'price' => 8990000, 'sale_price' => 7990000, 'short_description' => 'Wireless, ANC, Hi-Res, Hot-Swap Pin, Multi-Source', 'warranty_months' => 24, 'is_featured' => true, 'stock_quantity' => 8],
            ['category_id' => 24, 'brand_id' => 38, 'component_type_id' => null, 'name' => 'HyperX Cloud III Wireless', 'price' => 3990000, 'sale_price' => 3490000, 'short_description' => 'Wireless, DTS:X, 53mm Drivers, 120h Pin', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 18],

            // ===== Ghế Gaming (cat:28) =====
            ['category_id' => 28, 'brand_id' => null, 'component_type_id' => null, 'name' => 'Ghế Gaming DXRacer Air Pro', 'price' => 12990000, 'sale_price' => 11490000, 'short_description' => 'Lưới mesh cao cấp, 4D Armrest, Lumbar Support', 'warranty_months' => 24, 'is_featured' => false, 'stock_quantity' => 5],

            // ===== PC Đồ họa (cat:7) =====
            ['category_id' => 7, 'brand_id' => null, 'component_type_id' => null, 'name' => 'PC Đồ họa - Render Chuyên nghiệp', 'price' => 65990000, 'sale_price' => 59990000, 'short_description' => 'i9-14900K / RTX 4080 Super / 128GB DDR5 / 4TB NVMe RAID', 'warranty_months' => 36, 'is_featured' => true, 'stock_quantity' => 3],
        ];

        // Pre-load all specification keys grouped by component_type_id -> key
        $specKeys = SpecificationKey::all()->groupBy('component_type_id')->map(function ($keys) {
            return $keys->keyBy('key');
        });

        // Compatibility specs for each product (by name)
        $compatSpecs = [
            // --- CPU ---
            'Intel Core i9-14900K' => ['socket' => 'LGA1700', 'cores' => '24', 'threads' => '32', 'base_clock' => '3.2', 'boost_clock' => '6.0', 'tdp' => '125', 'memory_type' => 'DDR5'],
            'Intel Core i7-14700K' => ['socket' => 'LGA1700', 'cores' => '20', 'threads' => '28', 'base_clock' => '3.4', 'boost_clock' => '5.6', 'tdp' => '125', 'memory_type' => 'DDR5'],
            'Intel Core i5-14600K' => ['socket' => 'LGA1700', 'cores' => '14', 'threads' => '20', 'base_clock' => '3.5', 'boost_clock' => '5.3', 'tdp' => '125', 'memory_type' => 'DDR5'],
            'AMD Ryzen 9 7950X3D' => ['socket' => 'AM5', 'cores' => '16', 'threads' => '32', 'base_clock' => '4.2', 'boost_clock' => '5.7', 'tdp' => '120', 'memory_type' => 'DDR5'],
            'AMD Ryzen 7 7800X3D' => ['socket' => 'AM5', 'cores' => '8', 'threads' => '16', 'base_clock' => '4.2', 'boost_clock' => '5.0', 'tdp' => '120', 'memory_type' => 'DDR5'],
            'AMD Ryzen 5 7600X' => ['socket' => 'AM5', 'cores' => '6', 'threads' => '12', 'base_clock' => '4.7', 'boost_clock' => '5.3', 'tdp' => '105', 'memory_type' => 'DDR5'],

            // --- Mainboard ---
            'ASUS ROG Maximus Z790 Hero' => ['socket' => 'LGA1700', 'chipset' => 'Z790', 'form_factor' => 'ATX', 'memory_type' => 'DDR5', 'memory_slots' => '4', 'max_memory' => '128', 'm2_slots' => '5'],
            'MSI MAG B760 Tomahawk WiFi' => ['socket' => 'LGA1700', 'chipset' => 'B760', 'form_factor' => 'ATX', 'memory_type' => 'DDR5', 'memory_slots' => '4', 'max_memory' => '128', 'm2_slots' => '2'],
            'Gigabyte B650 AORUS Elite AX V2' => ['socket' => 'AM5', 'chipset' => 'B650', 'form_factor' => 'ATX', 'memory_type' => 'DDR5', 'memory_slots' => '4', 'max_memory' => '128', 'm2_slots' => '2'],
            'ASUS ROG Strix X670E-E Gaming WiFi' => ['socket' => 'AM5', 'chipset' => 'X670E', 'form_factor' => 'ATX', 'memory_type' => 'DDR5', 'memory_slots' => '4', 'max_memory' => '128', 'm2_slots' => '4'],

            // --- RAM ---
            'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000' => ['memory_type' => 'DDR5', 'capacity' => '32', 'speed' => '6000', 'kit_type' => '2x16GB'],
            'Corsair Vengeance 32GB (2x16GB) DDR5-5600' => ['memory_type' => 'DDR5', 'capacity' => '32', 'speed' => '5600', 'kit_type' => '2x16GB'],
            'Kingston Fury Beast 32GB (2x16GB) DDR5-5200' => ['memory_type' => 'DDR5', 'capacity' => '32', 'speed' => '5200', 'kit_type' => '2x16GB'],
            'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000' => ['memory_type' => 'DDR5', 'capacity' => '64', 'speed' => '6000', 'kit_type' => '2x32GB'],

            // --- VGA ---
            'ASUS ROG Strix RTX 4090 OC 24GB' => ['gpu_chip' => 'RTX 4090', 'vram' => '24', 'vram_type' => 'GDDR6X', 'tdp' => '450', 'length' => '358'],
            'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB' => ['gpu_chip' => 'RTX 4080 Super', 'vram' => '16', 'vram_type' => 'GDDR6X', 'tdp' => '320', 'length' => '340'],
            'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB' => ['gpu_chip' => 'RTX 4070 Ti Super', 'vram' => '16', 'vram_type' => 'GDDR6X', 'tdp' => '285', 'length' => '310'],
            'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB' => ['gpu_chip' => 'RTX 4070 Super', 'vram' => '12', 'vram_type' => 'GDDR6X', 'tdp' => '220', 'length' => '242'],
            'ASUS Dual RTX 4060 Ti OC 8GB' => ['gpu_chip' => 'RTX 4060 Ti', 'vram' => '8', 'vram_type' => 'GDDR6', 'tdp' => '160', 'length' => '267'],
            'Gigabyte RTX 4060 Eagle OC 8GB' => ['gpu_chip' => 'RTX 4060', 'vram' => '8', 'vram_type' => 'GDDR6', 'tdp' => '115', 'length' => '240'],
            'AMD Radeon RX 7900 XTX 24GB' => ['gpu_chip' => 'RX 7900 XTX', 'vram' => '24', 'vram_type' => 'GDDR6', 'tdp' => '355', 'length' => '340'],

            // --- PSU ---
            'Seasonic Focus GX-850 850W 80+ Gold' => ['wattage' => '850', 'efficiency' => '80+ Gold', 'modular' => 'Full Modular', 'form_factor' => 'ATX'],
            'Corsair RM1000x 1000W 80+ Gold' => ['wattage' => '1000', 'efficiency' => '80+ Gold', 'modular' => 'Full Modular', 'form_factor' => 'ATX'],
            'Seasonic Vertex GX-1200 1200W 80+ Gold' => ['wattage' => '1200', 'efficiency' => '80+ Gold', 'modular' => 'Full Modular', 'form_factor' => 'ATX'],

            // --- Case ---
            'Lian Li O11 Dynamic EVO' => ['form_factor' => 'ATX', 'max_gpu_length' => '420', 'max_cooler_height' => '167'],
            'NZXT H7 Flow' => ['form_factor' => 'ATX', 'max_gpu_length' => '400', 'max_cooler_height' => '185'],
            'Phanteks NV7 TG' => ['form_factor' => 'E-ATX', 'max_gpu_length' => '450', 'max_cooler_height' => '185'],

            // --- Cooler ---
            'Noctua NH-D15 chromax.black' => ['type' => 'Tower', 'socket_support' => 'LGA1700,AM5,AM4', 'tdp_rating' => '250', 'height' => '165'],
            'DeepCool LT720 AIO 360mm' => ['type' => 'AIO', 'socket_support' => 'LGA1700,AM5,AM4', 'tdp_rating' => '300', 'height' => '55', 'radiator_size' => '360'],
            'Corsair iCUE H150i Elite LCD XT 360mm' => ['type' => 'AIO', 'socket_support' => 'LGA1700,AM5,AM4', 'tdp_rating' => '350', 'height' => '55', 'radiator_size' => '360'],
        ];

        $counter = 1;
        foreach ($products as $data) {
            $product = Product::create([
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'component_type_id' => $data['component_type_id'],
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'sku' => 'SP' . str_pad($counter, 5, '0', STR_PAD_LEFT),
                'short_description' => $data['short_description'],
                'description' => '<p>' . $data['short_description'] . '</p><p>Sản phẩm chính hãng, bảo hành ' . $data['warranty_months'] . ' tháng. Miễn phí giao hàng toàn quốc.</p>',
                'price' => $data['price'],
                'sale_price' => $data['sale_price'],
                'cost_price' => (int) ($data['price'] * 0.75),
                'stock_quantity' => $data['stock_quantity'],
                'is_active' => true,
                'is_featured' => $data['is_featured'],
                'warranty_months' => $data['warranty_months'],
                'views_count' => rand(50, 2000),
                'sold_count' => rand(5, 200),
            ]);

            // Create a placeholder image 
            ProductImage::create([
                'product_id' => $product->id,
                'url' => 'https://placehold.co/600x600/1a1a2e/e94560?text=' . urlencode(Str::limit($product->name, 25)),
                'alt_text' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            // Seed compatibility specifications
            if ($product->component_type_id && isset($compatSpecs[$data['name']])) {
                $typeKeys = $specKeys->get($product->component_type_id, collect());
                foreach ($compatSpecs[$data['name']] as $key => $value) {
                    $specKey = $typeKeys->get($key);
                    if ($specKey) {
                        $isNumeric = in_array($specKey->data_type, ['integer', 'decimal']);
                        ProductSpecification::create([
                            'product_id' => $product->id,
                            'specification_key_id' => $specKey->id,
                            'value_string' => $isNumeric ? null : $value,
                            'value_numeric' => $isNumeric ? (float) $value : null,
                        ]);
                    }
                }
            }

            $counter++;
        }
    }
}
