<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class NewsReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first();
        if (! $author) {
            $this->command->warn('No users found. News reference posts were not seeded.');

            return;
        }

        $posts = [
            [
                'slug' => 'nvidia-chinh-thuc-ra-mat-geforce-rtx-50-series-hieu-nang-vuot-troi',
                'category_slug' => 'tin-tuc-cong-nghe',
                'title' => 'NVIDIA chính thức ra mắt GeForce RTX 50 Series: Hiệu năng đột phá',
                'excerpt' => 'Kiến trúc Blackwell mới mang đến hiệu năng vượt trội, hỗ trợ DLSS 4 và nhiều công nghệ AI tiên tiến.',
                'view_count' => 12500,
                'is_featured' => true,
                'published_at' => '2026-02-07 09:30:00',
                'featured_image' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?auto=format&fit=crop&w=1400&q=85',
            ],
            [
                'slug' => 'asus-rog-zephyrus-g16-2026-danh-gia-chi-tiet',
                'category_slug' => 'review-san-pham',
                'title' => 'Đánh giá ASUS ROG Zephyrus G16 2026: Thiết kế mỏng nhẹ, hiệu năng đỉnh cao',
                'excerpt' => 'Mẫu laptop gaming cao cấp cân bằng tốt giữa tính di động, màn hình đẹp và sức mạnh xử lý.',
                'view_count' => 8200,
                'is_featured' => true,
                'published_at' => '2026-02-05 14:10:00',
                'featured_image' => 'https://images.unsplash.com/photo-1593642702749-b7d2a804fbcf?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'huong-dan-build-pc-gaming-20-trieu-toi-uu',
                'category_slug' => 'huong-dan-build-pc',
                'title' => 'Hướng dẫn build PC gaming 20 triệu: Cấu hình tối ưu cho mọi tựa game',
                'excerpt' => 'Gợi ý linh kiện cân bằng hiệu năng và ngân sách, sẵn sàng cho gaming 2K trong năm nay.',
                'view_count' => 15100,
                'is_featured' => true,
                'published_at' => '2026-02-03 08:45:00',
                'featured_image' => 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'lg-ultragear-oled-27gr95qe-trai-nghiem-240hz',
                'category_slug' => 'review-san-pham',
                'title' => 'Đánh giá LG UltraGear OLED 27GR95QE: Màn hình 240Hz cho game thủ',
                'excerpt' => 'Tấm nền OLED cho màu sắc rực rỡ, tốc độ phản hồi nhanh và trải nghiệm game cực đã.',
                'view_count' => 9400,
                'is_featured' => true,
                'published_at' => '2026-02-02 11:20:00',
                'featured_image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'intel-core-ultra-200s-chinh-thuc-ra-mat',
                'category_slug' => 'tin-tuc-cong-nghe',
                'title' => 'Intel Core Ultra 200S chính thức ra mắt: Hiệu năng AI cải thiện vượt bậc',
                'excerpt' => 'Dòng vi xử lý mới tập trung vào hiệu năng đa nhân, tiết kiệm điện và khả năng xử lý AI.',
                'view_count' => 7800,
                'is_featured' => true,
                'published_at' => '2026-02-01 10:00:00',
                'featured_image' => 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'tet-sale-lon-pc-laptop-giam-den-50',
                'category_slug' => 'khuyen-mai',
                'title' => 'Tết Sale Lớn: PC và laptop giảm đến 50%',
                'excerpt' => 'Tổng hợp những chương trình ưu đãi nổi bật cho mùa mua sắm đầu năm.',
                'view_count' => 22100,
                'is_featured' => false,
                'published_at' => '2026-01-28 09:00:00',
                'featured_image' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'lenovo-legion-pro-7i-2026-co-dang-mua',
                'category_slug' => 'laptop',
                'title' => 'Lenovo Legion Pro 7i 2026: Sức mạnh cho game thủ chuyên nghiệp',
                'excerpt' => 'CPU mạnh, GPU rời và hệ thống tản nhiệt được nâng cấp cho những phiên chơi dài.',
                'view_count' => 4300,
                'is_featured' => false,
                'published_at' => '2026-01-26 15:30:00',
                'featured_image' => 'https://images.unsplash.com/photo-1593642702749-b7d2a804fbcf?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'build-pc-do-hoa-30-trieu-cho-designer-editor',
                'category_slug' => 'huong-dan-build-pc',
                'title' => 'Build PC đồ họa 30 triệu: Lựa chọn tối ưu cho designer, editor',
                'excerpt' => 'Cấu hình chú trọng bộ nhớ, khả năng render và nâng cấp dài hạn cho công việc sáng tạo.',
                'view_count' => 6100,
                'is_featured' => false,
                'published_at' => '2026-01-24 13:15:00',
                'featured_image' => 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'amd-ryzen-9000-series-hieu-nang-da-nhan',
                'category_slug' => 'cpu',
                'title' => 'AMD Ryzen 9000 Series lộ thông số: Hiệu năng đa nhân ấn tượng',
                'excerpt' => 'Những nâng cấp đáng chú ý của thế hệ Ryzen mới dành cho gaming và công việc nặng.',
                'view_count' => 5800,
                'is_featured' => false,
                'published_at' => '2026-01-22 09:40:00',
                'featured_image' => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => '10-meo-toi-uu-windows-11-cho-pc-gaming',
                'category_slug' => 'meo-toi-uu-game',
                'title' => '10 mẹo tối ưu Windows 11 giúp PC chơi game mượt hơn',
                'excerpt' => 'Tinh chỉnh nhanh hệ thống, giảm độ trễ và giải phóng tài nguyên trước mỗi trận đấu.',
                'view_count' => 12700,
                'is_featured' => false,
                'published_at' => '2026-01-20 08:20:00',
                'featured_image' => 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'top-gaming-gear-dang-mua-2026',
                'category_slug' => 'gaming-gear',
                'title' => 'Top gaming gear đáng mua 2026 cho góc máy hiện đại',
                'excerpt' => 'Bàn phím, chuột và tai nghe giúp nâng cấp trải nghiệm mà vẫn kiểm soát ngân sách.',
                'view_count' => 9100,
                'is_featured' => false,
                'published_at' => '2026-01-18 16:00:00',
                'featured_image' => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'rtx-4070-super-van-con-dang-mua',
                'category_slug' => 'vga',
                'title' => 'RTX 4070 SUPER vẫn còn đáng mua trong năm 2026?',
                'excerpt' => 'Đánh giá hiệu năng thực tế, mức tiêu thụ điện và vị trí của GPU trong phân khúc tầm cao.',
                'view_count' => 14500,
                'is_featured' => false,
                'published_at' => '2026-01-16 10:35:00',
                'featured_image' => 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'ces-gaming-2026-nhung-cong-nghe-noi-bat',
                'category_slug' => 'gaming',
                'title' => 'CES Gaming 2026: Những công nghệ gaming nổi bật đáng chú ý',
                'excerpt' => 'Từ màn hình tốc độ cao đến thiết bị ngoại vi thế hệ mới, đây là các xu hướng nổi bật tại CES.',
                'view_count' => 11600,
                'is_featured' => false,
                'published_at' => '2026-01-14 14:50:00',
                'featured_image' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'ssd-gen-5-co-dang-de-nang-cap',
                'category_slug' => 'tin-tuc-cong-nghe',
                'title' => 'SSD Gen 5 có đáng để nâng cấp trong năm nay?',
                'excerpt' => 'So sánh tốc độ, nhiệt độ và chi phí để biết khi nào SSD PCIe 5.0 thực sự phù hợp.',
                'view_count' => 6900,
                'is_featured' => false,
                'published_at' => '2026-01-12 11:05:00',
                'featured_image' => 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'setup-goc-may-rgb-legend-cho-game-thu',
                'category_slug' => 'setup-goc-may',
                'title' => 'Setup góc máy RGB Legend: Gọn gàng, đẹp mắt và đủ công năng',
                'excerpt' => 'Cảm hứng sắp xếp không gian chơi game với ánh sáng vừa đủ và hệ thống đi dây sạch sẽ.',
                'view_count' => 8600,
                'is_featured' => false,
                'published_at' => '2026-01-10 17:25:00',
                'featured_image' => 'https://images.unsplash.com/photo-1593062096033-9a26b09da705?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'laptop-ai-cho-sinh-vien-va-dan-van-phong',
                'category_slug' => 'laptop',
                'title' => 'Laptop AI cho sinh viên và dân văn phòng: Nên chọn thế nào?',
                'excerpt' => 'Các tiêu chí quan trọng về CPU, thời lượng pin, màn hình và khả năng nâng cấp.',
                'view_count' => 3700,
                'is_featured' => false,
                'published_at' => '2026-01-08 09:15:00',
                'featured_image' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=1000&q=85',
            ],
            [
                'slug' => 'uu-dai-phu-kien-gaming-dau-nam',
                'category_slug' => 'khuyen-mai',
                'title' => 'Ưu đãi phụ kiện gaming đầu năm: Nâng cấp góc máy tiết kiệm',
                'excerpt' => 'Tổng hợp các nhóm phụ kiện đang có mức giá tốt cho game thủ và người dùng sáng tạo.',
                'view_count' => 5200,
                'is_featured' => false,
                'published_at' => '2026-01-05 12:00:00',
                'featured_image' => 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?auto=format&fit=crop&w=1000&q=85',
            ],
        ];

        foreach ($posts as $post) {
            $category = PostCategory::query()->where('slug', $post['category_slug'])->first();
            if (! $category) {
                $this->command->warn("Missing news category: {$post['category_slug']}");

                continue;
            }

            Post::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'user_id' => $author->id,
                    'post_category_id' => $category->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $this->body($post['title'], $post['excerpt']),
                    'featured_image' => $post['featured_image'],
                    'status' => 'published',
                    'view_count' => $post['view_count'],
                    'is_featured' => $post['is_featured'],
                    'published_at' => Carbon::parse($post['published_at']),
                    'meta_title' => $post['title'].' - PC Center',
                    'meta_description' => $post['excerpt'],
                ],
            );
        }

        $this->command->info('News reference data seeded ('.count($posts).' posts).');
    }

    private function body(string $title, string $excerpt): string
    {
        return '<h2>'.$title.'</h2><p>'.$excerpt.'</p><p>Nội dung phân tích chi tiết sẽ giúp bạn chọn thiết bị phù hợp với nhu cầu, ngân sách và hệ sinh thái đang sử dụng.</p><h3>Điểm cần quan tâm</h3><p>Hãy cân nhắc hiệu năng thực tế, khả năng nâng cấp, độ ổn định và chính sách bảo hành trước khi quyết định.</p>';
    }
}
