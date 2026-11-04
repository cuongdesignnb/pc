<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Homepage product blocks list in-stock products first; turn off in
 * Cài đặt → Trang chủ to keep the old order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('settings')->where('key', 'homepage_in_stock_first')->exists()) {
            DB::table('settings')->insert([
                'key' => 'homepage_in_stock_first',
                'value' => '1',
                'group' => 'homepage',
                'type' => 'boolean',
                'options' => null,
                'label' => 'Xếp sản phẩm còn hàng lên trước (khối bán chạy và danh mục)',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'homepage_in_stock_first')->delete();
    }
};
