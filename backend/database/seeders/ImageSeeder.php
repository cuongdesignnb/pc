<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ImageSeeder extends Seeder
{
    /**
     * Seed images for the entire application.
     * Uses external image services for a realistic demo:
     * - Products: picsum.photos (random photos)
     * - Brands: logo.clearbit.com (real brand logos) + ui-avatars fallback
     * - Categories: SVG icons copied from sample-icons/
     * - Posts: picsum.photos
     * - Banners: picsum.photos (wide images)
     */
    public function run(): void
    {
        $this->seedBrandLogos();
        $this->seedCategoryIcons();
        $this->seedProductImages();
        $this->seedPostImages();
        $this->seedBanners();

        $this->command->info('✅ All images seeded successfully!');
    }

    /**
     * Seed brand logos using logo.clearbit.com for real logos.
     */
    private function seedBrandLogos(): void
    {
        $brandDomains = [
            'Intel'            => 'intel.com',
            'AMD'              => 'amd.com',
            'NVIDIA'           => 'nvidia.com',
            'ASUS'             => 'asus.com',
            'MSI'              => 'msi.com',
            'Gigabyte'         => 'gigabyte.com',
            'EVGA'             => 'evga.com',
            'Zotac'            => 'zotac.com',
            'Colorful'         => 'colorful.cn',
            'Galax'            => 'galax.com',
            'ASRock'           => 'asrock.com',
            'G.Skill'          => 'gskill.com',
            'Kingston'         => 'kingston.com',
            'Corsair'          => 'corsair.com',
            'TeamGroup'        => 'teamgroupinc.com',
            'Crucial'          => 'crucial.com',
            'Samsung'          => 'samsung.com',
            'Western Digital'  => 'westerndigital.com',
            'Seagate'          => 'seagate.com',
            'SK Hynix'         => 'skhynix.com',
            'Lexar'            => 'lexar.com',
            'Seasonic'         => 'seasonic.com',
            'NZXT'             => 'nzxt.com',
            'Cooler Master'    => 'coolermaster.com',
            'Thermaltake'      => 'thermaltake.com',
            'Be Quiet!'        => 'bequiet.com',
            'Super Flower'     => 'superflower.com.tw',
            'Lian Li'          => 'lian-li.com',
            'Phanteks'         => 'phanteks.com',
            'Fractal Design'   => 'fractal-design.com',
            'Noctua'           => 'noctua.at',
            'Arctic'           => 'arctic.de',
            'DeepCool'         => 'deepcool.com',
            'ID-Cooling'       => 'idcooling.com',
            'Logitech'         => 'logitech.com',
            'Razer'            => 'razer.com',
            'SteelSeries'      => 'steelseries.com',
            'HyperX'           => 'hyperx.com',
            'Ducky'            => 'duckychannel.com.tw',
            'Keychron'         => 'keychron.com',
            'LG'               => 'lg.com',
            'Dell'             => 'dell.com',
            'BenQ'             => 'benq.com',
            'AOC'              => 'aoc.com',
            'ViewSonic'        => 'viewsonic.com',
            'Lenovo'           => 'lenovo.com',
            'HP'               => 'hp.com',
            'Acer'             => 'acer.com',
            'Apple'            => 'apple.com',
        ];

        foreach ($brandDomains as $name => $domain) {
            Brand::where('name', $name)->update([
                'logo' => "https://logo.clearbit.com/{$domain}",
            ]);
        }

        $this->command->info('  → Brand logos seeded (' . count($brandDomains) . ' brands)');
    }

    /**
     * Seed category icons.
     * All SVGs are embedded directly — no external file dependency.
     * Saves SVG files to public storage disk and stores the URL.
     */
    private function seedCategoryIcons(): void
    {
        // Ensure storage directory exists
        Storage::disk('public')->makeDirectory('icons');

        // All SVGs embedded: slug => SVG content
        $categoryIcons = [
            // === Component types (Linh kiện PC children) ===
            'cpu' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',

            'mainboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><rect x="13" y="13" width="4" height="4"/></svg>',

            'ram' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="8" width="20" height="10" rx="2"/><path d="M6 11v2"/><path d="M10 11v2"/><path d="M14 11v2"/><path d="M18 11v2"/></svg>',

            'vga' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h3"/><path d="M6 14h3"/><path d="M15 10h3"/><path d="M15 14h3"/><circle cx="12" cy="12" r="2"/></svg>',

            'ssd' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M6 10h2"/><path d="M6 14h2"/><path d="M10 10v4"/><circle cx="16" cy="12" r="2"/></svg>',

            'hdd' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M6 10h2"/><path d="M6 14h2"/><path d="M10 10v4"/><circle cx="16" cy="12" r="2"/></svg>',

            'psu' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',

            'case' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2"/><circle cx="12" cy="6" r="1"/><rect x="9" y="10" width="6" height="4" rx="1"/><path d="M9 18h6"/></svg>',

            'cooler' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 1 1 4 0Z"/></svg>',

            'fan' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c-1.5-3-4-4.5-7-4 1.5 3 4 4.5 7 4z"/><path d="M12 12c3-1.5 4.5-4 4-7-3 1.5-4.5 4-4 7z"/><path d="M12 12c1.5 3 4 4.5 7 4-1.5-3-4-4.5-7-4z"/><path d="M12 12c-3 1.5-4.5 4-4 7 3-1.5 4.5-4 4-7z"/><circle cx="12" cy="12" r="1"/></svg>',

            // === Peripherals (Phụ kiện children) ===
            'man-hinh' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>',

            'ban-phim' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01"/><path d="M10 10h.01"/><path d="M14 10h.01"/><path d="M18 10h.01"/><path d="M6 14h.01"/><path d="M10 14h.01"/><path d="M14 14h.01"/><path d="M18 14h.01"/></svg>',

            'chuot' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c-2.8 0-5 2.2-5 5v8c0 2.8 2.2 5 5 5s5-2.2 5-5V7c0-2.8-2.2-5-5-5z"/><path d="M12 2v8"/></svg>',

            'tai-nghe' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>',

            'loa' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>',

            'webcam' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="5"/><circle cx="12" cy="10" r="2"/><path d="M12 15v5"/><path d="M8 20h8"/></svg>',

            'ban-gaming' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="3" rx="1"/><path d="M6 10v7"/><path d="M18 10v7"/><path d="M4 17h16"/></svg>',

            'ghe-gaming' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="4" y="11" width="16" height="7" rx="2"/><path d="M6 18v3"/><path d="M18 18v3"/></svg>',

            // === Main categories (parents) ===
            'pc-may-tinh-de-ban' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',

            'laptop' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',

            'linh-kien-pc' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',

            'phu-kien' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/><path d="M12 6v6l4 2"/></svg>',

            // === PC subcategories ===
            'pc-gaming' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="12" x2="18" y2="12"/><line x1="12" y1="6" x2="12" y2="18"/><rect x="2" y="6" width="20" height="12" rx="2"/></svg>',

            'pc-van-phong' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',

            'pc-do-hoa-render' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',

            // === Laptop subcategories ===
            'laptop-gaming' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',

            'laptop-van-phong' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',

            'laptop-do-hoa' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',
        ];

        $count = 0;
        foreach ($categoryIcons as $slug => $svgContent) {
            $destPath = "icons/{$slug}.svg";
            Storage::disk('public')->put($destPath, $svgContent);
            $iconUrl = Storage::disk('public')->url($destPath);
            $updated = Category::where('slug', $slug)->update(['icon' => $iconUrl]);
            if ($updated) {
                $count++;
            }
        }

        $this->command->info("  → Category icons seeded ({$count} categories)");
    }

    /**
     * Seed product images using picsum.photos.
     * Maps product categories to themed image IDs for more relevant visuals.
     */
    private function seedProductImages(): void
    {
        // Curated picsum image IDs grouped by product type
        // These are tech/abstract/dark photos that look good for a PC store
        $imagePool = [
            'cpu' => [
                'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop',
            ],
            'vga' => [
                'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop',
            ],
            'mainboard' => [
                'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop',
            ],
            'ram' => [
                'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?w=600&h=600&fit=crop',
            ],
            'ssd' => [
                'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1531492746076-161ca9bcad09?w=600&h=600&fit=crop',
            ],
            'psu' => [
                'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=600&h=600&fit=crop',
            ],
            'case' => [
                'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop',
            ],
            'cooler' => [
                'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop',
            ],
            'pc' => [
                'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop',
            ],
            'laptop' => [
                'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop',
            ],
            'monitor' => [
                'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?w=600&h=600&fit=crop',
            ],
            'keyboard' => [
                'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=600&h=600&fit=crop',
            ],
            'mouse' => [
                'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=600&h=600&fit=crop',
            ],
            'headset' => [
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=600&h=600&fit=crop',
            ],
            'chair' => [
                'https://images.unsplash.com/photo-1589384267710-7a170981ca78?w=600&h=600&fit=crop',
            ],
            'generic' => [
                'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop',
            ],
        ];

        // Map category_id to image pool keys
        $categoryImageMap = [
            11 => 'cpu',        // CPU
            12 => 'mainboard',  // Mainboard
            13 => 'ram',        // RAM
            14 => 'vga',        // VGA
            15 => 'ssd',        // SSD
            17 => 'psu',        // PSU
            18 => 'case',       // Case
            19 => 'cooler',     // Cooler
            5  => 'pc',         // PC Gaming
            6  => 'pc',         // PC Văn phòng
            7  => 'pc',         // PC Đồ họa
            8  => 'laptop',     // Laptop Gaming
            9  => 'laptop',     // Laptop Văn phòng
            21 => 'monitor',    // Màn hình
            22 => 'keyboard',   // Bàn phím
            23 => 'mouse',      // Chuột
            24 => 'headset',    // Tai nghe
            28 => 'chair',      // Ghế Gaming
        ];

        $products = Product::with('images')->get();
        $count = 0;

        foreach ($products as $product) {
            $poolKey = $categoryImageMap[$product->category_id] ?? 'generic';
            $images = $imagePool[$poolKey] ?? $imagePool['generic'];

            // Pick image based on product ID for consistency
            $primaryUrl = $images[$product->id % count($images)];

            // Update existing primary image or create one
            $existing = $product->images()->where('is_primary', true)->first();
            if ($existing) {
                $existing->update(['url' => $primaryUrl]);
            } else {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $primaryUrl,
                    'alt_text' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);
            }

            // Add 2-3 additional gallery images from the pool + other pools
            $galleryImages = [];
            $allPools = array_merge($images, $imagePool['generic']);
            $allPools = array_unique($allPools);

            // Pick different images for gallery (skip the primary one)
            $galleryPick = 0;
            foreach ($allPools as $url) {
                if ($url === $primaryUrl) continue;
                if ($galleryPick >= 2) break;
                $galleryImages[] = $url;
                $galleryPick++;
            }

            // Remove old non-primary images and recreate
            $product->images()->where('is_primary', false)->delete();
            foreach ($galleryImages as $idx => $url) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $url,
                    'alt_text' => $product->name . ' - Hình ' . ($idx + 2),
                    'sort_order' => $idx + 1,
                    'is_primary' => false,
                ]);
            }

            $count++;
        }

        $this->command->info("  → Product images seeded ({$count} products, each with 2-3 images)");
    }

    /**
     * Seed post featured images.
     */
    private function seedPostImages(): void
    {
        $postImages = [
            'top-5-cau-hinh-pc-gaming-tam-trung-2026'
                => 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1200&h=600&fit=crop',
            'rtx-5080-chinh-thuc-ra-mat-danh-gia-hieu-nang'
                => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=1200&h=600&fit=crop',
            'huong-dan-toi-uu-hieu-nang-windows-11-cho-gaming'
                => 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=1200&h=600&fit=crop',
            'amd-ryzen-9-7950x3d-vs-intel-core-i9-14900k'
                => 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=1200&h=600&fit=crop',
            'tin-tuc-ddr5-giam-gia-manh-trong-q12026'
                => 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=1200&h=600&fit=crop',
            '10-game-aaa-dang-choi-nhat-nam-2026'
                => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=1200&h=600&fit=crop',
            'cach-chon-nguon-may-tinh-phu-hop'
                => 'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=1200&h=600&fit=crop',
            'ssd-nvme-gen-5-co-dang-de-nang-cap'
                => 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=1200&h=600&fit=crop',
        ];

        $count = 0;
        foreach ($postImages as $slug => $url) {
            $updated = Post::where('slug', $slug)->update(['featured_image' => $url]);
            $count += $updated;
        }

        // Fallback: update any post without featured image
        $postsWithout = Post::whereNull('featured_image')->get();
        $fallbackUrls = [
            'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&h=600&fit=crop',
            'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=1200&h=600&fit=crop',
            'https://images.unsplash.com/photo-1531297484001-80022131f5a1?w=1200&h=600&fit=crop',
        ];
        foreach ($postsWithout as $idx => $post) {
            $post->update([
                'featured_image' => $fallbackUrls[$idx % count($fallbackUrls)],
            ]);
            $count++;
        }

        $this->command->info("  → Post featured images seeded ({$count} posts)");
    }

    /**
     * Seed banner images.
     */
    private function seedBanners(): void
    {
        $banners = [
            [
                'title' => 'Xây dựng PC<br>trong mơ của bạn',
                'description' => 'Công cụ build cấu hình thông minh — kiểm tra tương thích tự động, xem TDP & giá ngay lập tức.',
                'badge' => 'Hot Deal',
                'image' => 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1920&h=600&fit=crop',
                'link' => '/configurator',
                'position' => 'hero',
                'sort_order' => 1,
                'is_active' => true,
                'metadata' => [
                    'gradient' => 'from-indigo-600 via-purple-600 to-pink-500',
                    'cta_label' => 'Build PC ngay',
                    'cta_link' => '/configurator',
                    'cta2_label' => 'Xem sản phẩm',
                    'cta2_link' => '/products',
                    'glow_a' => 'bg-white',
                    'glow_b' => 'bg-yellow-300',
                ],
            ],
            [
                'title' => 'PC Gaming<br>hiệu năng khủng',
                'description' => 'Cấu hình mạnh mẽ với RTX 50 Series, Intel Gen 15 & AMD Ryzen 9000. Sẵn sàng chiến mọi tựa game.',
                'badge' => 'PC Gaming',
                'image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=1920&h=600&fit=crop',
                'link' => '/categories/pc-gaming',
                'position' => 'hero',
                'sort_order' => 2,
                'is_active' => true,
                'metadata' => [
                    'gradient' => 'from-red-600 via-orange-600 to-amber-500',
                    'cta_label' => 'Xem PC Gaming',
                    'cta_link' => '/categories/pc-gaming',
                    'cta2_label' => 'Build cấu hình',
                    'cta2_link' => '/configurator',
                    'glow_a' => 'bg-orange-300',
                    'glow_b' => 'bg-red-400',
                ],
            ],
            [
                'title' => 'Laptop chính hãng<br>giá tốt nhất',
                'description' => 'Laptop Gaming, Đồ họa, Văn phòng — đa dạng thương hiệu, bảo hành toàn quốc.',
                'badge' => 'Laptop',
                'image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=1920&h=600&fit=crop',
                'link' => '/categories/laptop',
                'position' => 'hero',
                'sort_order' => 3,
                'is_active' => true,
                'metadata' => [
                    'gradient' => 'from-emerald-600 via-teal-600 to-cyan-500',
                    'cta_label' => 'Xem Laptop',
                    'cta_link' => '/categories/laptop',
                    'cta2_label' => 'So sánh giá',
                    'cta2_link' => '/products',
                    'glow_a' => 'bg-cyan-300',
                    'glow_b' => 'bg-emerald-300',
                ],
            ],
            [
                'title' => 'Giảm đến 40%<br>linh kiện PC',
                'description' => 'RAM, SSD, VGA, PSU — hàng chính hãng giá sốc. Số lượng có hạn, mua ngay!',
                'badge' => 'Flash Sale',
                'image' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=1920&h=600&fit=crop',
                'link' => '/categories/linh-kien-pc',
                'position' => 'hero',
                'sort_order' => 4,
                'is_active' => true,
                'metadata' => [
                    'gradient' => 'from-pink-600 via-rose-600 to-red-500',
                    'cta_label' => 'Mua ngay',
                    'cta_link' => '/categories/linh-kien-pc',
                    'cta2_label' => 'Xem tất cả',
                    'cta2_link' => '/products',
                    'glow_a' => 'bg-pink-300',
                    'glow_b' => 'bg-rose-400',
                ],
            ],
            [
                'title' => 'Flash Sale - Giảm đến 30%',
                'description' => 'Ưu đãi hấp dẫn cho linh kiện máy tính',
                'badge' => 'Sale',
                'image' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=800&h=400&fit=crop',
                'link' => '/products?sort=sale',
                'position' => 'sidebar',
                'sort_order' => 1,
                'is_active' => true,
                'metadata' => null,
            ],
            [
                'title' => 'Phụ kiện Gaming chính hãng',
                'description' => 'Bàn phím, chuột, tai nghe gaming hàng đầu',
                'badge' => 'Gaming',
                'image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=800&h=400&fit=crop',
                'link' => '/categories/phu-kien',
                'position' => 'sidebar',
                'sort_order' => 2,
                'is_active' => true,
                'metadata' => null,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::updateOrCreate(
                ['title' => $banner['title']],
                $banner
            );
        }

        $this->command->info('  → Banners seeded (' . count($banners) . ' banners)');
    }
}
