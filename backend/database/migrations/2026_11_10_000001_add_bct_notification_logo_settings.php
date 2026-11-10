<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The "Đã thông báo Bộ Công Thương" badge in the footer business block. It shows
 * once the link to the shop's profile on online.gov.vn is filled in, so the
 * shop no longer needs a footer script to inject it.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'legal_bct_url', 'value' => '', 'type' => 'text',
            'label' => 'Link hồ sơ đã thông báo Bộ Công Thương (online.gov.vn); để trống thì ẩn logo'],
        ['key' => 'legal_bct_image', 'value' => 'https://online.gov.vn/Content/EndUser/LogoCCDVSaleNoti/logoSaleNoti.png', 'type' => 'image',
            'label' => 'Ảnh logo Bộ Công Thương'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as $setting) {
            if (! DB::table('settings')->where('key', $setting['key'])->exists()) {
                DB::table('settings')->insert($setting + [
                    'group' => 'legal',
                    'options' => null,
                    'is_public' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        Cache::forget('settings.public');
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_column(self::SETTINGS, 'key'))->delete();
        Cache::forget('settings.public');
    }
};
