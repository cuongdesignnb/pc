<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Theme colours and homepage blocks the shop edits in Cài đặt. Existing
     * admin values are preserved.
     *
     * @var array<int, array{key: string, value: string, group: string, type: string, label: string, is_public: bool}>
     */
    private const SETTINGS = [
        ['key' => 'theme_primary_color', 'value' => '#E30613', 'group' => 'appearance', 'type' => 'color', 'label' => 'Màu chủ đạo (thanh menu, nút chính, giá)', 'is_public' => true],
        ['key' => 'theme_secondary_color', 'value' => '#0B1F3A', 'group' => 'appearance', 'type' => 'color', 'label' => 'Màu phụ (nút Danh mục sản phẩm, thanh trên cùng, chân trang)', 'is_public' => true],
        ['key' => 'theme_accent_color', 'value' => '#FFC72C', 'group' => 'appearance', 'type' => 'color', 'label' => 'Màu nhấn (nút nổi bật, nhãn khuyến mãi)', 'is_public' => true],
        ['key' => 'homepage_product_tabs_title', 'value' => 'SẢN PHẨM BÁN CHẠY', 'group' => 'homepage', 'type' => 'text', 'label' => 'Tiêu đề khối sản phẩm theo tab', 'is_public' => true],
        ['key' => 'homepage_product_tabs', 'value' => '[{"label":"LAPTOP CŨ","category_slug":"laptop-cu"},{"label":"LAPTOP MỚI","category_slug":"laptop-moi"},{"label":"LINH KIỆN LAPTOP","category_slug":"linh-kien"}]', 'group' => 'homepage', 'type' => 'json', 'label' => 'Các tab của khối sản phẩm (tên tab và danh mục)', 'is_public' => true],
        ['key' => 'homepage_store_activity_title', 'value' => 'HOẠT ĐỘNG TẠI CỬA HÀNG', 'group' => 'homepage', 'type' => 'text', 'label' => 'Tiêu đề khối Hoạt động tại cửa hàng', 'is_public' => true],
        ['key' => 'homepage_store_activity_subtitle', 'value' => '', 'group' => 'homepage', 'type' => 'text', 'label' => 'Mô tả ngắn khối Hoạt động tại cửa hàng', 'is_public' => true],
    ];

    public function up(): void
    {
        Schema::create('store_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type', 10)->default('image');
            $table->string('image')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        foreach (self::SETTINGS as $setting) {
            if (DB::table('settings')->where('key', $setting['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        // Settings are user-editable data; keep them on rollback.
        Schema::dropIfExists('store_activities');
    }
};
