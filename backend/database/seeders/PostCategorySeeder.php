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
            ['name' => 'Hướng dẫn Build PC', 'slug' => 'huong-dan-build-pc', 'description' => 'Hướng dẫn xây dựng cấu hình máy tính', 'sort_order' => 2],
            ['name' => 'Review sản phẩm', 'slug' => 'review-san-pham', 'description' => 'Đánh giá chi tiết các sản phẩm phần cứng', 'sort_order' => 3],
            ['name' => 'Gaming', 'slug' => 'gaming', 'description' => 'Tin tức và review về PC Gaming', 'sort_order' => 4],
            ['name' => 'Tips & Tricks', 'slug' => 'tips-tricks', 'description' => 'Mẹo vặt và kinh nghiệm sử dụng PC', 'sort_order' => 5],
        ];

        foreach ($categories as $category) {
            PostCategory::create($category);
        }
    }
}
