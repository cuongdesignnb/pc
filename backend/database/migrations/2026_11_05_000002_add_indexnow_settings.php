<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * IndexNow for Bing: a generated key served at /indexnow-key.txt on the
 * storefront and a switch, off by default, to send changed product and
 * article URLs to api.indexnow.org.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            ['indexnow_enabled', '0', 'boolean', 'Bật IndexNow (tự báo Bing khi đăng hoặc sửa sản phẩm, bài viết)'],
            ['indexnow_key', bin2hex(random_bytes(16)), 'text', 'Khoá IndexNow (file khoá: laptopplus.vn/indexnow-key.txt)'],
        ];
        foreach ($rows as [$key, $value, $type, $label]) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'group' => 'seo',
                'type' => $type,
                'options' => null,
                'label' => $label,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['indexnow_enabled', 'indexnow_key'])->delete();
    }
};
