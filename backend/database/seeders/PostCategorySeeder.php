<?php

namespace Database\Seeders;

use App\Models\PostCategory;
use Illuminate\Database\Seeder;

class PostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Tin tức công nghệ', 'slug' => 'tin-tuc-cong-nghe', 'description' => 'Tin tức mới nhất về công nghệ, phần cứng máy tính', 'sort_order' => 1],
            ['name' => 'Review sản phẩm', 'slug' => 'review-san-pham', 'description' => 'Đánh giá chi tiết các sản phẩm phần cứng', 'sort_order' => 2],
            ['name' => 'Hướng dẫn', 'slug' => 'huong-dan', 'description' => 'Hướng dẫn sử dụng và nâng cấp máy tính', 'sort_order' => 3],
            ['name' => 'Khuyến mãi', 'slug' => 'khuyen-mai', 'description' => 'Ưu đãi dành cho PC, laptop và phụ kiện', 'sort_order' => 4],
            ['name' => 'Hướng dẫn Build PC', 'slug' => 'huong-dan-build-pc', 'description' => 'Hướng dẫn xây dựng cấu hình máy tính', 'sort_order' => 5],
            ['name' => 'Laptop', 'slug' => 'laptop', 'description' => 'Tin tức, đánh giá và tư vấn laptop', 'sort_order' => 6],
            ['name' => 'Gaming Gear', 'slug' => 'gaming-gear', 'description' => 'Bàn phím, chuột, tai nghe và thiết bị gaming', 'sort_order' => 7],
            ['name' => 'Gaming', 'slug' => 'gaming', 'description' => 'Tin tức và review về PC Gaming', 'sort_order' => 8],
            ['name' => 'Tips & Tricks', 'slug' => 'tips-tricks', 'description' => 'Mẹo vặt và kinh nghiệm sử dụng PC', 'sort_order' => 9],
            ['name' => 'VGA', 'slug' => 'vga', 'description' => 'Tin tức và tư vấn card đồ họa', 'sort_order' => 10],
            ['name' => 'CPU', 'slug' => 'cpu', 'description' => 'Tin tức và tư vấn bộ vi xử lý', 'sort_order' => 11],
            ['name' => 'Setup góc máy', 'slug' => 'setup-goc-may', 'description' => 'Cảm hứng bố trí góc làm việc và gaming', 'sort_order' => 12],
            ['name' => 'Mẹo tối ưu game', 'slug' => 'meo-toi-uu-game', 'description' => 'Thủ thuật tối ưu trải nghiệm chơi game', 'sort_order' => 13],
        ];

        foreach ($categories as $category) {
            PostCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
