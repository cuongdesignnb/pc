<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first(); // Get first user (admin)
        if (!$admin) {
            $this->command->warn('No users found. Please create a user first.');
            return;
        }

        $categories = PostCategory::all();
        if ($categories->isEmpty()) {
            $this->command->warn('No post categories found. Please run PostCategorySeeder first.');
            return;
        }

        $posts = [
            [
                'title' => 'Top 5 cấu hình PC Gaming tầm trung 2026',
                'category_slug' => 'huong-dan-build-pc',
                'excerpt' => 'Khám phá 5 cấu hình PC Gaming tầm trung giá từ 15-25 triệu đồng, đáp ứng mọi tựa game hot hiện nay.',
                'body' => '<h2>Giới thiệu</h2><p>Trong năm 2026, nhu cầu về PC Gaming ngày càng cao. Dưới đây là 5 cấu hình tối ưu cho game thủ tầm trung...</p><h3>Cấu hình 1: Intel Core i5 + RTX 4060</h3><p>Cấu hình này phù hợp cho các tựa game esports và AAA ở mức 1080p...</p>',
                'is_featured' => true,
            ],
            [
                'title' => 'RTX 5080 chính thức ra mắt - Đánh giá hiệu năng',
                'category_slug' => 'review-san-pham',
                'excerpt' => 'NVIDIA vừa chính thức công bố RTX 5080 với hiệu năng vượt trội. Cùng xem đánh giá chi tiết.',
                'body' => '<h2>Thông số kỹ thuật</h2><p>RTX 5080 sở hữu 10240 CUDA Cores, 16GB GDDR6X...</p>',
                'is_featured' => true,
            ],
            [
                'title' => 'Hướng dẫn tối ưu hiệu năng Windows 11 cho Gaming',
                'category_slug' => 'tips-tricks',
                'excerpt' => 'Những thủ thuật đơn giản giúp tăng FPS và giảm độ trễ khi chơi game trên Windows 11.',
                'body' => '<h2>Tắt các hiệu ứng không cần thiết</h2><p>Windows 11 có nhiều hiệu ứng đồ họa tốn tài nguyên...</p>',
                'is_featured' => false,
            ],
            [
                'title' => 'AMD Ryzen 9 7950X3D vs Intel Core i9-14900K',
                'category_slug' => 'review-san-pham',
                'excerpt' => 'So sánh chi tiết hai con chip hàng đầu từ AMD và Intel trong năm 2026.',
                'body' => '<h2>Hiệu năng Gaming</h2><p>Ryzen 9 7950X3D với công nghệ 3D V-Cache cho hiệu năng gaming vượt trội...</p>',
                'is_featured' => true,
            ],
            [
                'title' => 'Tin tức: DDR5 giảm giá mạnh trong Q1/2026',
                'category_slug' => 'tin-tuc-cong-nghe',
                'excerpt' => 'Giá RAM DDR5 đã giảm gần 40% so với đầu năm, đây là thời điểm tốt để nâng cấp PC.',
                'body' => '<h2>Xu hướng giá</h2><p>Theo báo cáo từ các nhà sản xuất, giá RAM DDR5 đang có xu hướng giảm...</p>',
                'is_featured' => false,
            ],
            [
                'title' => '10 Game AAA đáng chơi nhất năm 2026',
                'category_slug' => 'gaming',
                'excerpt' => 'Danh sách các tựa game AAA được đánh giá cao nhất trong năm nay.',
                'body' => '<h2>Top 1: Cyberpunk 2078</h2><p>Phần tiếp theo của Cyberpunk 2077 với đồ họa tuyệt đẹp...</p>',
                'is_featured' => true,
            ],
            [
                'title' => 'Cách chọn nguồn máy tính phù hợp',
                'category_slug' => 'huong-dan-build-pc',
                'excerpt' => 'Hướng dẫn chi tiết cách tính công suất và chọn nguồn máy tính cho PC.',
                'body' => '<h2>Tính toán công suất</h2><p>Để chọn nguồn phù hợp, bạn cần tính tổng TDP của CPU + GPU...</p>',
                'is_featured' => false,
            ],
            [
                'title' => 'SSD NVMe Gen 5 - Có đáng để nâng cấp?',
                'category_slug' => 'review-san-pham',
                'excerpt' => 'Phân tích hiệu năng thực tế của SSD NVMe Gen 5 so với Gen 4.',
                'body' => '<h2>Tốc độ đọc/ghi</h2><p>Gen 5 có tốc độ lý thuyết lên đến 14000 MB/s nhưng trong thực tế...</p>',
                'is_featured' => false,
            ],
        ];

        foreach ($posts as $postData) {
            $category = $categories->firstWhere('slug', $postData['category_slug']);
            
            Post::create([
                'user_id' => $admin->id,
                'post_category_id' => $category?->id,
                'title' => $postData['title'],
                'slug' => Str::slug($postData['title']),
                'excerpt' => $postData['excerpt'],
                'body' => $postData['body'],
                'status' => 'published',
                'is_featured' => $postData['is_featured'],
                'published_at' => now()->subDays(rand(0, 30)),
                'view_count' => rand(50, 5000),
            ]);
        }
    }
}
