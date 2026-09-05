<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HomepageReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBanners();
        $this->ensureProductCoverage();
        $this->seedTestimonials();

        $this->command->info('Homepage reference data seeded.');
    }

    private function seedBanners(): void
    {
        $banners = [
            [
                'title' => 'Build PC đỉnh cao - Chiến game cực đã',
                'description' => 'Tự chọn linh kiện theo nhu cầu và ngân sách của bạn.',
                'badge' => 'Build PC',
                'image' => 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1600&h=720&fit=crop',
                'link' => '/cau-hinh',
                'position' => 'hero',
                'sort_order' => -30,
                'is_active' => true,
                'metadata' => [
                    'cta_label' => 'Bắt đầu build PC',
                    'cta_link' => '/cau-hinh',
                ],
            ],
            [
                'title' => 'Laptop Gaming - Hiệu năng đỉnh cao',
                'description' => 'Laptop gaming chính hãng cho mọi cuộc chơi.',
                'badge' => 'Laptop Gaming',
                'image' => 'https://images.unsplash.com/photo-1593642532400-2682810df593?w=1600&h=720&fit=crop',
                'link' => '/categories/laptop-gaming',
                'position' => 'hero',
                'sort_order' => -29,
                'is_active' => true,
                'metadata' => [
                    'cta_label' => 'Xem laptop gaming',
                    'cta_link' => '/categories/laptop-gaming',
                ],
            ],
            [
                'title' => 'GeForce RTX - Sức mạnh đồ họa',
                'description' => 'Nâng tầm trải nghiệm gaming và sáng tạo nội dung.',
                'badge' => 'GPU HOT',
                'image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=1600&h=720&fit=crop',
                'link' => '/categories/vga',
                'position' => 'hero',
                'sort_order' => -28,
                'is_active' => true,
                'metadata' => [
                    'cta_label' => 'Xem card màn hình',
                    'cta_link' => '/categories/vga',
                ],
            ],
            [
                'title' => 'Laptop Gaming - Ưu đãi mỗi ngày',
                'description' => 'Chọn laptop phù hợp với bạn.',
                'badge' => 'ƯU ĐÃI',
                'image' => 'https://images.unsplash.com/photo-1593642532973-d31b6557fa68?w=900&h=430&fit=crop',
                'link' => '/categories/laptop-gaming',
                'position' => 'sidebar',
                'sort_order' => -30,
                'is_active' => true,
            ],
            [
                'title' => 'GeForce RTX 40 Series',
                'description' => 'Sức mạnh đồ họa cho game thủ.',
                'badge' => 'GPU',
                'image' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=900&h=430&fit=crop',
                'link' => '/categories/vga',
                'position' => 'sidebar',
                'sort_order' => -29,
                'is_active' => true,
            ],
            [
                'title' => 'Combo Gear Gaming',
                'description' => 'Góc chơi game đầy đủ, sẵn sàng chiến.',
                'badge' => 'COMBO',
                'image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=900&h=430&fit=crop',
                'link' => '/categories/phu-kien',
                'position' => 'sidebar',
                'sort_order' => -28,
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

    private function ensureProductCoverage(): void
    {
        $referenceProducts = [
            [
                'category_slug' => 'pc-gaming',
                'name' => 'PC Gaming Reference RTX 4070 SUPER',
                'price' => 29990000,
                'sale_price' => 26990000,
                'sku' => 'HPR-PC-4070S',
                'image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=720&h=720&fit=crop',
            ],
            [
                'category_slug' => 'laptop-gaming',
                'name' => 'Laptop Gaming Reference RTX 4060',
                'price' => 32990000,
                'sale_price' => 29990000,
                'sku' => 'HPR-LAP-4060',
                'image' => 'https://images.unsplash.com/photo-1593642702749-b7d2a804fbcf?w=720&h=720&fit=crop',
            ],
            [
                'category_slug' => 'vga',
                'name' => 'GIGABYTE GeForce RTX 4070 SUPER Reference',
                'price' => 17990000,
                'sale_price' => 15990000,
                'sku' => 'HPR-VGA-4070S',
                'image' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=720&h=720&fit=crop',
            ],
        ];

        foreach ($referenceProducts as $data) {
            $category = Category::query()->where('slug', $data['category_slug'])->first();
            if (! $category) {
                continue;
            }

            $product = Product::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'sku' => $data['sku'],
                    'short_description' => 'Sản phẩm tham khảo cho homepage PC Center.',
                    'description' => '<p>Sản phẩm chính hãng, bảo hành theo chính sách của PC Center.</p>',
                    'price' => $data['price'],
                    'sale_price' => $data['sale_price'],
                    'cost_price' => (int) ($data['price'] * 0.75),
                    'stock_quantity' => 20,
                    'is_active' => true,
                    'is_featured' => true,
                    'warranty_months' => 24,
                    'sold_count' => 180,
                    'views_count' => 800,
                    'inventory_source' => 'local',
                    'show_on_pc_website' => true,
                ],
            );

            ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'sort_order' => 0],
                [
                    'url' => $data['image'],
                    'alt_text' => $product->name,
                    'is_primary' => true,
                ],
            );
        }
    }

    private function seedTestimonials(): void
    {
        $products = Product::query()
            ->where('is_active', true)
            ->where('show_on_pc_website', true)
            ->orderBy('id')
            ->limit(4)
            ->get();
        if ($products->isEmpty()) {
            return;
        }

        $testimonials = [
            ['Nguyễn Hoàng Nam', 'nam.homepage@example.test', 'PC chạy rất mượt, tư vấn đúng nhu cầu và giao hàng nhanh.'],
            ['Trần Minh Quân', 'quan.homepage@example.test', 'Nhân viên hỗ trợ nhiệt tình, build PC tương thích ngay từ đầu.'],
            ['Phạm Thị Linh', 'linh.homepage@example.test', 'Laptop đóng gói cẩn thận, trải nghiệm mua hàng rất tốt.'],
            ['Lê Đức Anh', 'anh.homepage@example.test', 'Sản phẩm đúng mô tả, giá hợp lý và hỗ trợ sau mua chu đáo.'],
        ];

        foreach ($testimonials as $index => [$name, $email, $body]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(32)),
                ],
            );
            $product = $products[$index % $products->count()];

            Review::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $product->id],
                [
                    'rating' => 5,
                    'title' => 'Khách hàng hài lòng',
                    'body' => $body,
                    'is_approved' => true,
                ],
            );
        }
    }
}
