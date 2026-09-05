<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageMerchandisingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBanners();
        $this->seedCategoryIcons();

        $this->command?->info('Homepage merchandising assets seeded.');
    }

    private function seedBanners(): void
    {
        $banners = [
            [
                'title' => 'Build PC đỉnh cao - Chiến game cực đã',
                'description' => 'Tự chọn linh kiện theo nhu cầu và ngân sách của bạn.',
                'badge' => 'BUILD PC',
                'image' => 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1600&h=720&fit=crop',
                'link' => '/cau-hinh',
                'position' => 'hero',
                'sort_order' => 0,
                'is_active' => true,
                'metadata' => ['cta_label' => 'Bắt đầu build PC', 'cta_link' => '/cau-hinh'],
            ],
            [
                'title' => 'Laptop Gaming - Hiệu năng đỉnh cao',
                'description' => 'Laptop gaming chính hãng cho mọi cuộc chơi.',
                'badge' => 'LAPTOP GAMING',
                'image' => 'https://images.unsplash.com/photo-1593642532400-2682810df593?w=1600&h=720&fit=crop',
                'link' => '/categories/laptop-gaming',
                'position' => 'hero',
                'sort_order' => 1,
                'is_active' => true,
                'metadata' => ['cta_label' => 'Xem laptop gaming', 'cta_link' => '/categories/laptop-gaming'],
            ],
            [
                'title' => 'GeForce RTX - Sức mạnh đồ họa',
                'description' => 'Nâng tầm trải nghiệm gaming và sáng tạo nội dung.',
                'badge' => 'GPU HOT',
                'image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=1600&h=720&fit=crop',
                'link' => '/categories/vga',
                'position' => 'hero',
                'sort_order' => 2,
                'is_active' => true,
                'metadata' => ['cta_label' => 'Xem card màn hình', 'cta_link' => '/categories/vga'],
            ],
            [
                'title' => 'Linh kiện PC chính hãng',
                'description' => 'Nâng cấp hiệu năng với linh kiện đồng bộ, bảo hành rõ ràng.',
                'badge' => 'LINH KIỆN',
                'image' => 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=1600&h=720&fit=crop',
                'link' => '/categories/linh-kien-pc',
                'position' => 'hero',
                'sort_order' => 3,
                'is_active' => true,
                'metadata' => ['cta_label' => 'Xem linh kiện', 'cta_link' => '/categories/linh-kien-pc'],
            ],
            [
                'title' => 'Laptop Gaming - Ưu đãi mỗi ngày',
                'description' => 'Chọn laptop phù hợp với bạn.',
                'badge' => 'ƯU ĐÃI',
                'image' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=900&h=430&fit=crop',
                'link' => '/categories/laptop-gaming',
                'position' => 'sidebar',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'GeForce RTX 40 Series',
                'description' => 'Sức mạnh đồ họa cho game thủ.',
                'badge' => 'GPU',
                'image' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=900&h=430&fit=crop',
                'link' => '/categories/vga',
                'position' => 'sidebar',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Combo Gear Gaming',
                'description' => 'Góc chơi game đầy đủ, sẵn sàng chiến.',
                'badge' => 'COMBO',
                'image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=900&h=430&fit=crop',
                'link' => '/categories/phu-kien',
                'position' => 'sidebar',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Màn hình và phụ kiện nổi bật',
                'description' => 'Hoàn thiện góc máy với thiết bị phù hợp.',
                'badge' => 'SETUP',
                'image' => 'https://images.unsplash.com/photo-1593642532744-d377ab507dc8?w=900&h=430&fit=crop',
                'link' => '/categories/man-hinh',
                'position' => 'sidebar',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'PC Builder - Lắp PC theo phong cách của bạn',
                'description' => 'Kiểm tra tương thích, công suất và lưu cấu hình ngay trên website.',
                'badge' => 'PC BUILDER',
                'image' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=1600&h=360&fit=crop',
                'link' => '/cau-hinh',
                'position' => 'pc_builder',
                'sort_order' => 0,
                'is_active' => true,
                'metadata' => [
                    'cta_label' => 'Bắt đầu build PC ngay',
                    'cta_link' => '/cau-hinh',
                    'cta2_label' => 'Hướng dẫn build PC',
                    'cta2_link' => '/tin-tuc',
                ],
            ],
            [
                'title' => 'Combo siêu ưu đãi',
                'description' => 'Mua combo - tiết kiệm hơn mỗi ngày.',
                'badge' => 'COMBO',
                'image' => 'https://images.unsplash.com/photo-1593640495253-23196b27a87f?w=1000&h=640&fit=crop',
                'link' => '/categories/phu-kien',
                'position' => 'combo',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Setup RGB Legend',
                'description' => 'Không gian gaming rực rỡ.',
                'badge' => 'SETUP',
                'image' => 'https://images.unsplash.com/photo-1593062096033-9a26b09da705?w=700&h=500&fit=crop',
                'link' => '/tin-tuc',
                'position' => 'setup_inspiration',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Minimal Black',
                'description' => 'Tối giản và mạnh mẽ.',
                'badge' => 'SETUP',
                'image' => 'https://images.unsplash.com/photo-1616588589676-62b3bd4ff6d2?w=700&h=500&fit=crop',
                'link' => '/tin-tuc',
                'position' => 'setup_inspiration',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'White & Clean',
                'description' => 'Gọn gàng, sáng tạo.',
                'badge' => 'SETUP',
                'image' => 'https://images.unsplash.com/photo-1593642532744-d377ab507dc8?w=700&h=500&fit=crop',
                'link' => '/tin-tuc',
                'position' => 'setup_inspiration',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Stream Pro',
                'description' => 'Sẵn sàng cho mọi buổi stream.',
                'badge' => 'SETUP',
                'image' => 'https://images.unsplash.com/photo-1598550476439-6847785fcea6?w=700&h=500&fit=crop',
                'link' => '/tin-tuc',
                'position' => 'setup_inspiration',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::updateOrCreate(
                ['title' => $banner['title'], 'position' => $banner['position']],
                $banner,
            );
        }
    }

    private function seedCategoryIcons(): void
    {
        Storage::disk('public')->makeDirectory('icons');

        foreach (Category::query()->get() as $category) {
            $type = $this->iconType($category);
            $path = 'icons/category-'.$category->slug.'.svg';
            Storage::disk('public')->put($path, $this->iconSvg($type));
            $category->update(['icon' => Storage::disk('public')->url($path)]);
        }
    }

    private function iconType(Category $category): string
    {
        $text = Str::lower($category->slug.' '.$category->name);

        return match (true) {
            Str::contains($text, ['laptop', 'notebook']) => 'laptop',
            Str::contains($text, ['màn hình', 'man-hinh', 'monitor']) => 'display',
            Str::contains($text, ['bàn phím', 'ban-phim', 'keyboard']) => 'keyboard',
            Str::contains($text, ['chuột', 'chuot', 'mouse']) => 'mouse',
            Str::contains($text, ['tai nghe', 'tai-nghe', 'headset']) => 'headset',
            Str::contains($text, ['mạng', 'mang', 'network', 'wifi', 'router']) => 'network',
            Str::contains($text, ['cpu', 'bộ xử lý', 'bo-xu-ly']) => 'cpu',
            Str::contains($text, ['ram', 'bộ nhớ', 'bo-nho']) => 'memory',
            Str::contains($text, ['ssd', 'hdd', 'ổ cứng', 'o-cung']) => 'storage',
            Str::contains($text, ['psu', 'nguồn', 'nguon']) => 'power',
            Str::contains($text, ['vga', 'card màn hình', 'card-man-hinh']) => 'gpu',
            Str::contains($text, ['ghế', 'ghe', 'chair']) => 'chair',
            Str::contains($text, ['case', 'vỏ máy', 'vo-may']) => 'case',
            Str::contains($text, ['pc', 'máy tính', 'may-tinh']) => 'desktop',
            default => 'accessories',
        };
    }

    private function iconSvg(string $type): string
    {
        $shapes = [
            'desktop' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
            'laptop' => '<path d="M5 5h14v10H5z"/><path d="M3 18h18l-2 2H5z"/>',
            'display' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'keyboard' => '<rect x="2" y="7" width="20" height="10" rx="2"/><path d="M6 11h.01M10 11h.01M14 11h.01M18 11h.01M6 14h12"/>',
            'mouse' => '<rect x="7" y="3" width="10" height="18" rx="5"/><path d="M12 3v6"/>',
            'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><path d="M4 14h3v5H5a1 1 0 0 1-1-1zM20 14h-3v5h2a1 1 0 0 0 1-1z"/>',
            'network' => '<rect x="3" y="5" width="18" height="12" rx="2"/><path d="M7 10h.01M11 10h.01M15 10h.01M19 10h.01M8 14h8"/>',
            'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
            'memory' => '<rect x="2" y="8" width="20" height="9" rx="2"/><path d="M6 11v2M10 11v2M14 11v2M18 11v2M6 17v3M10 17v3M14 17v3M18 17v3"/>',
            'storage' => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="15" r="3"/><path d="M8 7h8"/>',
            'power' => '<path d="M12 2v9M7 5a8 8 0 1 0 10 0"/>',
            'gpu' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="8" cy="12" r="3"/><path d="M15 10h4M15 14h4"/>',
            'chair' => '<path d="M7 11V7a5 5 0 0 1 10 0v4M4 11h16v7H4zM7 18v3M17 18v3"/>',
            'case' => '<rect x="6" y="2" width="12" height="20" rx="2"/><circle cx="12" cy="6" r="1"/><path d="M9 11h6v5H9z"/>',
            'accessories' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2M12 2v2M12 20v2M2 12h2M20 12h2"/>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#1264d8" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$shapes[$type].'</svg>';
    }
}
