<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = [
        'google_analytics_id',
        'google_tag_manager_id',
        'facebook_pixel_id',
    ];

    public function up(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->update(['is_public' => true]);
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', self::KEYS)->update(['is_public' => false]);
        Cache::forget('settings.public');
    }
};
