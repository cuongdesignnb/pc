<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Time zone the admin enters and reads dates in (post publish times).
 * Stored data stays UTC; this only changes how admin inputs are read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'site_timezone')->exists()) {
            return;
        }
        DB::table('settings')->insert([
            'key' => 'site_timezone',
            'value' => 'Asia/Ho_Chi_Minh',
            'group' => 'general',
            'type' => 'select',
            'options' => json_encode(['choices' => ['Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Singapore', 'Asia/Tokyo', 'UTC']]),
            'label' => 'Múi giờ của cửa hàng (giờ đăng bài trong admin)',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'site_timezone')->delete();
        Cache::forget('settings.public');
    }
};
